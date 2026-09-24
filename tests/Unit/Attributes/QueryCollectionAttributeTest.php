<?php

use Nodesol\LaraQL\Attributes\QueryCollection;
use Workbench\App\GraphQL\ArticleCollections;
use Workbench\App\Models\AdminNote;
use Workbench\App\Models\Article;
use Workbench\App\Models\CollidingRelations;
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

it('gives every orderable relation of a model its own order by argument', function () {
    $schema = (new QueryCollection(class: Article::class))->getSchema();

    expect($schema)
        // The default argument is untouched, so clients keep the shared OrderByClause type.
        ->toContain('orderBy: _ @orderBy')
        ->not->toContain('orderBy: _ @orderBy(relations:')
        // Every orderable relation is offered on its own argument.
        ->toContain('orderByUser: _ @orderBy(relations: [{ relation: "user", columns: ["id","name","email","created_at","updated_at"] }])')
        ->toContain('orderByComments: _ @orderBy(relations: [{ relation: "comments", columns: [')
        ->toContain('orderByTags: _ @orderBy(relations: [{ relation: "tags", columns: [')
        ->toContain('orderByProfile: _ @orderBy(relations: [{ relation: "profile", columns: [')
        ->toContain('orderByMorphComments: _ @orderBy(relations: [{ relation: "morphComments", columns: [')
        ->toContain('orderByUserComments: _ @orderBy(relations: [{ relation: "userComments", columns: [')
        ->toContain('orderByUserProfile: _ @orderBy(relations: [{ relation: "userProfile", columns: [')
        ->not->toContain('orderByBrokenRelation');
});

it('adds no relation order by arguments when they are turned off', function () {
    $schema = (new QueryCollection(class: Article::class, order_by_relations: false))->getSchema();

    expect($schema)
        ->toContain('orderBy: _ @orderBy')
        ->not->toContain('@orderBy(relations:');
});

it('adds no relation order by arguments without an order by argument', function () {
    $schema = (new QueryCollection(class: Article::class, filters: ['first: Int! = 10']))->getSchema();

    expect($schema)
        ->toContain('first: Int! = 10')
        ->not->toContain('@orderBy');
});

it('keeps hidden related columns out of orderable relation columns', function () {
    $schema = (new QueryCollection(class: Tag::class))->getSchema();

    expect($schema)
        ->toContain('orderByArticles: _ @orderBy(relations: [{ relation: "articles", columns: [')
        ->not->toContain('internal_notes');
});

it('does not add morph-to relations to the order arguments', function () {
    $schema = (new QueryCollection(class: Comment::class))->getSchema();

    expect($schema)
        ->toContain('orderByArticle: _ @orderBy(relations: [{ relation: "article", columns: [')
        ->toContain('orderByUser: _ @orderBy(relations: [{ relation: "user", columns: [')
        ->not->toContain('relation: "commentable"')
        ->not->toContain('orderByCommentable');
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

it('leaves a customized order filter unchanged and still adds the relation arguments', function () {
    $schema = (new QueryCollection(
        class: Article::class,
        filters: ['orderBy: _ @orderBy(columns: ["title"])'],
    ))->getSchema();

    expect($schema)
        ->toContain('orderBy: _ @orderBy(columns: ["title"])')
        ->toContain('orderByComments: _ @orderBy(relations: [{ relation: "comments", columns: [');
});

it('keeps the first relation when two methods map to the same order by argument', function () {
    $schema = (new QueryCollection(class: CollidingRelations::class))->getSchema();

    // `adminNote()` and `admin_note()` both become `orderByAdminNote`.
    expect(substr_count($schema, 'orderByAdminNote: _ @orderBy'))->toBe(1)
        ->and($schema)->toContain('orderByAdminNote: _ @orderBy(relations: [{ relation: "');
});

it('offers a relation without a column list when every column is hidden', function () {
    $schema = (new QueryCollection(class: Article::class))->getSchema();

    // `Article::redacted()` points at a model that hides all of its columns, so the
    // relation is offered without a column list.
    expect($schema)->toContain('orderByRedacted: _ @orderBy(relations: [{ relation: "redacted" }])');
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
