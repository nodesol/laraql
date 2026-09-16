<?php

use Nodesol\LaraQL\Attributes\Model as ModelAttribute;
use Nodesol\LaraQL\Attributes\Mutation;
use Nodesol\LaraQL\Attributes\Query;
use Nodesol\LaraQL\Attributes\QueryCollection;
use Workbench\App\Models\AdminNote;
use Workbench\App\Models\Article;
use Workbench\App\Models\Comment;
use Workbench\App\Models\GuardedArticle;
use Workbench\App\Models\LegacyRecord;
use Workbench\App\Models\User;

it('generates the type from the table columns', function () {
    $schema = (new ModelAttribute(class: User::class))->getTypeSchema();

    // Relation fields are derived from the declared return type of the relation method.
    expect($schema)
        ->toContain('type User')
        ->toContain('id: ID!')
        ->toContain('name: String!')
        ->toContain('email: String!')
        ->toContain('created_at: DateTime')
        ->toContain('articles: [Article] @hasMany')
        ->toContain('comments: [Comment] @hasMany');
});

it('maps every column of the article table to its graphql type', function () {
    $schema = (new ModelAttribute(class: Article::class))->getTypeSchema();

    expect($schema)
        ->toContain('id: ID!')
        ->toContain('user_id: Int')
        ->toContain('title: String!')
        ->toContain('body: String')
        ->toContain('meta: String')
        ->toContain('is_active: Boolean!')
        ->toContain('views: Int!')
        ->toContain('published_at: DateTime')
        ->toContain('created_at: DateTime')
        // SQLite reports decimal columns as "numeric", which falls back to String.
        ->toContain('price: String');
});

it('hides columns listed in the hidden property', function () {
    $schema = (new ModelAttribute(class: Article::class))->getTypeSchema();

    expect($schema)->not->toContain('internal_notes');
});

it('generates relation fields for every relation kind', function () {
    $schema = (new ModelAttribute(class: Article::class))->getTypeSchema();

    expect($schema)
        ->toContain('user: User @belongsTo')
        ->toContain('comments: [Comment] @hasMany')
        ->toContain('tags: [Tag] @belongsToMany')
        ->toContain('profile: Profile @hasOne')
        ->toContain('morphComments: [Comment] @morphMany')
        ->toContain('userComments: [Comment] @hasManyThrough')
        ->toContain('userProfile: Profile @hasOneThrough');
});

it('generates morphTo fields from the model that declares them', function () {
    // A MorphTo relation cannot know its related model before it is loaded, so LaraQL
    // falls back to the model that declares the relation.
    expect((new ModelAttribute(class: Comment::class))->getTypeSchema())
        ->toContain('commentable: Comment @morphTo');
});

it('skips methods that are not relations', function () {
    $schema = (new ModelAttribute(class: Article::class))->getTypeSchema();

    expect($schema)
        ->not->toContain('titleLength')
        ->not->toContain('commentsForUser')
        ->not->toContain('messages')
        ->not->toContain('brokenRelation');
});

it('applies type overrides on top of the detected columns', function () {
    $schema = (new ModelAttribute(
        class: Article::class,
        type_override: [
            'views' => 'Int! @deprecated(reason: "Use viewCount.")',
            'status_label' => 'String! @method(name: "statusLabel")',
        ],
    ))->getTypeSchema();

    expect($schema)
        ->toContain('views: Int! @deprecated(reason: "Use viewCount.")')
        ->toContain('status_label: String! @method(name: "statusLabel")');
});

it('builds the input from the fillable columns only', function () {
    $schema = (new ModelAttribute(class: Article::class))->getInputSchema();

    expect($schema)
        ->toContain('input ArticleInput')
        ->toContain('title: String!')
        ->toContain('slug: String!')
        ->toContain('body: String')
        ->toContain('user_id: Int')
        ->toContain('is_active: Boolean!')
        ->not->toContain('internal_notes')
        ->not->toContain('created_at');
});

it('applies input overrides on top of the fillable columns', function () {
    $schema = (new ModelAttribute(
        class: Article::class,
        input_override: [
            'title' => 'String! @rules(apply: ["required", "max:200"])',
            'published_at' => 'DateTime',
        ],
    ))->getInputSchema();

    expect($schema)
        ->toContain('title: String! @rules(apply: ["required", "max:200"])')
        ->toContain('published_at: DateTime');
});

