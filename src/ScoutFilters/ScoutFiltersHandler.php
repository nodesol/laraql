<?php

declare(strict_types=1);

namespace Nodesol\LaraQL\ScoutFilters;

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

class ScoutFiltersHandler
{
    public function __construct(
        protected Operator $operator,
    ) {}

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<TModel>  $builder
     * @param  array<string, mixed>  $scoutFilters
     * @param  TModel|null  $model
     */
    public function __invoke(
        object $builder,
        array $scoutFilters,
        ?Model $model = null,
        string $boolean = 'and',
    ): void {
        if ($builder instanceof EloquentBuilder) {
            $model = $builder->getModel();
        }

        $filters = $this->getFilters($scoutFilters);

        /** @phpstan-ignore staticMethod.notFound  */
        $scoutBuilder = $model::search('', function ($index, $query, $options) use ($filters, $model) {
            if ($filters) {
                $options['filter'] = trim($filters);
            }
            $options['limit'] = config('scout.'.config('scout.driver').'.index-settings.'.get_class($model).'.pagination.maxTotalHits', 100000);
            $options['attributesToRetrieve'] = ['id'];

            return $index->rawSearch($query, $options);
        });
        $hits = $scoutBuilder->raw();
        $ids = array_column($hits['hits'], 'id');

        $builder->whereIn($model->getKeyName(), $ids);
    }

    protected function getFilters($scoutFilters)
    {
        $filter = '';

        if ($andConnectedConditions = $scoutFilters['AND'] ?? null) {
            $andFilters = [];
            foreach ($andConnectedConditions as $condition) {
                $andFilters[] = '('.$this->getFilters($condition).')';
            }
            $filter .= ' '.implode(' AND ', $andFilters).' ';
        }

        if ($orConnectedConditions = $scoutFilters['OR'] ?? null) {
            $orFilters = [];
            foreach ($orConnectedConditions as $condition) {
                $orFilters[] = '('.$this->getFilters($condition).')';
            }
            $filter .= ' '.implode(' OR ', $orFilters).' ';
        }

        if ($column = $scoutFilters['column'] ?? null) {
            $this->assertValidColumnReference($column);
            $filter .= ' '.$this->operator->applyConditions($scoutFilters).' ';
        } elseif ($scoutFilters['operator'] == 'FUNC') {
            $filter .= ' '.$this->operator->applyConditions($scoutFilters).' ';
        }

        return $filter;
    }

    /** Ensure the column name is well formed to prevent SQL injection. */
    protected function assertValidColumnReference(string $column): void
    {
        // A valid column reference:
        // - must not start with a digit, dot or hyphen
        // - must contain only alphanumerics, digits, underscores, dots, hyphens or JSON references
        $match = \Safe\preg_match('/^(?![0-9.-])([A-Za-z0-9_.-]|->)*$/', $column);
        if ($match === 0) {
            throw new Error(self::invalidColumnName($column));
        }
    }

    public static function invalidColumnName(string $column): string
    {
        return "Column names may contain only alphanumerics or underscores, and may not begin with a digit, got: {$column}";
    }
}
