---
name: laraql-scout-search
description: Add full-text search and Meilisearch filtering to LaraQL (nodesol/laraql) GraphQL queries with the @scoutFilters directive. Use when adding a scoutSearch argument, combining Scout search with where conditions on Eloquent queries, restricting filterable columns, or writing a custom ScoutFilters handler.
---

# LaraQL Scout search

Lighthouse's built-in Scout support filters in Meilisearch but cannot apply normal `where` conditions afterwards, because the result set never touches the database. LaraQL ships a `@scoutFilters` directive (in `Nodesol\LaraQL\ScoutFilters`) that searches Scout first, collects the matching primary keys, and then constrains the Eloquent query with `whereIn`, so ordinary `where` conditions, `orderBy` and pagination keep working.

Supported driver: Meilisearch only (`Nodesol\LaraQL\ScoutFilters\MeilisearchOperator` is bound for every driver value).

## What gets registered

The `ScoutFiltersServiceProvider` (registered by LaraQL) adds to every schema:

- `directive @scoutFilters(columns: [String!], columnsEnum: String, handler: String = "\\Nodesol\\LaraQL\\ScoutFilters\\ScoutFiltersHandler")` - the escaped backslashes are the SDL representation of the FQCN.
- `input ScoutFilters { search: String, filters: ScoutFiltersCondition }`.
- `input ScoutFiltersCondition { column: String, operator: MeilisearchOperator = EQ, value: Mixed, AND: [ScoutFiltersCondition!], OR: [ScoutFiltersCondition!] }` - `column` becomes the generated columns enum when the argument is restricted.
- `enum MeilisearchOperator` with `EQ`, `NEQ`, `GT`, `GTE`, `LT`, `LTE`, `IN`, `NOT_IN`, `EXISTS`, `NOT_EXISTS`, `IS_EMPTY`, `IS_NOT_EMPTY`, `IS_NULL`, `NOT_NULL`, `BETWEEN`, `FUNCTION`.
- `scalar Mixed` (from `mll-lab/graphql-php-scalars`).

When the argument is declared with `columns` or `columnsEnum`, LaraQL swaps the unrestricted `ScoutFilters` input for a restricted one built from Lighthouse's arg-qualified names - for `articles(scoutSearch: _ @scoutFilters(columns: [...]))` that is `QueryArticlesScoutSearchScoutFilters`, its `QueryArticlesScoutSearchScoutFiltersCondition` counterpart and the `QueryArticlesScoutSearchColumn` enum.

## Step 1 - prerequisites

- `laravel/scout` installed, driver set to `meilisearch`, and the model using `Laravel\Scout\Searchable`.
- The Meilisearch index must have the filtered attributes declared as `filterableAttributes`, otherwise Meilisearch rejects the filter (or silently returns nothing).
- Reindex with `php artisan scout:import "App\Models\Article"` after changing the index settings or the searchable array.

## Step 2 - expose the argument

Add the argument to a generated query through `filters_override` (or use a standalone `#[Query]` / `#[QueryCollection]` class):

```php
use Nodesol\LaraQL\Attributes\Model as LaraQL;

#[LaraQL(
    operations: [
        'query_collection' => [
            'filters_override' => [
                'where: _ @whereConditions(column: {})',
                'first: Int! = 10',
                'page: Int',
                'orderBy: _ @orderBy',
                'scoutSearch: _ @scoutFilters',
            ],
        ],
    ],
)]
class Article extends Model
{
}
```

Restrict the client to specific columns for a smaller, safer API surface:

```php
'filters_override' => [
    'scoutSearch: _ @scoutFilters(columns: ["title", "created_at", "status"])',
],
```

## Step 3 - query it

```graphql
query {
    articles(
        where: { AND: [{ column: "status", operator: EQ, value: "published" }] }
        orderBy: [{ column: "created_at", order: DESC }]
        first: 20
        page: 1
        scoutSearch: {
            search: "laravel"
            filters: {
                AND: [
                    { column: "status", operator: EQ, value: "published" },
                    { operator: FUNCTION, value: "_geoRadius(34.154778, -104.645259, 10000)" }
                ]
            }
        }
    ) {
        data { id title }
        paginatorInfo { total currentPage lastPage hasMorePages }
    }
}
```