it('generates the default crud operations', function () {
    $schema = (new ModelAttribute(class: Article::class))->getOperationSchema();

    expect($schema)
        ->toContain('article')
        ->toContain('id: ID @eq')
        ->toContain('): Article')
        ->toContain('@find')
        ->toContain('articles')
        ->toContain('where: _ @whereConditions(column: {})')
        ->toContain('first: Int! = 10')
        ->toContain('page: Int')
        ->toContain('orderBy: _ @orderBy')
        ->toContain('@paginate(defaultCount: 10)')
        ->toContain('createArticle')
        ->toContain('input: ArticleInput! @spread')
        ->toContain('@create')
        ->toContain('updateArticle')
        ->toContain('@update')
        ->toContain('deleteArticle')
        ->toContain('id: ID! @whereKey')
        ->toContain('@delete');
});

it('returns every operation as an operation object', function () {
    $operations = (new ModelAttribute(class: Article::class))->getOperations();

    expect($operations)->toHaveCount(5)
        ->and($operations[0])->toBeInstanceOf(Query::class)
        ->and($operations[1])->toBeInstanceOf(QueryCollection::class)
        ->and($operations[2])->toBeInstanceOf(Mutation::class)
        ->and($operations[2]->getName())->toBe('create')
        ->and($operations[3]->getName())->toBe('update')
        ->and($operations[4]->getName())->toBe('delete');
});

it('joins the type, operations and input into a single schema string', function () {
    $schema = (new ModelAttribute(class: Article::class))->getSchema();

    expect($schema)
        ->toContain('type Article')
        ->toContain('extend type Query')
        ->toContain('extend type Mutation')
        ->toContain('input ArticleInput');
});

it('passes operation arguments to the generated operation attributes', function () {
    $operations = (new ModelAttribute(
        class: Article::class,
        operations: [
            'query_collection' => [
                'name' => 'publishedArticles',
                'filters_override' => ['status: String @eq'],
                'query' => '@paginate(defaultCount: 25)',
            ],
        ],
    ))->getOperations();

    expect($operations[1]->getSchema())
        ->toContain('publishedArticles')
        ->toContain('status: String @eq')
        ->toContain('@paginate(defaultCount: 25)');
});

it('merges model directives with the directives of a single operation', function () {
    $operations = (new ModelAttribute(
        class: GuardedArticle::class,
        directives: ['@guard'],
        operations: ['delete' => ['directives' => ['@canModel(ability: "delete")']]],
    ))->getOperations();

    expect($operations[0]->getSchema())->toContain('extend type Query @guard');
    expect($operations[1]->getSchema())->toContain('extend type Query @guard');
    expect($operations[4]->getSchema())->toContain('@guard @canModel(ability: "delete")');
});

it('applies model directives to every generated operation', function () {
    $schema = (new ModelAttribute(class: AdminNote::class, directives: ['@guard']))->getOperationSchema();

    expect(substr_count($schema, '@guard'))->toBe(5)
        ->and($schema)->toContain('extend type Query @guard')
        ->and($schema)->toContain('extend type Mutation @guard');
});

it('translates authorize true into policy directives for every operation', function () {
    $schema = (new ModelAttribute(class: GuardedArticle::class, authorize: true))->getOperationSchema();

    expect($schema)
        ->toContain('@canFind(ability: "view", find: "id")')
        ->toContain('@canModel(ability: "viewAny")')
        ->toContain('@canModel(ability: "create")')
        ->toContain('@canFind(ability: "update", find: "id")')
        ->toContain('@canFind(ability: "delete", find: "id")');
});

it('lets a single operation override the model level authorize argument', function () {
    $operations = (new ModelAttribute(
        class: GuardedArticle::class,
        authorize: true,
        operations: ['query' => ['authorize' => '@canModel(ability: "viewAny")']],
    ))->getOperations();

    expect($operations[0]->getSchema())
        ->toContain('@canModel(ability: "viewAny") @find')
        ->not->toContain('@canFind');

    // Every other operation keeps the model level behaviour.
    expect($operations[4]->getSchema())->toContain('@canFind(ability: "delete", find: "id")');
});

it('accepts models that are not exposed through an attribute', function () {
    // LegacyRecord is a plain Eloquent model, but the attribute itself can still be built for it.
    expect(fn () => new ModelAttribute(class: LegacyRecord::class))->not->toThrow(Throwable::class);
});
