<?php

use Nodesol\LaraQL\Attributes\Type;
use Workbench\App\GraphQL\ArticleStats;
use Workbench\App\GraphQL\ArticleSummary;

it('uses the class name as the type name by default', function () {
    expect((new Type(class: ArticleSummary::class))->getName())->toBe('ArticleSummary')
        ->and((new Type(class: ArticleStats::class, name: 'Stats'))->getName())->toBe('Stats');
});

it('generates a type from explicit columns', function () {
    $schema = (new Type(class: ArticleStats::class, columns: ['id' => 'ID!', 'title' => 'String!']))->getSchema();

    expect($schema)
        ->toContain('type ArticleStats')
        ->toContain('id: ID!')
        ->toContain('title: String!')
        ->not->toContain('Paginator');
});

it('lets column overrides replace and extend explicit columns', function () {
    $schema = (new Type(
        class: ArticleStats::class,
        columns: ['id' => 'ID!', 'title' => 'String!'],
        columns_override: [
            'title' => 'String! @deprecated(reason: "Use headline instead.")',
            'views' => 'Int!',
        ],
    ))->getSchema();

    expect($schema)
        ->toContain('title: String! @deprecated(reason: "Use headline instead.")')
        ->toContain('views: Int!')
        ->and(substr_count($schema, 'title:'))->toBe(1);
});

it('generates a type from the class properties', function () {
    $schema = (new Type(class: ArticleSummary::class))->getSchema();

    expect($schema)
        ->toContain('type ArticleSummary')
        ->toContain('headline: String!')
        ->toContain('views: Int')
        ->toContain('tags: String!')
        ->toContain('author: User!')
        ->toContain('untyped: String!');
});

it('generates a paginator for a type when asked', function () {
    $paginator = (new Type(class: ArticleStats::class, create_paginator: true))->getPaginatorSchema();

    expect($paginator)
        ->toContain('type ArticleStatsPaginator')
        ->toContain('paginatorInfo: PaginatorInfo!')
        ->toContain('data: [ArticleStats!]!')
        ->toContain('@field(resolver:');
});

it('returns an empty paginator schema unless it is requested', function () {
    expect((new Type(class: ArticleStats::class))->getPaginatorSchema())->toBe('');
});

it('renders the extends argument as given', function () {
    // NOTE: this documents current behaviour. `type X extends Y` is not valid GraphQL,
    // so the rendered schema must be checked with `lighthouse:validate-schema` before use.
    $schema = (new Type(class: ArticleSummary::class, name: 'ExtendedSummary', extends: 'SearchHit'))->getSchema();

    expect($schema)->toContain('type ExtendedSummary')
        ->and($schema)->toContain('extends SearchHit');
});