`search` is the full-text query (omit it to filter without a search term); `filters` are translated into a Meilisearch filter expression. Both the Scout filter and the `where` conditions apply: Scout narrows the primary keys, then the Eloquent query filters, orders and paginates the result.

## Operators

| Operator | Meilisearch expression | Notes |
| --- | --- | --- |
| `EQ` / `NEQ` | `column = value` / `column != value` | Default when `operator` is omitted. |
| `GT`, `GTE`, `LT`, `LTE` | `column > value` and friends | |
| `IN` / `NOT_IN` | `column IN [value]` | Pass the list as one comma separated string, quoting string values. |
| `EXISTS` / `NOT_EXISTS` | `column EXISTS` | No value needed. |
| `IS_EMPTY` / `IS_NOT_EMPTY` | `column IS EMPTY` | No value needed. |
| `IS_NULL` / `NOT_NULL` | `column IS NULL` / `column IS NOT NULL` | No value needed. |
| `BETWEEN` | `column min TO max` | Pass `value: "min,max"`; only the first two comma separated values are used. |
| `FUNCTION` | raw expression | `value` is inserted as-is, e.g. `_geoRadius(48.8, 2.3, 5000)`. |

`AND` / `OR` nest conditions and are wrapped in parentheses while the filter expression is built.

## Step 4 - custom handler

Pass `handler:` to replace the default `ScoutFiltersHandler`. The handler is resolved from the container and called as `$handler($builder, $value)`, so an invokable class is the simplest implementation:

```php
namespace App\GraphQL;

class TenantScoutFiltersHandler
{
    public function __invoke(object $builder, array $scoutFilters): void
    {
        // $scoutFilters holds the client input: search, filters, ...
        // Apply your own logic, or delegate to the LaraQL handler:
        // app(\Nodesol\LaraQL\ScoutFilters\ScoutFiltersHandler::class)($builder, $scoutFilters);
    }
}
```

Use it through `'scoutSearch: _ @scoutFilters(handler: "App\\GraphQL\\TenantScoutFiltersHandler")'`.

## How the default handler behaves

1. Resolves the model from the Eloquent builder.
2. Builds the Meilisearch filter expression from `filters` (recursively for `AND` / `OR`).
3. Calls `Model::search($scoutFilters['search'] ?? '', callback)` and overrides the index options: `filter`, `limit` (`config('scout.<driver>.index-settings.<Model>.pagination.maxTotalHits', 100000)`) and `attributesToRetrieve` (the model key only), using `rawSearch`.
4. Collects the returned key values and applies `whereIn(model->getKeyName(), $ids)` on the Eloquent builder.

Ordering of the final result is therefore the database's, not Meilisearch's. Use `orderBy` (or a Scout relevance sort inside a custom handler) when relevance order matters.

## Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| Always an empty result set | The index does not contain the records, or the search term does not match | Run `php artisan scout:import`, check the driver config, verify with `Model::search('...')->raw()` |
| "Invalid facet distribution / filter" or a Meilisearch 400 error | The filtered attribute is not declared as filterable | Add it to `filterableAttributes` in `config/scout.php` and re-import |
| `call to a member function search() on null` | The model does not use `Laravel\Scout\Searchable` | Add the trait |
| Filters work in Meilisearch but results are empty in GraphQL | Scout returned hits whose keys are filtered out by the Eloquent `where` conditions | Loosen the `where` conditions, or move them into the Scout filters |
| `search` argument is unknown | The argument was not added through `filters_override` | Add `'scoutSearch: _ @scoutFilters'` to the query arguments |
| Only Meilisearch behaviour is expected | LaraQL binds `MeilisearchOperator` for every driver | Only rely on `@scoutFilters` with the Meilisearch driver |

