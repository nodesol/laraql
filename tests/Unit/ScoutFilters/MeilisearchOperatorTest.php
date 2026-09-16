<?php

use GraphQL\Error\Error;
use Nodesol\LaraQL\ScoutFilters\MeilisearchOperator;
use Nodesol\LaraQL\ScoutFilters\Operator;

it('implements the operator contract', function () {
    expect(new MeilisearchOperator)->toBeInstanceOf(Operator::class);
});

it('describes every meilisearch operator in the enum definition', function () {
    $definition = (new MeilisearchOperator)->enumDefinition();

    expect($definition)
        ->toContain('enum MeilisearchOperator')
        ->toContain('EQ @enum(value: "=")')
        ->toContain('NEQ @enum(value: "!=")')
        ->toContain('GT @enum(value: ">")')
        ->toContain('GTE @enum(value: ">=")')
        ->toContain('LT @enum(value: "<")')
        ->toContain('LTE @enum(value: "<=")')
        ->toContain('IN @enum(value: "IN")')
        ->toContain('NOT_IN @enum(value: "NOT IN")')
        ->toContain('EXISTS @enum(value: "EXISTS")')
        ->toContain('NOT_EXISTS @enum(value: "NOT EXISTS")')
        ->toContain('IS_EMPTY @enum(value: "IS EMPTY")')
        ->toContain('IS_NOT_EMPTY @enum(value: "IS NOT EMPTY")')
        ->toContain('IS_NULL @enum(value: "IS NULL")')
        ->toContain('NOT_NULL @enum(value: "IS NOT NULL")')
        ->toContain('BETWEEN @enum(value: "TO")')
        ->toContain('FUNCTION @enum(value: "FUNC")');
});

it('defaults to the equality operator', function () {
    expect((new MeilisearchOperator)->default())->toBe('EQ');
});

it('turns conditions into a meilisearch filter expression', function (array $condition, string $expected) {
    expect((new MeilisearchOperator)->applyConditions($condition))->toBe($expected);
})->with([
    'equality' => [['column' => 'title', 'operator' => '=', 'value' => 'foo'], 'title = foo'],
    'not equal' => [['column' => 'title', 'operator' => '!=', 'value' => 'foo'], 'title != foo'],
    'greater than' => [['column' => 'views', 'operator' => '>', 'value' => '10'], 'views > 10'],
    'greater than or equal' => [['column' => 'views', 'operator' => '>=', 'value' => '10'], 'views >= 10'],
    'less than' => [['column' => 'views', 'operator' => '<', 'value' => '10'], 'views < 10'],
    'less than or equal' => [['column' => 'views', 'operator' => '<=', 'value' => '10'], 'views <= 10'],
    'exists' => [['column' => 'title', 'operator' => 'EXISTS', 'value' => ''], 'title EXISTS'],
    'not exists' => [['column' => 'title', 'operator' => 'NOT EXISTS', 'value' => ''], 'title NOT EXISTS'],
    'is empty' => [['column' => 'title', 'operator' => 'IS EMPTY', 'value' => ''], 'title IS EMPTY'],
    'is not empty' => [['column' => 'title', 'operator' => 'IS NOT EMPTY', 'value' => ''], 'title IS NOT EMPTY'],
    'is null' => [['column' => 'title', 'operator' => 'IS NULL', 'value' => ''], 'title IS NULL'],
    'is not null' => [['column' => 'title', 'operator' => 'IS NOT NULL', 'value' => ''], 'title IS NOT NULL'],
    'in' => [['column' => 'status', 'operator' => 'IN', 'value' => 'draft,published'], 'status IN [draft,published]'],
    'not in' => [['column' => 'status', 'operator' => 'NOT IN', 'value' => 'draft'], 'status NOT IN [draft]'],
    'between' => [['column' => 'views', 'operator' => 'TO', 'value' => '1,5'], 'views 1 TO 5'],
    'function' => [['column' => 'location', 'operator' => 'FUNC', 'value' => '_geoRadius(1, 2, 3)'], '_geoRadius(1, 2, 3)'],
]);

it('reports a missing value for a column', function () {
    $error = MeilisearchOperator::missingValueForColumn('title');

    expect($error)->toBeInstanceOf(Error::class)
        ->and($error->getMessage())->toBe('Did not receive a value to match the ScoutFilters for column title.');
});
