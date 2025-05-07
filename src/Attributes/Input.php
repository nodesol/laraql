<?php

namespace Nodesol\LaraQL\Attributes;

use Nodesol\LaraQL\Types\ColumnTypes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Input
{
    private \ReflectionClass $reflector;

    public function __construct(
        public string $class,
        public ?string $name = null,
        public ?array $inputs = null,
        public ?array $inputs_override = null,
    ) {
        $this->reflector = new \ReflectionClass($this->class);
    }

    public function getName()
    {
        return $this->name ?? ($this->reflector->getShortName().'Input');
    }

    public function getInputs()
    {
        if ($this->inputs) {
            return $this->inputs;
        }

        $inputs = [];

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

                $type .= $property->getType()->allowsNull() ? '' : '!';
            }
            $inputs[$property->getName()] = $type;
        }

        return array_merge($inputs, ($this->inputs_override ?? []));
    }

    public function getSchema()
    {
        $inputs = $this->getInputs();
        $cols = implode(" \n ", array_map(
            (fn ($key, $value): string => "$key: $value"),
            array_keys($inputs),
            array_values($inputs)
        ));

        return <<<RETURN
            input {$this->getName()} {
                $cols
            }
        RETURN;
    }
}
