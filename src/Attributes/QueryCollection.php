<?php

namespace Nodesol\LaraQL\Attributes;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class QueryCollection implements Operation
{
    private \ReflectionClass $reflector;

    public function __construct(
        public string $class,
        public ?string $name = null,
        public ?string $return_type = null,
        public ?array $directives = [],
        public ?array $filters = ['where: _ @whereConditions(column: {})', 'first: Int! = 10', 'page: Int', 'orderBy: _ @orderBy'],
        public ?array $filters_override = [],
        public ?string $query = '@paginate(defaultCount: 10)',
        public bool|string|null $authorize = null,
        /**
         * Offer every orderable relation of the model on its own `orderBy<Relation>`
         * argument, e.g. `orderByTenant` for a `tenant()` relation.
         *
         * Lighthouse answers the `relations` argument of `@orderBy` with a clause type
         * that is generated per field (e.g. `QueryUsersOrderByTenantRelationOrderByClause`),
         * so the relations get their own arguments and the default `orderBy` argument keeps
         * the shared `OrderByClause` type that clients already reference. Set this to false
         * to keep the collection query free of the extra arguments.
         */
        public bool $order_by_relations = true,
    ) {
        $this->reflector = new \ReflectionClass($this->class);
    }

    public function getName(): string
    {
        return $this->name ?? Str::snake(Str::plural($this->reflector->getShortName()));
    }

    public function getReturnType(): string
    {
        return $this->return_type ?? ("[{$this->reflector->getShortName()}!]!");
    }

    public function getAuthorize(): string
    {
        if (! is_null($this->authorize)) {
            if (is_string($this->authorize)) {
                return $this->authorize;
            }

            if ($this->authorize) {
                return '@canModel(ability: "viewAny")';
            }

        }

        return '';
    }

    public function getSchema(): string
    {
        $directives = implode(' ', $this->directives);
        $filters = '';

        if (is_array($this->filters) && count($this->filters)) {
            $filterDefinitions = array_merge(
                $this->filters ?? [],
                $this->filters_override ?? []
            );

            if ($this->order_by_relations && $this->hasOrderByArgument($filterDefinitions)) {
                $filterDefinitions = array_merge(
                    $filterDefinitions,
                    $this->getRelationOrderByArguments()
                );
            }

            $filters = implode(" \n ", $filterDefinitions);
            $filters = <<<ENDDATA
                (
                    $filters
                )
            ENDDATA;
        }

        return <<<ENDDATA
        extend type Query $directives {
            {$this->getName()} $filters: {$this->getReturnType()} {$this->getAuthorize()} {$this->query}
        }
        ENDDATA;
    }

    /**
     * Whether the collection still offers an `orderBy` argument.
     *
     * @param  array<int, string>  $filterDefinitions
     */
    private function hasOrderByArgument(array $filterDefinitions): bool
    {
        foreach ($filterDefinitions as $filterDefinition) {
            if (str_starts_with(trim($filterDefinition), 'orderBy:')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build an `orderBy<Relation>` argument for every orderable relation of the model.
     *
     * Relations are offered one per argument, so the clause type Lighthouse generates for
     * a `relations` argument does not replace the shared `OrderByClause` type of the plain
     * `orderBy` argument.
     *
     * @return array<int, string>
     */
    private function getRelationOrderByArguments(): array
    {
        if (! $this->reflector->isSubclassOf(EloquentModel::class)) {
            return [];
        }

        $model = new $this->class;
        $arguments = [];
        $argumentNames = [];

        foreach (
            $this->reflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $method
        ) {
            $name = $method->getName();
            $returnType = $method->getReturnType();

            if (
                $method->isStatic()
                || $method->getNumberOfParameters() !== 0
                || ! $returnType instanceof \ReflectionNamedType
                || ! is_a($returnType->getName(), Relation::class, true)
                || in_array($name, $model->getHidden(), true)
                || in_array($name, ['column', 'order'], true)
            ) {
                continue;
            }

            try {
                $relation = Relation::noConstraints(
                    fn () => $method->invoke($model)
                );
            } catch (\Throwable) {
                continue;
            }

            if (! $relation instanceof Relation || $relation instanceof MorphTo) {
                continue;
            }

            $argumentName = 'orderBy'.Str::studly($name);

            // `user_profile()` and `userProfile()` both become `orderByUserProfile`, so the
            // first relation that is offered keeps the argument.
            if (in_array($argumentName, $argumentNames, true)) {
                continue;
            }

            $argumentNames[] = $argumentName;

            $related = $relation->getRelated();

            $columns = $related->getConnection()
                ->getSchemaBuilder()
                ->getColumnListing($related->getTable());

            $columns = array_values(
                array_diff($columns, $related->getHidden())
            );

            $relationDefinition = '{ relation: '.json_encode($name, JSON_THROW_ON_ERROR);

            if ($columns !== []) {
                $relationDefinition .= ', columns: '.json_encode($columns, JSON_THROW_ON_ERROR);
            }

            $relationDefinition .= ' }';

            $arguments[] = "{$argumentName}: _ @orderBy(relations: [{$relationDefinition}])";
        }

        return $arguments;
    }
}
