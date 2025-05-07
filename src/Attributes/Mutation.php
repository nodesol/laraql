<?php

namespace Nodesol\LaraQL\Attributes;

class Mutation implements Operation
{
    private \ReflectionClass $reflector;

    public function __construct(
        public string $class,
        public string $name,
        public ?string $return_type = null,
        public ?array $directives = [],
        public ?array $inputs = null,
        public ?string $query = null,
        public bool|string|null $authorize = null,
    ) {
        $this->reflector = new \ReflectionClass($this->class);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAuthorize(): string
    {
        if (! is_null($this->authorize)) {
            if (is_string($this->authorize)) {
                return $this->authorize;
            }

            if ($this->name == 'create') {
                return '@canModel(ability: "create")';
            }

            return "@canFind(ability: \"{$this->name}\", find: \"id\")";
        }

        return '';
    }

    public function getInputs(): array
    {
        if (! is_null($this->inputs) && is_array($this->inputs)) {
            return $this->inputs;
        }

        return match ($this->name) {
            'create' => ['input' => "{$this->reflector->getShortName()}Input! @spread"],
            'update' => ['id' => 'ID!', 'input' => "{$this->reflector->getShortName()}Input! @spread"],
            'delete' => ['id' => 'ID! @whereKey'],
            default => ['id' => 'ID!'],
        };
    }

    public function getReturnType(): string
    {
        if ($this->return_type) {
            return $this->return_type;
        }

        return $this->reflector->getShortName();
    }

    public function getQuery(): string
    {
        if ($this->query) {
            return $this->query;
        }

        return "@{$this->name}";
    }

    public function getSchema(): string
    {
        $inputs = $this->getInputs();
        $input = implode(" \n ", array_map(
            (fn ($key, $value): string => "$key: $value"),
            array_keys($inputs),
            array_values($inputs)
        ));
        $directives = implode(' ', $this->directives);

        $input = count($this->getInputs()) ? <<<ENDDATA
            (
                $input
            )
        ENDDATA : '';

        return <<<ENDDATA
        extend type Mutation $directives {
            {$this->name}{$this->reflector->getShortName()} $input: {$this->getReturnType()} {$this->getAuthorize()} {$this->getQuery()}
        }
        ENDDATA;
    }
}
