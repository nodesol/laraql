<?php

declare(strict_types=1);

namespace Nodesol\LaraQL\ScoutFilters;

/**
 * An Operator handles the database or application specific bits
 * of applying Scout Filters to a database query builder.
 */
interface Operator
{
    /** Return the GraphQL SDL definition of the operator enum. */
    public function enumDefinition(): string;

    /**
     * The default value if no operator is specified.
     *
     * @example "EQ"
     */
    public function default(): string;

    /**
     * Apply the conditions to the query builder.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<TModel>  $builder
     * @param  array<string, mixed>  $scoutFilters
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<TModel>
     */
    public function applyConditions(array $scoutFilters): string;
}
