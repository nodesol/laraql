<?php

use GraphQL\Language\Printer;
use Nodesol\LaraQL\ScoutFilters\MeilisearchOperator;
use Nodesol\LaraQL\ScoutFilters\Operator;
use Nodesol\LaraQL\ScoutFilters\ScoutFiltersDirective;
use Nodesol\LaraQL\ScoutFilters\ScoutFiltersServiceProvider;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\DirectiveLocator;

it('binds the meilisearch operator to the operator contract', function () {
    expect(app(Operator::class))->toBeInstanceOf(MeilisearchOperator::class);
});

it('binds the meilisearch operator for any configured driver', function () {
    config()->set('scout.driver', 'algolia');

    expect(app()->make(Operator::class))->toBeInstanceOf(MeilisearchOperator::class);
});

it('creates the input type that holds a search term and conditions', function () {
    $definition = Printer::doPrint(
        ScoutFiltersServiceProvider::createScoutFiltersInputType('ScoutFilters', 'Dynamic Scout Filters.', 'String'),
    );

    expect($definition)
        ->toContain('input ScoutFilters')
        ->toContain('search: String')
        ->toContain('filters: ScoutFiltersCondition');
});

it('creates the condition input type', function () {
    $definition = Printer::doPrint(
        ScoutFiltersServiceProvider::createScoutFiltersConditionInputType('ScoutFilters', 'Dynamic Scout Filters.', 'QueryArticlesScoutSearchColumn'),
    );

    expect($definition)
        ->toContain('input ScoutFiltersCondition')
        ->toContain('column: QueryArticlesScoutSearchColumn')
        ->toContain('operator: MeilisearchOperator = EQ')
        ->toContain('value: Mixed')
        ->toContain('AND: [ScoutFiltersCondition!]')
        ->toContain('OR: [ScoutFiltersCondition!]');
});

it('injects the scout filters types into the schema', function () {
    $documentAST = app(ASTBuilder::class)->documentAST();

    expect($documentAST->types)
        ->toHaveKey('ScoutFilters')
        ->toHaveKey('ScoutFiltersCondition')
        ->toHaveKey('MeilisearchOperator')
        ->toHaveKey('Mixed');
});

it('registers the directive namespace so @scoutFilters resolves', function () {
    expect(DirectiveLocator::class)
        ->toBeString()
        ->and(app(DirectiveLocator::class)->create('scoutFilters'))
        ->toBeInstanceOf(ScoutFiltersDirective::class);
});
