# LaraQL troubleshooting

Work through this order before changing code.

1. Does the class even get scanned? It must live inside `config('laraql.directories')` (default `app/Models` and `app/GraphQL`), be a `*.php` file, be PSR-4 autoloadable in the application namespace, and (for models) be a concrete `Illuminate\Database\Eloquent\Model` subclass. A model also needs `#[Model]` unless `laraql.models.auto_include` is enabled.
2. Is the generated SDL what you expect? `php artisan lighthouse:print-schema` prints whatever Lighthouse actually built, including everything LaraQL injected.
3. Is a cached copy being served? `laraql.cache` defaults to enabled outside debug and stores the SDL forever under the `laraql_schema` key, and Lighthouse caches the compiled AST in `bootstrap/cache/lighthouse-schema.php` when `lighthouse.schema_cache.enable` is on. Clear both, plus the config cache when `config/laraql.php` changed.
4. Does the database match the code? The schema is built from the live tables; run migrations first, and keep the connection available in the environment that builds the schema (including CI and production deploys).
5. Is the merged SDL valid? `php artisan lighthouse:validate-schema`.

## Symptoms and fixes

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| A type, input or query is missing from the schema | Class outside `laraql.directories`, abstract class, missing `#[Model]` with `models.auto_include` off, or a stale `laraql_schema` cache entry | Move the class, drop `abstract`, add the attribute, or clear the cache |
| A column is missing from the generated type | The column is listed in the model's `$hidden` | Remove it from `$hidden` or override it explicitly in `type_override` |
| A field cannot be set by a mutation | The column is not in `$fillable` | Add it to `$fillable` or use `input_override` |
| The generated input is empty | The model unguards (`$guarded = []`) or uses `Model::unguard()`; the input is built from `getFillable()`, which stays empty | List the writable columns in `$fillable` |
| Relation field is missing | The relation method is not public, takes arguments, has no return type, or does not return an Eloquent `Relation` | Make it `public function relation(): HasMany` (a real relation class), no arguments |
| Relation field has the wrong type | LaraQL maps to-one relations to `Related` and to-many to `[Related]` | Override the field with `type_override`, e.g. `'comments' => '[Comment!]! @hasMany'` |
| Relation field errors with "call to a member function on null" | LaraQL invokes the relation method while building the schema, so the method must not depend on request state | Keep relation methods side-effect free and DB-safe |
| Schema build fails with a database error | Migrations have not run, or the connection is unavailable while the schema is built | Run migrations, make the connection available, and clear the schema cache |
| "Unknown type X" during schema build or query validation | A column or override maps to a scalar the app never defines (`Json` for `object` columns, or a custom enum) | Define the scalar or enum in `graphql/schema.graphql`, or override the field type |
| "Duplicate type definition" or a type exists twice | Hand-written SDL defines a type LaraQL also generates (`Article`, `ArticleInput`, `User`, ...) | Delete the hand-written definition; LaraQL is the source of truth. Also avoid publishing Lighthouse's example schema, which defines an example `User` type |
| Query returns "Cannot query field X on type Query" although the attribute exists | Name collision, or the query lives on a class that is not scanned | Print the schema, compare names, move the class into a scanned directory |
| `first`/`page`/pagination behaves unexpectedly on a collection query | `@paginate` adds its own `first` and `page` arguments and rewrites the return type to `<Type>Paginator` | Query `data` and `paginatorInfo`, and set the count through the directive or `filters_override` |
| `where` argument rejects a column | `@whereConditions` is generated with an unrestricted clause input, so the error usually comes from the underlying SQL, not LaraQL | Check that the column exists, and prefer the generated columns over raw JSON paths |
| Changes do not appear in production | The SDL was cached when `laraql.cache` was enabled | Clear the cache as part of the deploy |
| Every model suddenly appears in the schema | `LARAQL_MODELS_AUTO_INCLUDE=true` (or `laraql.models.auto_include`) is set | Disable it and use `#[Model]` on the models you want to expose |
| An unrelated class in `app/GraphQL` breaks schema building | Every file in the scanned directories is reflected; a malformed class or a class that cannot be autoloaded aborts the scan | Keep only valid, autoloadable classes there, or narrow `laraql.directories` |

## Useful one-liners

```bash
# What did Lighthouse actually build?
php artisan lighthouse:print-schema

# LaraQL's cached SDL
php artisan cache:clear

# Lighthouse's compiled AST cache (bootstrap/cache/lighthouse-schema.php)
php artisan lighthouse:clear-schema-cache

# Schema sanity check
php artisan lighthouse:validate-schema

# Introspection-driven checks from tinker
php artisan tinker --execute 'dump(Schema::getColumns((new App\Models\Article)->getTable()));'
```

## Reporting a LaraQL bug

Include the model/attribute definitions, the relevant `lighthouse:print-schema` output, the installed `nodesol/laraql` and `nuwave/lighthouse` versions, and whether `laraql.cache` was enabled when the problem appeared.
