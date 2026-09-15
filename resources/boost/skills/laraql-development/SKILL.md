---
name: laraql-development
description: Build and change code-first GraphQL schemas with LaraQL (nodesol/laraql) on Laravel Lighthouse. Use when adding or editing #[Model] Eloquent models, #[Type] / #[Input] / #[Query] / #[QueryCollection] / #[Mutation] classes, generated CRUD operations, filters, type and input overrides, relation fields, policy authorization, or when the generated schema does not match expectations.
---

# LaraQL development

LaraQL turns PHP attributes into the Lighthouse GraphQL schema at runtime. For everything LaraQL generates there is no SDL to maintain: the attributes on the PHP classes are the schema.

- Full attribute reference: `references/attributes.md`
- Debugging walkthrough: `references/troubleshooting.md`

## When to use this skill

- Adding or changing a GraphQL entity backed by an Eloquent model.
- Customizing the generated queries, mutations, filters, field types, inputs or authorization.
- Exposing relationships, computed fields, or standalone types, inputs, queries and mutations.
- Debugging missing fields, missing types, duplicate types or stale schemas.

## Mental model

1. `config('laraql.directories')` (default `app/Models` and `app/GraphQL`) is scanned recursively for `*.php`; the file path is mapped back to the application namespace, so the classes must be autoloadable from there.
2. `#[Model]` on a concrete Eloquent model generates a type, an input, a single query, a paginated collection query, create/update/delete mutations and a paginator type. `#[Type]`, `#[Input]`, `#[Query]`, `#[QueryCollection]` and `#[Mutation]` generate their own piece on any scanned class.
3. The SDL is injected into Lighthouse through the `BuildSchemaString` event and merged with the app's schema file, which may stay empty.
4. The schema is generated from live data: table columns (`Schema::getColumns()`), the model's `$fillable` / `$hidden`, and public zero-argument relation methods. There is nothing to synchronize manually.

## Step 1 - the model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nodesol\LaraQL\Attributes\Model as LaraQL;

#[LaraQL()]
class Article extends Model
{
    protected $fillable = ['title', 'body', 'user_id'];

