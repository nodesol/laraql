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

it('caches the generated schema when caching is enabled', function () {
    config()->set('laraql.cache', true);
    Cache::clear();

    $schema = laraqlGeneratedSchema();

    expect($schema)->toContain('type Article')
        ->and(Cache::has('laraql_schema'))->toBeTrue();

    // Once the cache holds a value, that value is what gets served.
    Cache::forever('laraql_schema', 'cached-schema');

    expect(laraqlGeneratedSchema())->toBe('cached-schema');
});

it('does not touch the cache when caching is disabled', function () {
    config()->set('laraql.cache', false);
    Cache::spy();

    expect(laraqlGeneratedSchema())->toContain('type Article');

    Cache::shouldNotHaveReceived('rememberForever');
});
