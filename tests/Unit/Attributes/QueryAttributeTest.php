<?php

use Nodesol\LaraQL\Attributes\Query;
use Workbench\App\GraphQL\ArticleQueries;
use Workbench\App\Models\Article;
use Workbench\App\Models\Tag;

it('derives the query name and return type from the class', function () {
    $query = new Query(class: ArticleQueries::class);

    expect($query->getName())->toBe('article_queries')
        ->and($query->getReturnType())->toBe('ArticleQueries');
});

it('accepts an explicit name and return type', function () {
    $query = new Query(class: Article::class, name: 'articleBySlug', return_type: 'Article');

    expect($query->getName())->toBe('articleBySlug')
        ->and($query->getReturnType())->toBe('Article');
});

it('generates a query with the id filter by default', function () {
    $schema = (new Query(class: Article::class))->getSchema();

    expect($schema)
        ->toContain('extend type Query')
        ->toContain('article (')
        ->toContain('id: ID @eq')
        ->toContain('): Article')
        ->toContain('@find');
});

it('merges the filters argument with the filters override', function () {
    $schema = (new Query(
        class: Article::class,
        filters: ['slug: String! @eq'],
        filters_override: ['status: String @eq'],
    ))->getSchema();

    expect($schema)
        ->toContain('slug: String! @eq')
        ->toContain('status: String @eq')
        ->not->toContain('id: ID @eq');
});

it('generates an empty argument list when no filters are given', function () {
    $schema = (new Query(class: Tag::class, filters: [], filters_override: []))->getSchema();

    expect($schema)
        ->toContain('tag')
        ->toContain('@find')
        ->not->toContain('tag (');
});

it('translates authorize true into a canFind directive', function () {
    expect((new Query(class: Article::class, authorize: true))->getSchema())
        ->toContain('@canFind(ability: "view", find: "id")');
});

it('passes a string authorize through unchanged', function () {
    expect((new Query(class: Article::class, authorize: '@canModel(ability: "viewAny")'))->getSchema())
        ->toContain('@canModel(ability: "viewAny")');
});

it('adds no authorize directive when authorization is off', function () {
    $schema = (new Query(class: Article::class))->getSchema();

    expect($schema)->not->toContain('@can');
});

it('applies directives to the query extension', function () {
    expect((new Query(class: Article::class, directives: ['@guard', '@namespace']))->getSchema())
        ->toContain('extend type Query @guard @namespace');
});

it('uses the configured root directive as the resolver', function () {
    $schema = (new Query(
        class: Article::class,
        query: '@field(resolver: "App\\\\GraphQL\\\\ArticleQueries@findBySlug")',
        return_type: 'Article',
    ))->getSchema();

    expect($schema)->toContain('@field(resolver: "App\\\\GraphQL\\\\ArticleQueries@findBySlug")');
});
