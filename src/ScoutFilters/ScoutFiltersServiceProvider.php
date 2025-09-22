<?php

declare(strict_types=1);

namespace Nodesol\LaraQL\ScoutFilters;

use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\ServiceProvider;
use MLL\GraphQLScalars\MixedScalar;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class ScoutFiltersServiceProvider extends ServiceProvider
{
    public const DEFAULT_SCOUT_FILTERS = 'ScoutFilters';

    public function register(): void
    {
        $this->app->bind(Operator::class, match (config('scout.driver', 'meilisearch')) {
            'meilisearch' => MeilisearchOperator::class,
            default => MeilisearchOperator::class,
        });
    }

    public function boot(EventsDispatcher $dispatcher): void
    {
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): string => __NAMESPACE__);
        $dispatcher->listen(ManipulateAST::class, function (ManipulateAST $manipulateAST): void {
            $operator = $this->app->make(Operator::class);

            $documentAST = $manipulateAST->documentAST;
            $documentAST->setTypeDefinition(
                static::createScoutFiltersInputType(
                    static::DEFAULT_SCOUT_FILTERS,
                    'Dynamic Scout Filters for queries.',
                    'String',
                ),
            );
            $documentAST->setTypeDefinition(
                Parser::enumTypeDefinition(
                    $operator->enumDefinition(),
                ),
            );
            $mixedScalarClass = addslashes(MixedScalar::class);
            $documentAST->setTypeDefinition(
                Parser::scalarTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
                    scalar Mixed @scalar(class: "{$mixedScalarClass}")
                GRAPHQL),
            );
        });
    }

    public static function createScoutFiltersInputType(string $name, string $description, string $columnType): InputObjectTypeDefinitionNode
    {

        $operator = Container::getInstance()->make(Operator::class);

        $operatorName = Parser::enumTypeDefinition(
            $operator->enumDefinition(),
        )
            ->name
            ->value;
        $operatorDefault = $operator->default();

        return Parser::inputObjectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "{$description}"
            input {$name} {
                "The column that is used for the condition."
                column: {$columnType}

                "The operator that is used for the condition."
                operator: {$operatorName} = {$operatorDefault}

                "The value that is used for the condition."
                value: Mixed

                "A set of conditions that requires all conditions to match."
                AND: [{$name}!]

                "A set of conditions that requires at least one condition to match."
                OR: [{$name}!]
            }
GRAPHQL
        );
    }
}
