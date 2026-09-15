# LaraQL

`nodesol/laraql` (LaraQL) gives a Laravel application a code-first GraphQL schema on top of Nuwave Lighthouse: PHP attributes on Eloquent models and plain PHP classes are scanned and compiled into SDL at runtime, so PHP classes are the source of truth for the schema - not `.graphql` files.

- Schema work (models, attributes, generated CRUD, overrides, authorization): use the `laraql-development` skill.

@verbatim
- Meilisearch full-text search is exposed through LaraQL's own `@scoutFilters` directive, which keeps the normal `where`, `orderBy` and pagination working: use the `laraql-scout-search` skill.
@endverbatim

- Package docs: https://nodesol.github.io/laraql

## How the schema is assembled

- `config/laraql.php` (publish with the `laraql-config` tag) controls everything:
    - `directories` is scanned recursively for `*.php` and defaults to `app_path('Models')` and `app_path('GraphQL')`.
    - `models.auto_include` (env `LARAQL_MODELS_AUTO_INCLUDE`): when true, every concrete Eloquent model found in those directories joins the schema even without the `#[Model]` attribute.
    - `cache` (env `LARAQL_CACHE`): caches the generated SDL forever in the `laraql_schema` cache entry. Defaults to `true` unless `app.debug` is on.
- Files are reflected and mapped back to the application namespace, so the classes must be autoloadable from the scanned directories.
- The generated SDL is merged into Lighthouse through the `BuildSchemaString` event. The Lighthouse schema file itself may stay empty - that is exactly what `php artisan vendor:publish --tag=laraql-schema` installs, and it prevents the sample `User` type shipped in Lighthouse's default schema from colliding with generated types.

@verbatim
## Rules

- Never hand-write SDL for anything LaraQL generates (types, inputs, queries, mutations, paginators). Change the attribute, the model, or the config instead. Keep SDL only for what LaraQL does not cover: custom scalars (LaraQL ships only `Upload`, `DateTime` and `Date`), enums and custom directives.
- The database is introspected while the schema is built: `Schema::getColumns()` runs for every `#[Model]` class and relation methods are invoked on a fresh model instance. Migrations must have run and the connection must work before the schema can be built, printed, validated or tested.
- Generated type fields = table columns minus `$hidden`. Generated input fields = `$fillable` only. Change those properties instead of patching generated output.
- Relation fields are discovered automatically from public methods that take no arguments and declare an Eloquent `Relation` return type: to-one relations become `Related`, to-many relations become `[Related]`. Add a `type_override` entry when a relation field needs another type or extra directives.
- Column to GraphQL type mapping is an ordered match on the database type name reported by `Schema::getColumns()`: integers (`smallint`, `mediumint`, `int`, `integer`, `bigint`, `year`, `binary`) become `Int`, or `ID` when auto-increment; the string-like types (`string`, `varchar`, `text`, `blob`, `json`, `enum`, `ascii_string`, `array`) become `String`; `boolean` and `tinyint` become `Boolean`; `float` and `decimal` become `Float`; `object` becomes `Json`; `date` becomes `Date`; the remaining date and time types become `DateTime`; anything unknown falls back to `String`. Non-nullable columns get a trailing `!`. Check `lighthouse:print-schema` when the mapping is not what the API should expose, and change it with `type_override` / `input_override`.
- `$fillable` decides what clients may create or update (`getFillable()` is used literally, so a model that unguards instead of listing `$fillable` generates an empty input), `$hidden` keeps a column out of the type. Setting neither means the column is queryable but never writable.
- Caching bites twice: with `laraql.cache` enabled the generated SDL is cached forever in the `laraql_schema` entry, and Lighthouse caches the compiled AST in `bootstrap/cache/lighthouse-schema.php` when `lighthouse.schema_cache.enable` is on (default outside `local`). After changing models, attributes or config clear both before investigating a "missing field/type" report.
- Adding `#[Model]` to `class Article` generates the type `Article`, the input `ArticleInput`, the queries `article` and `articles`, the mutations `createArticle`, `updateArticle`, `deleteArticle`, and the paginator type `ArticlePaginator` with `data` and `paginatorInfo`.
- Authorization belongs in the attributes: `authorize: true` maps to the Laravel policy (`view`, `viewAny`, `create`, `update`, `delete` through `@canFind` and `@canModel`), an explicit directive string is passed through, and `directives: ['@guard']` requires authentication. Do not re-implement authorization in resolvers.
- Anything not scanned is silently skipped: a class outside `laraql.directories`, an abstract model, or a model without `#[Model]` while `models.auto_include` is false. Check this first when a type or query is missing.
- The generated schema is built from Lighthouse directives (`@find`, `@paginate`, `@create`, `@update`, `@delete`, `@eq`, `@whereConditions`, `@orderBy`, `@spread`, `@whereKey`, `@field`, `@rules`, `@rename`, `@hasMany`, `@belongsTo`, ...). Prefer them over custom resolution logic.

## Workflow: add or change an entity

1. Create or edit the model in `app/Models`, import `Nodesol\LaraQL\Attributes\Model as LaraQL` and add the attribute.
2. Set `$fillable` (drives the create and update input) and `$hidden` (keeps columns out of the type). Add public relation methods for nested data.
3. Override only what needs overriding: `type_override`, `input_override`, `operations`, `directives`, `authorize`.
4. Run the migration, then clear the caches.
5. Verify the merged SDL, then exercise the operation with Lighthouse's test helpers (`$this->graphQL('query { ... }')`) before shipping.

## Workflow: customize a generated operation

