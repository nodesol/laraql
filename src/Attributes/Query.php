<?php

namespace Nodesol\LaraQL\Attributes;

use Illuminate\Support\Str;

class Query implements Operation
{
    private \ReflectionClass $reflector;

    public function __construct(
        public string $class,
        public ?string $name = null,
        public ?string $return_type = null,
        public ?array $directives = [],
        public ?array $filters = ['id: ID @eq'],
        public ?string $query = '@find',
        public bool|string|null $authorize = null,
    ) {
        $this->reflector = new \ReflectionClass($this->class);
    }

    public function getName(): string
    {
        return $this->name ?? Str::snake($this->reflector->getShortName());
    }

    public function getReturnType()
    {
        return $this->return_type ?? $this->reflector->getShortName();
    }

    public function getAuthorize(): string
    {
        if (! is_null($this->authorize)) {
            if (is_string($this->authorize)) {
                return $this->authorize;
            }

            return '@canFind(ability: "view", find: "id")';
        }

        return '';
    }

    public function getSchema(): string
    {
        $directives = implode(' ', $this->directives);
        $filters = implode(" \n ", $this->filters);
        if (count($this->filters)) {
            $filters = "(\n $filters \n)";
        }

        return <<<ENDDATA
        extend type Query $directives {
            {$this->getName()} $filters: {$this->getReturnType()} {$this->query}
        }
        ENDDATA;
    }
}
