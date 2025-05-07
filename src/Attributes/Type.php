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
        public ?string $extends = null,
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
                /** @var \ReflectionNamedType $property_type */
                $property_type = $property->getType();
                if ($property_type->isBuiltIn()) {
                    $type = ColumnTypes::getType($property_type->getName());
                } else {
                    $parts = explode('\\', $property_type->getName());
                    $type = end($parts);
                }

                $type .= $property_type->allowsNull() ? '' : '!';
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

        $extends = "";

        if($this->extends) {
            $extends = " extends {$this->extends} ";
        }

        return <<<RETURN
            type {$this->getName()} $extends {
                $cols
            }
            {$this->getPaginatorSchema()}
        RETURN;
    }
}
