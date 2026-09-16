<?php

use Nodesol\LaraQL\Attributes\Input;
use Workbench\App\GraphQL\ArticleFilterInput;
use Workbench\App\GraphQL\SummaryFilter;
use Workbench\App\GraphQL\UnannotatedFilter;

it('appends Input to the class name by default', function () {
    expect((new Input(class: SummaryFilter::class))->getName())->toBe('SummaryFilterInput')
        ->and((new Input(class: SummaryFilter::class, name: 'FilterInput'))->getName())->toBe('FilterInput');
});

it('generates an input from explicit definitions', function () {
    $schema = (new Input(
        class: ArticleFilterInput::class,
        inputs: ['title' => 'String', 'status' => 'String'],
    ))->getSchema();

    expect($schema)
        ->toContain('input ArticleFilterInput')
        ->toContain('title: String')
        ->toContain('status: String')
        ->not->toContain('limit');
});

it('lets input overrides replace and extend explicit definitions', function () {
    $schema = (new Input(
        class: ArticleFilterInput::class,
        inputs: ['title' => 'String', 'status' => 'String'],
        inputs_override: ['status' => 'ArticleStatus', 'limit' => 'Int = 10'],
    ))->getSchema();

    expect($schema)
        ->toContain('status: ArticleStatus')
        ->toContain('limit: Int = 10')
        ->and(substr_count($schema, 'status:'))->toBe(1);
});

it('generates an input from the class properties', function () {
    $schema = (new Input(class: SummaryFilter::class))->getSchema();

    expect($schema)
        ->toContain('input SummaryFilterInput')
        ->toContain('headline: String!')
        ->toContain('views: Int')
        ->toContain('tags: String!')
        ->toContain('untyped: String!');
});

it('falls back to the class name for non built-in property types', function () {
    $schema = (new Input(class: UnannotatedFilter::class))->getSchema();

    expect($schema)
        ->toContain('input UnannotatedFilterInput')
        ->toContain('headline: String!')
        ->toContain('author: User!');
});
