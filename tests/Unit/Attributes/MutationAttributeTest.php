<?php

use Nodesol\LaraQL\Attributes\Mutation;
use Nodesol\LaraQL\Attributes\Operation;
use Workbench\App\Models\Article;

it('implements the operation contract', function () {
    expect(new Mutation(class: Article::class, name: 'create'))->toBeInstanceOf(Operation::class);
});

it('generates the create mutation with a spread input', function () {
    $schema = (new Mutation(class: Article::class, name: 'create'))->getSchema();

    expect($schema)
        ->toContain('extend type Mutation')
        ->toContain('createArticle')
        ->toContain('input: ArticleInput! @spread')
        ->toContain('): Article')
        ->toContain('@create');
});

it('generates the update mutation with an id and a spread input', function () {
    $schema = (new Mutation(class: Article::class, name: 'update'))->getSchema();

    expect($schema)
        ->toContain('updateArticle')
        ->toContain('id: ID!')
        ->toContain('input: ArticleInput! @spread')
        ->toContain('@update');
});

it('generates the delete mutation with a where key argument', function () {
    $schema = (new Mutation(class: Article::class, name: 'delete'))->getSchema();

    expect($schema)
        ->toContain('deleteArticle')
        ->toContain('id: ID! @whereKey')
        ->toContain('@delete');
});

it('falls back to a plain id argument for other mutation names', function () {
    $schema = (new Mutation(class: Article::class, name: 'publish'))->getSchema();

    expect($schema)
        ->toContain('publishArticle')
        ->toContain('id: ID!')
        ->toContain('@publish')
        ->not->toContain('@spread');
});

it('accepts explicit inputs, return type and resolver', function () {
    $schema = (new Mutation(
        class: Article::class,
        name: 'archive',
        return_type: 'Article',
        inputs: ['id' => 'ID!', 'status' => 'String!'],
        query: '@update',
    ))->getSchema();

    expect($schema)
        ->toContain('archiveArticle')
        ->toContain('id: ID!')
        ->toContain('status: String!')
        ->toContain('@update');
});

it('generates an empty argument list when the inputs are empty', function () {
    $schema = (new Mutation(class: Article::class, name: 'ping', inputs: [], query: '@find'))->getSchema();

    expect($schema)
        ->toContain('pingArticle')
        ->toContain('@find')
        ->not->toContain('pingArticle (');
});

it('translates authorize true into a canModel directive for create', function () {
    expect((new Mutation(class: Article::class, name: 'create', authorize: true))->getSchema())
        ->toContain('@canModel(ability: "create")');
});

it('translates authorize true into a canFind directive for other names', function () {
    expect((new Mutation(class: Article::class, name: 'delete', authorize: true))->getSchema())
        ->toContain('@canFind(ability: "delete", find: "id")');
});

it('passes a string authorize through unchanged', function () {
    expect((new Mutation(class: Article::class, name: 'publish', authorize: '@canModel(ability: "publish")'))->getSchema())
        ->toContain('@canModel(ability: "publish")');
});

it('adds no authorize directive when authorization is off', function () {
    expect((new Mutation(class: Article::class, name: 'create'))->getSchema())->not->toContain('@can');
});

it('applies directives to the mutation extension', function () {
    expect((new Mutation(class: Article::class, name: 'create', directives: ['@guard', '@namespace']))->getSchema())
        ->toContain('extend type Mutation @guard @namespace');
});
