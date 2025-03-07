<?php

namespace Nodesol\LaraQL\Attributes;

use Nodesol\LaraQL\Types\ColumnTypes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Input {
    private \ReflectionClass $reflector;
    public function __construct(
        public string $class,
        public ?string $name = null,
        public ?array $inputs = null,
    ){
        $this->reflector = new \ReflectionClass($this->class);
    }

    public function getName() {
        return $this->name ?? ($this->reflector->getShortName()."Input");
    }

    public function getInputs() {
        if($this->inputs) {
            return $this->inputs;
        }

        $inputs = [];

        foreach($this->reflector->getProperties() as $property) {
            $type = "String!";
            if($property->hasType()){
                if ($property->getType()->isBuiltIn()) {
                    $type = ColumnTypes::getType($property->getType()->getName());
                } else {
                    $parts = explode("\\", $property->getType()->getName());
                    $type = end($parts);
                }

                $type .= $property->getType()->allowsNull() ? "" : "!";
            }
            $inputs[$property->getName()] = $type;
        }

        return $inputs;
    }

    public function getSchema() {
        $inputs = $this->getInputs();
        $cols = implode(" \n ", array_map(
            (fn($key, $value) : string => "$key: $value"),
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
