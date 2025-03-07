<?php

namespace Nodesol\LaraQL\Attributes;

use Nodesol\LaraQL\Types\ColumnTypes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Type
{
    private \ReflectionClass $reflector;

    public function __construct(
        public string $class,
        public bool $create_paginator = false,
        public ?string $name = null,
        public ?array $columns = null,
    ) {
        $this->reflector = new \ReflectionClass($this->class);
    }

    public function getName()
    {
        return $this->name ?? $this->reflector->getShortName();
    }

    public function getColumns()
    {
        if ($this->columns) {
            return $this->columns;
        }

        $columns = [];

        foreach ($this->reflector->getProperties() as $property) {
            $type = 'String!';
            if ($property->hasType()) {
                if ($property->getType()->isBuiltIn()) {
                    $type = ColumnTypes::getType($property->getType()->getName());
                } else {
                    $parts = explode('\\', $property->getType()->getName());
                    $type = end($parts);
                }

                $type .= $property->getType()->allowsNull() ? '' : '!';
            }
            $columns[$property->getName()] = $type;
        }

        return $columns;
    }

    public function getPaginatorSchema()
    {
        return ! $this->create_paginator ? '' : <<<PAGINATOR
            type {$this->getName()}Paginator {
                paginatorInfo: PaginatorInfo!
                data: [{$this->getName()}!]!
            }
        PAGINATOR;
    }

    public function getSchema()
    {
        $columns = $this->getColumns();
        $cols = implode(" \n ", array_map(
            (fn ($key, $value): string => "$key: $value"),
            array_keys($columns),
            array_values($columns)
        ));

        return <<<RETURN
            type {$this->getName()} {
                $cols
            }
            {$this->getPaginatorSchema()}
        RETURN;
    }
}
