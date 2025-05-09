<?php

namespace Nodesol\LaraQL\Attributes;

use Illuminate\Support\Str;

class QueryCollection implements Operation
{
    private \ReflectionClass $reflector;

    public function __construct(
        public string $class,
        public ?string $name = null,
        public ?string $return_type = null,
        public ?array $directives = [],
        public ?array $filters = ['where: _ @whereConditions(column: {})', 'first: Int! = 10', 'page: Int', 'orderBy: _ @orderBy'],
        public ?string $query = '@paginate(defaultCount: 10)',
        public bool|string|null $authorize = null,
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

            if($this->authorize){
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
            $filters = implode(" \n ", $this->filters);
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
}
