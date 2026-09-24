<?php

use Illuminate\Support\Facades\Cache;
use Nodesol\LaraQL\Listeners\BuildSchemaStringListener;
use Nuwave\Lighthouse\Events\BuildSchemaString;

function laraqlSchemaListener(): BuildSchemaStringListener
{
    return new BuildSchemaStringListener;
}

function laraqlGeneratedSchema(): string
{
    return laraqlSchemaListener()->handle(new BuildSchemaString(''));
}

it('generates the schema of every scanned class', function () {
    $schema = laraqlGeneratedSchema();

    // app/Models
    expect($schema)
        ->toContain('type Article')
        ->toContain('type User')
        ->toContain('type Comment')
        ->toContain('type Tag')
        ->toContain('type Profile')
        ->toContain('type SearchablePost')
        ->toContain('type GuardedArticle')
        ->toContain('type AdminNote')
        // nested directories are resolved to nested namespaces
        ->toContain('type DeepArticle')
        ->toContain('deep_article')
        // app/GraphQL
        ->toContain('type ArticleStats')
        ->toContain('type ArticleSummary')
        ->toContain('input ArticleFilterInput')
        ->toContain('articleBySlug')
        ->toContain('paginatedArticles')
        ->toContain('archiveArticleMutations');
});

it('appends the shared scalars, helper field and directives to the schema', function () {
    $schema = laraqlGeneratedSchema();

    expect($schema)
        ->toContain('scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")')
        ->toContain('scalar DateTime @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\DateTime")')
        ->toContain('scalar Date @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Date")')
        ->toContain('extend type Query {')
        ->toContain('graphql(id: ID):[String!] @find')
        ->toContain('directive @whereConditions(')
        ->toContain('directive @whereHasConditions(');
});

it('skips models without the attribute, abstract models and traits', function () {
    $schema = laraqlGeneratedSchema();

    expect($schema)
        ->not->toContain('type LegacyRecord')
        ->not->toContain('type AbstractRecord')
        ->not->toContain('HasUniqueSlug')
        // Sanity check: the directories were scanned.
        ->toContain('type Article');
});

it('includes unannotated models when auto inclusion is enabled', function () {
    config()->set('laraql.models.auto_include', true);

    $schema = laraqlGeneratedSchema();

    expect($schema)
        ->toContain('type LegacyRecord')
        ->toContain('legacy_record')
        // Abstract models are still skipped.
        ->not->toContain('type AbstractRecord');
});

it('ignores directories that do not exist', function () {
    config()->set('laraql.directories', [
        app_path('Models/DoesNotExist'),
        app_path('Models'),
    ]);

    expect(laraqlGeneratedSchema())->toContain('type Article');
});

it('returns no model schema when no directory is configured', function () {
    config()->set('laraql.directories', []);

    $schema = laraqlGeneratedSchema();

    expect($schema)
        ->not->toContain('type Article')
        ->toContain('scalar Upload');
});

it('caches the generated schema in the configured store when caching is enabled', function () {
    config()->set('laraql.cache', true);
    config()->set('laraql.cache_store', 'array');
    Cache::store('array')->clear();

    $schema = laraqlGeneratedSchema();

    expect($schema)->toContain('type Article')
        ->and(Cache::store('array')->has('laraql_schema'))->toBeTrue();

    // Once the cache holds a value, that value is what gets served.
    Cache::store('array')->forever('laraql_schema', 'cached-schema');

    expect(laraqlGeneratedSchema())->toBe('cached-schema');
});

it('falls back to the default store when no store is configured', function (?string $store) {
    config()->set('laraql.cache', true);
    config()->set('laraql.cache_store', $store);
    Cache::store('array')->clear();

    expect(laraqlGeneratedSchema())->toContain('type Article')
        ->and(Cache::store('array')->has('laraql_schema'))->toBeTrue();
})->with([
    'no store at all' => [null],
    'an empty store' => [''],
]);

it('generates the schema when the configured store does not exist', function () {
    // A misconfigured cache must not take the schema down with it. `Cache::store()` throws
    // "Cache store [] is not defined." for a store that is not configured.
    config()->set('laraql.cache', true);
    config()->set('laraql.cache_store', 'does-not-exist');

    expect(laraqlGeneratedSchema())->toContain('type Article');
});

it('generates the schema when the default store of the application is empty', function () {
    // This is the shape of a "Cache store [] is not defined." failure: no LaraQL store is
    // configured and the application has an empty default store.
    config()->set('laraql.cache', true);
    config()->set('laraql.cache_store', null);
    config()->set('cache.default', '');

    expect(laraqlGeneratedSchema())->toContain('type Article');
});

it('keeps the cache store configuration a string', function () {
    // `cache_store` used to be cast to a boolean, which turned `LARAQL_CACHE_STORE=redis`
    // into a lookup of a store named `1`.
    $config = require dirname(__DIR__, 3).'/config/laraql.php';

    expect($config['cache_store'])->not->toBeBool()
        ->and($config['cache'])->toBeBool();
});

it('does not touch the cache when caching is disabled', function () {
    config()->set('laraql.cache', false);
    Cache::spy();

    expect(laraqlGeneratedSchema())->toContain('type Article');

    Cache::shouldNotHaveReceived('rememberForever');
});

it('maps a scanned file path to the class it holds on any platform', function (string $path, string $appPath, string $expected) {
    $listener = new class extends BuildSchemaStringListener
    {
        public function map(string $path, string $appPath, string $namespace): string
        {
            return self::classNameFromPath($path, $appPath, $namespace);
        }
    };

    expect($listener->map($path, $appPath, 'Workbench\App\\'))->toBe($expected);
})->with([
    'unix' => [
        '/project/workbench/app/Models/Article.php',
        '/project/workbench/app',
        'Workbench\App\Models\Article',
    ],
    'unix with a trailing separator' => [
        '/project/workbench/app/Models/Article.php',
        '/project/workbench/app/',
        'Workbench\App\Models\Article',
    ],
    'unix nested directories' => [
        '/project/workbench/app/GraphQL/ArticleStats.php',
        '/project/workbench/app/',
        'Workbench\App\GraphQL\ArticleStats',
    ],
    'windows' => [
        'C:\project\workbench\app\Models\Article.php',
        'C:\project\workbench\app',
        'Workbench\App\Models\Article',
    ],
    // app_path('/') mixes separators on Windows ("C:\project\workbench\app\/"),
    // which used to leave the absolute path inside the derived class name and made
    // every schema build fail with a ReflectionException.
    'windows with the app_path("/") shape' => [
        'C:\project\workbench\app\Models\Article.php',
        'C:\project\workbench\app\/',
        'Workbench\App\Models\Article',
    ],
    'windows nested directories' => [
        'C:\project\workbench\app\Models\Nested\DeepArticle.php',
        'C:\project\workbench\app',
        'Workbench\App\Models\Nested\DeepArticle',
    ],
    'windows with mixed separators in the file path' => [
        'C:\project\workbench\app\Models/Nested\DeepArticle.php',
        'C:\project\workbench\app\/',
        'Workbench\App\Models\Nested\DeepArticle',
    ],
]);
