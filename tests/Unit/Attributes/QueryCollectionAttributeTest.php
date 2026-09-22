<?php

use Nodesol\LaraQL\Attributes\QueryCollection;
use Workbench\App\GraphQL\ArticleCollections;
use Workbench\App\Models\AdminNote;
use Workbench\App\Models\Article;
use Workbench\App\Models\Comment;
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

it('adds orderable relation columns for eloquent models', function () {
    $schema = (new QueryCollection(class: Article::class))->getSchema();

    expect($schema)
        ->toContain('orderBy: _ @orderBy(relations: [')
        ->toContain('relation: "user"')
        ->toContain('columns: ["id","name","email","created_at","updated_at"]')
        ->toContain('relation: "comments"')
        ->toContain('relation: "tags"')
        ->toContain('relation: "profile"')
        ->toContain('relation: "morphComments"')
        ->toContain('relation: "userComments"')
        ->toContain('relation: "userProfile"')
        ->not->toContain('relation: "brokenRelation"');
});

it('keeps hidden related columns out of orderable relation columns', function () {
    $schema = (new QueryCollection(class: Tag::class))->getSchema();

    expect($schema)
        ->toContain('relation: "articles"')
        ->not->toContain('internal_notes');
});

it('does not add morph-to relations to the order filter', function () {
    $schema = (new QueryCollection(class: Comment::class))->getSchema();

    expect($schema)
        ->toContain('relation: "article"')
        ->toContain('relation: "user"')
        ->not->toContain('relation: "commentable"');
});

it('keeps the default order filter for collections that are not eloquent models', function () {
    $schema = (new QueryCollection(class: ArticleCollections::class))->getSchema();

    expect($schema)
        ->toContain('orderBy: _ @orderBy')
        ->not->toContain('@orderBy(relations:');
});

it('keeps the default order filter for models without relations', function () {
    $schema = (new QueryCollection(class: AdminNote::class))->getSchema();

    expect($schema)
        ->toContain('orderBy: _ @orderBy')
        ->not->toContain('@orderBy(relations:');
});

it('leaves a customized order filter unchanged', function () {
    $schema = (new QueryCollection(
        class: Article::class,
        filters: ['orderBy: _ @orderBy(columns: ["title"])'],
    ))->getSchema();

    expect($schema)
        ->toContain('orderBy: _ @orderBy(columns: ["title"])')
        ->not->toContain('@orderBy(relations:');
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
