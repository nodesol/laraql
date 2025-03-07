<?php

namespace Nodesol\LaraQL\Attributes;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Nodesol\LaraQL\Types\ColumnTypes;
use Nodesol\LaraQL\Types\RelationTypes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Model
{
    private \ReflectionObject $reflector;

    private EloquentModel $model;

    private Collection $columns;

    public function __construct(
        public string $class,
        public ?array $operations = [],
        public ?array $directives = [],
        public ?bool $auth_check = false,
        public bool|string $authorize = false,
        public ?string $validator = null,
    ) {
        $this->model = new $class;
        $this->reflector = new \ReflectionObject($this->model);
        $this->initColumns();

    }

    private function initColumns()
    {
        $columns = [];
        foreach (Schema::getColumns($this->model->getTable()) as $column) {
            $type = ColumnTypes::getType($column['type_name'], $column['auto_increment'] ?? false);

            if (! $column['nullable']) {
                $type .= '!';
            }
            $column['graphql_type'] = $type;
            $column['graphql_string'] = "{$column['name']}: $type";
            $column['graphql_query_string'] = "{$column['name']}: $type";
            $column['hidden'] = in_array($column['name'], $this->model->getHidden());
            $column['fillable'] = in_array($column['name'], $this->model->getFillable());
            $column['filterable'] = array_diff($this->model->getFillable(), $this->model->getHidden());
            $columns[$column['name']] = $column;
        }
        $this->columns = collect($columns);
    }

    public function getInputSchema()
    {
        $cols = $this->columns
            ->where('fillable', true)
            ->pluck('graphql_type', 'name')
            ->toArray();
        $input = new Input(
            class: $this->class,
            name: "{$this->reflector->getShortName()}Input",
            inputs: $cols
        );

        return $input->getSchema();
    }

    public function getTypeSchema()
    {
        $columns = $this->columns
            ->where('hidden', false)
            ->pluck('graphql_type', 'name')
            ->toArray();

        foreach ($this->reflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            try {
                /**
                 * @var \ReflectionNamedType $returnType
                 */
                $returnType = $method->getReturnType();
                if ($returnType && method_exists($returnType, 'isBuiltin') && (! $returnType->isBuiltin()) && $method->hasReturnType()) {
                    $methodName = $method->getName();
                    $relation = new \ReflectionClass( $returnType->getName());
                    if ($method->getNumberOfParameters() == 0 && $relation->isSubclassOf(Relation::class)) {
                        $relatedClassName = class_basename($method->invoke($this->model)->getRelated());
                        $relationClassName = $relation->getShortName();

                        $relationName = lcfirst($relationClassName);
                        if (in_array($relationClassName, RelationTypes::SINGLE_RELATION_TYPES)) {
                            $columns[$methodName] = "$relatedClassName @$relationName";
                        } elseif (in_array($relationClassName, RelationTypes::MULTIPLE_RELATION_TYPES)) {
                            $columns[$methodName] = "[$relatedClassName] @$relationName";
                        }
                    }
                }
            } catch (\Exception) {
                continue;
            }
        }

        $type = new Type(
            class: $this->class,
            create_paginator: false,
            columns: $columns
        );

        return $type->getSchema();
    }

    private function getArguments(?array $args): array
    {
        $return = [
            'class' => $this->class,
            'directives' => $this->directives,
        ];
        if ($args && count($args) > 0) {
            if (isset($args['directives'])) {
                $args['directives'] = array_merge($this->directives, $args['directives']);
            }
            $return = array_merge($return, $args);
        }

        return $return;

    }

    public function getOperations()
    {
        return [
            new Query(...$this->getArguments($this->operations['query'] ?? [])),
            new QueryCollection(...$this->getArguments($this->operations['query_collection'] ?? [])),
            new Mutation(...$this->getArguments($this->operations['create'] ?? []), name: 'create'),
            new Mutation(...$this->getArguments($this->operations['update'] ?? []), name: 'update'),
            new Mutation(...$this->getArguments($this->operations['delete'] ?? []), name: 'delete'),
        ];
    }

    public function getOperationSchema()
    {
        $schema = [];

        foreach ($this->getOperations() as &$operation) {
            $schema[] = $operation->getSchema();
        }

        return implode(" \n ", $schema);
    }

    public function getSchema()
    {

        return <<<SCHEMA
            {$this->getTypeSchema()}
            {$this->getOperationSchema()}
            {$this->getInputSchema()}
        SCHEMA;
    }
}
