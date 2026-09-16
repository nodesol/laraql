<?php

use Nodesol\LaraQL\Attributes\QueryCollection;
use Workbench\App\Models\Article;
use Workbench\App\Models\Tag;

it('derives a plural snake case name and a list return type from the class', function () {
    $collection = new QueryCollection(class: Tag::class);

    expect($collection->getName())->toBe('tags')
        ->and($collection->getReturnType())->toBe('[Tag!]!');
});

it('accepts an explicit name and return type', function () {
    $collection = new QueryCollection(class: Article::class, name: 'publishedArticles', return_type: '[Article!]!');

    expect($collection->getName())->toBe('publishedArticles')
        ->and($collection->getReturnType())->toBe('[Article!]!');
});

it('generates the default collection arguments and paginates', function () {
    $schema = (new QueryCollection(class: Tag::class))->getSchema();

    expect($schema)
        ->toContain('extend type Query')
        ->toContain('tags')
        ->toContain('where: _ @whereConditions(column: {})')
        ->toContain('first: Int! = 10')
        ->toContain('page: Int')
        ->toContain('orderBy: _ @orderBy')
        ->toContain('): [Tag!]!')
        ->toContain('@paginate(defaultCount: 10)');
});

it('merges extra filters into the default ones', function () {
    $schema = (new QueryCollection(
        class: Article::class,
        filters_override: ['status: String @eq', 'minViews: Int @where(operator: ">=", key: "views")'],
        query: '@paginate(defaultCount: 5, maxCount: 20)',
    ))->getSchema();

    expect($schema)
        ->toContain('where: _ @whereConditions(column: {})')
        ->toContain('status: String @eq')
        ->toContain('minViews: Int @where(operator: ">=", key: "views")')
        ->toContain('@paginate(defaultCount: 5, maxCount: 20)');
});

it('generates an empty argument list when no filters are given', function () {
    $schema = (new QueryCollection(class: Tag::class, filters: [], filters_override: []))->getSchema();

    expect($schema)
        ->toContain('tags')
        ->toContain(': [Tag!]!')
        ->not->toContain('tags (');
});

it('translates authorize true into a canModel directive', function () {
    expect((new QueryCollection(class: Article::class, authorize: true))->getSchema())
        ->toContain('@canModel(ability: "viewAny")');
});

it('passes a string authorize through unchanged', function () {
    expect((new QueryCollection(class: Article::class, authorize: '@canModel(ability: "viewAny")'))->getSchema())
        ->toContain('@canModel(ability: "viewAny")');
});

it('adds no authorize directive when authorization is off', function () {
    expect((new QueryCollection(class: Article::class))->getSchema())->not->toContain('@can');
});

it('applies directives to the query extension', function () {
    expect((new QueryCollection(class: Article::class, directives: ['@guard']))->getSchema())
        ->toContain('extend type Query @guard');
});