Pass the arguments of the attribute that generates the operation into the matching `operations` key. Keys are `query`, `query_collection`, `create`, `update` and `delete`.

```php
use Nodesol\LaraQL\Attributes\Model as LaraQL;

#[LaraQL(
    directives: ['@guard'],
    operations: [
        // Rename the collection query and rework its arguments
        'query_collection' => [
            'name' => 'publishedArticles',
            'filters_override' => ['status: String @eq'],
            'query' => '@paginate(defaultCount: 25)',
        ],
    ],
)]
class Article extends Model
{
}
```

## Workflow: standalone types, inputs, queries and mutations

Any class inside the scanned directories may carry `#[Type]`, `#[Input]`, `#[Query]`, `#[QueryCollection]` or `#[Mutation]`. LaraQL generates the SDL from the attribute arguments and the class becomes the resolver holder when the `query` argument points at one of its methods.

```php
namespace App\GraphQL;

use App\Models\Article as ArticleModel;
use Nodesol\LaraQL\Attributes\Query;

#[Query(
    name: 'articleBySlug',
    filters: ['slug: String! @eq'],
    return_type: 'Article',
    query: '@field(resolver: "Article@findBySlug")',
)]
class Article
{
    public function findBySlug(mixed $rootValue, array $args): ArticleModel
    {
        return ArticleModel::where('slug', $args['slug'])->firstOrFail();
    }
}
```

For hand-written object types use `#[Type(name: 'Article', create_paginator: true, columns: [...], columns_override: [...])]`, and `#[Input(name: 'ArticleInput', inputs: [...], inputs_override: [...])]` for inputs. A type with `create_paginator: true` also emits `ArticlePaginator` wired to Lighthouse's paginator field resolvers.


## Overrides: types, inputs, fields

- `type_override`: keys are field names, values are `Type` or `Type @directive`, merged over the columns detected for the model.
- `input_override`: the same idea for the generated input.
- Both accept fields that do not exist as columns, which is how computed fields, custom relation fields and enum types are exposed.

```php
#[LaraQL(
    type_override: [
        'name' => 'String! @rename(attribute: "title")',
        'status_label' => 'String @method(name: "statusLabel")',
    ],
    input_override: [
        'title' => 'String! @rules(apply: ["required", "max:200"])',
    ],
)]
class Article extends Model
{
}
```

@endverbatim

## Commands

- `{{ $assist->artisanCommand('vendor:publish --tag=laraql-config') }}` - publish `config/laraql.php` to change scan paths, caching or model auto-inclusion.
- `{{ $assist->artisanCommand('vendor:publish --tag=laraql-schema') }}` - install the empty Lighthouse schema file that LaraQL fills in. Prefer this over Lighthouse's example schema (`--tag=lighthouse-schema`), whose sample `User` type can clash with generated types.
- `{{ $assist->artisanCommand('lighthouse:print-schema') }}` - print the merged SDL to see exactly what LaraQL generated. This is the fastest way to debug a schema complaint.
- `{{ $assist->artisanCommand('lighthouse:validate-schema') }}` - validate the merged SDL.
- `{{ $assist->artisanCommand('cache:clear') }}` - drop the cached `laraql_schema` entry after schema-affecting changes (or set `LARAQL_CACHE=false` while developing).
- `{{ $assist->artisanCommand('lighthouse:clear-schema-cache') }}` - drop Lighthouse's compiled schema cache (`bootstrap/cache/lighthouse-schema.php`) when `lighthouse.schema_cache.enable` is on. `lighthouse:print-schema` clears it automatically.
- The GraphQL endpoint is Lighthouse's route (default `/graphql`); `mll-lab/laravel-graphiql` adds a `/graphiql` route (name `graphiql`) to explore the generated schema by hand.

@verbatim
## Client-facing shape of generated operations

```graphql
# Single record: `article(id: ID @eq): Article @find`
query {
    article(id: 1) {
        id
        title
    }
}

# Collection: `articles(...): ArticlePaginator @paginate(defaultCount: 10)`
query {
    articles(
        where: { AND: [{ column: "title", operator: LIKE, value: "%laravel%" }] }
        orderBy: [{ column: "id", order: DESC }]
        first: 20
        page: 1
    ) {
        data { id title }
        paginatorInfo { total currentPage lastPage hasMorePages }
    }
}

# Mutations map to Lighthouse @create / @update / @delete
mutation {
    createArticle(input: { title: "Hello", body: "World" }) { id title }
    updateArticle(id: 1, input: { title: "Updated" }) { id title }
    deleteArticle(id: 1) { id }
}
```

## Troubleshooting quick hits

- Type or query missing: the class is not inside `laraql.directories`, is abstract, is missing `#[Model]` while `models.auto_include` is false, or the SDL cache is stale.
- Field missing from the type: the column is listed in `$hidden`, or the relation method is not public, takes arguments, or lacks a `Relation` return type.
- Field rejected in mutations: the column is not in `$fillable`.
- "Unknown type" or duplicate type errors: a column whose database type maps to a scalar the app never defines (for example an `object` column mapping to `Json`), or hand-written SDL that duplicates what LaraQL generates.
- Schema cannot be built: the database is unreachable or migrations have not run, because LaraQL introspects the real tables.
- After deploying: clear the cache wherever `laraql.cache` is enabled, otherwise the previously generated SDL keeps being served.

See the `laraql-development` skill (with `references/attributes.md` and `references/troubleshooting.md`) for the full attribute reference and a deeper debugging walkthrough.
@endverbatim

