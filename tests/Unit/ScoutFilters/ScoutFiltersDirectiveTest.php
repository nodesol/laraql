<?php

use GraphQL\Error\Error;
use GraphQL\Language\Parser;
use Nodesol\LaraQL\ScoutFilters\ScoutFiltersDirective;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Workbench\App\GraphQL\CustomScoutHandler;
use Workbench\App\Models\SearchablePost;
use Workbench\App\Scout\FakeScout;

/**
 * Build a directive instance the way Lighthouse does: with the AST node it is
 * attached to and the node it is defined on.
 */
function scoutFiltersDirective(string $arguments = '', string $fieldSource = 'articles(scoutSearch: _ @scoutFilters%s): [Article!]!'): ScoutFiltersDirective
{
    $field = Parser::fieldDefinition(sprintf($fieldSource, $arguments));

    $directive = new ScoutFiltersDirective;
    $directive->directiveNode = $field->arguments[0]->directives[0];
    $directive->definitionNode = $field;

    return $directive;
}

beforeEach(function () {
    FakeScout::reset();
    CustomScoutHandler::$calls = 0;
});

it('defines the scout filters directive', function () {
    expect(ScoutFiltersDirective::definition())
        ->toContain('directive @scoutFilters(')
        ->toContain('columns: [String!]')
        ->toContain('columnsEnum: String')
        ->toContain('handler: String = "\\\\Nodesol\\\\LaraQL\\\\ScoutFilters\\\\ScoutFiltersHandler"')
        ->toContain('on ARGUMENT_DEFINITION');
});

it('suffixes generated input types with ScoutFilters', function () {
    $method = new ReflectionMethod(ScoutFiltersDirective::class, 'generatedInputSuffix');
    $method->setAccessible(true);

    expect($method->invoke(scoutFiltersDirective()))->toBe('ScoutFilters');
});

it('returns the builder untouched when the value is null', function () {
    $builder = SearchablePost::query();

    expect(scoutFiltersDirective()->handleBuilder($builder, null))->toBe($builder)
        ->and(FakeScout::$searches)->toBeEmpty();
});

it('applies the value through the default handler', function () {
    FakeScout::willReturn([['id' => 7]]);

    $builder = scoutFiltersDirective()->handleBuilder(SearchablePost::query(), ['search' => 'term']);

    expect(FakeScout::lastSearch(SearchablePost::class)['query'])->toBe('term')
        ->and($builder->getQuery()->wheres)->not->toBeEmpty();
});

it('applies the value through a configured handler', function () {
    FakeScout::willReturn([['id' => 7]]);

    scoutFiltersDirective('(handler: "Workbench\\\\App\\\\GraphQL\\\\CustomScoutHandler")')
        ->handleBuilder(SearchablePost::query(), ['search' => 'term']);

    expect(CustomScoutHandler::$calls)->toBe(1);
});

it('uses the generic input type when no columns are restricted', function () {
    $documentAST = DocumentAST::fromSource(/** @lang GraphQL */ '
        type Query {
            articles(scoutSearch: _ @scoutFilters): [Article!]!
        }
    ');

    $parentType = $documentAST->types['Query'];
    $field = $parentType->fields[0];
    $argDefinition = $field->arguments[0];

    $directive = new ScoutFiltersDirective;
    $directive->directiveNode = $argDefinition->directives[0];
    $directive->definitionNode = $field;

    $directive->manipulateArgDefinition($documentAST, $argDefinition, $field, $parentType);

    expect($argDefinition->type->name->value)->toBe('ScoutFilters')
        ->and($documentAST->types)->not->toHaveKey('QueryArticlesScoutSearchScoutFilters');
});

it('generates a restricted input type when columns are restricted', function () {
    $documentAST = DocumentAST::fromSource(/** @lang GraphQL */ '
        type Query {
            articles(scoutSearch: _ @scoutFilters(columns: ["title", "created_at"])): [Article!]!
        }
    ');

    $parentType = $documentAST->types['Query'];
    $field = $parentType->fields[0];
    $argDefinition = $field->arguments[0];

    $directive = new ScoutFiltersDirective;
    $directive->directiveNode = $argDefinition->directives[0];
    $directive->definitionNode = $field;

    $directive->manipulateArgDefinition($documentAST, $argDefinition, $field, $parentType);

    expect($argDefinition->type->name->value)->toBe('QueryArticlesScoutSearchScoutFilters')
        ->and($documentAST->types)->toHaveKey('QueryArticlesScoutSearchScoutFilters')
        ->and($documentAST->types)->toHaveKey('QueryArticlesScoutSearchScoutFiltersCondition')
        ->and($documentAST->types)->toHaveKey('QueryArticlesScoutSearchColumn');
});

it('rejects columns and columnsEnum at the same time', function () {
    $documentAST = DocumentAST::fromSource(/** @lang GraphQL */ '
        type Query {
            articles(scoutSearch: _ @scoutFilters(columns: ["title"], columnsEnum: "ArticleStatus")): [Article!]!
        }
    ');

    $parentType = $documentAST->types['Query'];
    $field = $parentType->fields[0];
    $argDefinition = $field->arguments[0];

    $directive = new ScoutFiltersDirective;
    $directive->directiveNode = $argDefinition->directives[0];
    $directive->definitionNode = $field;

    expect(fn () => $directive->manipulateArgDefinition($documentAST, $argDefinition, $field, $parentType))
        ->toThrow(DefinitionException::class, 'The arguments [columns, columnsEnum] for @scoutFilters are mutually exclusive, found [columns, columnsEnum] on articles.');
});

it('rejects an invalid column name while filtering', function () {
    expect(fn () => scoutFiltersDirective()->handleBuilder(SearchablePost::query(), [
        'filters' => ['column' => '1; drop table users', 'operator' => '=', 'value' => 'x'],
    ]))->toThrow(Error::class);
});