    protected $hidden = ['internal_notes'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
```

This produces:

- type `Article` with every table column except the hidden ones, plus `user: User` and `comments: [Comment]`;
- input `ArticleInput` with the fillable columns;
- queries `article(id: ID @eq): Article @find` and `articles(...): ArticlePaginator @paginate(defaultCount: 10)`;
- mutations `createArticle(input: ArticleInput! @spread)`, `updateArticle(id: ID!, input: ArticleInput! @spread)` and `deleteArticle(id: ID! @whereKey)`;
- the paginator type `ArticlePaginator` with `data` and `paginatorInfo`.

## Step 2 - what drives the schema

| Model/attribute | Effect |
| --- | --- |
| `$fillable` | Columns accepted by the generated input. Nothing else is writable. |
| `$hidden` | Columns that never appear in the generated type. |
| neither | The column is queryable but not writable. |
| `$guarded = []` | Not understood by LaraQL: the input is built from `getFillable()`, so an unguarded model produces an empty input. List the writable columns in `$fillable`. |
| relation methods | Public, zero-argument, `Relation` return type: to-one becomes `Related`, to-many becomes `[Related]`. |
| `type_override` | Overrides or adds type fields, including directives such as `@rename`, `@method`, `@belongsTo`. |
| `input_override` | Overrides or adds input fields, commonly `@rules(apply: [...])`. |
| `directives` | Applied to every generated operation of the model (`@guard`, `@model`, `@namespace`). |
| `authorize` | `true` uses the Laravel policy; a string is used verbatim as a directive. |
| `models.auto_include` | When true, every concrete Eloquent model in the scanned directories is exposed without `#[Model]`. |

## Step 3 - customize generated operations

Each generated operation is configurable through the `operations` array on `#[Model]`, using the arguments of the attribute that produces it:

| `operations` key | Attribute | Notable arguments |
| --- | --- | --- |
| `query` | `#[Query]` | `name`, `filters`, `filters_override`, `query`, `return_type`, `directives`, `authorize` |
| `query_collection` | `#[QueryCollection]` | `name`, `filters`, `filters_override`, `query`, `return_type`, `directives`, `authorize` |
| `create` | `#[Mutation]` (`name: 'create'`) | `inputs`, `query`, `return_type`, `directives`, `authorize` |
| `update` | `#[Mutation]` (`name: 'update'`) | same as create |
| `delete` | `#[Mutation]` (`name: 'delete'`) | same as create |

Defaults worth remembering:

- `#[Query]`: `name` = `Str::snake(class)`, `filters` = `['id: ID @eq']`, `query` = `'@find'`, `return_type` = the class short name.
- `#[QueryCollection]`: `name` = `Str::snake(Str::plural(class))`, `filters` = `['where: _ @whereConditions(column: {})', 'first: Int! = 10', 'page: Int', 'orderBy: _ @orderBy']`, `return_type` = `[Type!]!` (Lighthouse turns that into `<Type>Paginator` because of `@paginate`), `query` = `'@paginate(defaultCount: 10)'`.
- `#[Mutation]`: `name` is required, `inputs` default to `['input' => 'TypeInput! @spread']` for create, `['id' => 'ID!', 'input' => 'TypeInput! @spread']` for update and `['id' => 'ID! @whereKey']` for delete, `query` defaults to `'@<name>'`.

Replacing the whole filter list is the cleanest way to add or remove arguments:

```php
#[LaraQL(
    operations: [
        'query_collection' => [
            'filters_override' => [
                'where: _ @whereConditions(column: {})',
                'first: Int! = 10',
                'page: Int',
                'orderBy: _ @orderBy',
                'status: String @eq',
            ],
        ],
    ],
)]
class Article extends Model
{
}
```

`#[Model]` always generates all five operations; there is no flag to switch one off. Restrict an operation with `authorize` or `directives` (for example `authorize: '@canFind(ability: "delete", find: "id")'`), or drive that entity entirely through standalone `#[Query]` / `#[Mutation]` classes when a model should not expose CRUD.

## Step 4 - standalone types, inputs, queries and mutations

Any class in the scanned directories can carry these attributes; the class itself becomes the resolver holder when `query` points at one of its methods.

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

- `#[Type(columns: [...], columns_override: [...], create_paginator: true)]` writes an object type; `columns` replaces the reflection based detection and `columns_override` merges on top of it.
- `#[Input(inputs: [...], inputs_override: [...])]` behaves the same way for input objects.
- `#[Type(extends: 'Node')]` emits a type extension.
- Add `@guard`, `@can*` or `@rules` to any of these through the `directives`, `return_type` or override values.

## Step 5 - authorization

- `authorize: true` on `#[Model]` uses the Laravel policy: `view` for the single query, `viewAny` for the collection query, `create`, `update` and `delete` for the mutations.
- Pass a raw directive string when the policy ability differs, for example `authorize: '@canFind(ability: "publish", find: "id")'`.
- `directives: ['@guard']` on `#[Model]` applies to all generated operations; directives added per operation are appended to the model level ones.
- The policy must exist and allow the ability, otherwise Lighthouse's `@canModel` / `@canFind` denies the request.

## Step 6 - verify

1. Run the migration: LaraQL introspects the real tables, and an unmigrated table breaks schema building.
2. Clear the schema cache when `laraql.cache` is enabled (the default outside debug) - the SDL is cached forever under the `laraql_schema` key.
3. Print and validate the merged schema:

```bash
php artisan lighthouse:print-schema
php artisan lighthouse:validate-schema
```

4. Exercise the operation with Lighthouse's testing helpers:

```php
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;

$this->graphQL('
    mutation {
        createArticle(input: { title: "Hello" }) { id title }
    }
')->assertJsonPath('data.createArticle.title', 'Hello');
```

## Do not

- Do not add SDL for generated types, inputs, queries, mutations or paginators to `.graphql` files.
- Do not duplicate a `#[Model]` type that hand-written SDL already defines - Lighthouse rejects duplicate type definitions.
- Do not implement authorization in resolvers when `authorize` or `directives` cover it.
- Do not put GraphQL classes outside `laraql.directories` and expect discovery.
- Do not enable `models.auto_include` just to expose a single model - use `#[Model]` on that model.
- Do not hand-write paginator types for `#[QueryCollection]`: Lighthouse already generates `<Type>Paginator` from `@paginate`.

