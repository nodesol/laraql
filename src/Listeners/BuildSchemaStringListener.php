<?php

namespace Nodesol\LaraQL\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Nodesol\LaraQL\Attributes\Input;
use Nodesol\LaraQL\Attributes\Model as ModelAttribute;
use Nodesol\LaraQL\Attributes\Mutation;
use Nodesol\LaraQL\Attributes\Query;
use Nodesol\LaraQL\Attributes\QueryCollection;
use Nodesol\LaraQL\Attributes\Type;
use Nuwave\Lighthouse\Events\BuildSchemaString;

class BuildSchemaStringListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(BuildSchemaString $event): string
    {
        if (config('laraql.cache')) {
            return Cache::rememberForever('laraql_schema', fn () => $this->getSchemaString());
        }

        return $this->getSchemaString();
    }

    private function getSchemaString(): string
    {
        $directories = config('laraql.directories');
        $schema = [];
        foreach ($directories as $path) {
            if (! is_dir($path)) {
                continue;
            }
            $iterator = new \RegexIterator(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                ),
                '/^.+\.php$/i',
                \RecursiveRegexIterator::GET_MATCH
            );
            foreach ($iterator as $file) {
                $sourceFile = $file[0];
                $class = $this->getClassFromPath($sourceFile);
                $model = $this->getModel($class);
                $reflection = new \ReflectionClass($class);
                if ($model) {
                    $schema[] = $model->getSchema();
                }

                $attributes = $reflection->getAttributes();
                foreach ($attributes as $attribute) {
                    $item = match ($attribute->getName()) {
                        Input::class,
                        Mutation::class,
                        Query::class,
                        QueryCollection::class,
                        Type::class => $this->getObject($class, $attribute),
                        default => null
                    };
                    if ($item) {
                        $schema[] = $item->getSchema();
                    }
                }
            }
        }
        $return = implode("\n\n", $schema);

        return $return.<<<TEST
            scalar Upload @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Upload")
            scalar DateTime @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\DateTime")
            scalar Date @scalar(class: "Nuwave\\\\Lighthouse\\\\Schema\\\\Types\\\\Scalars\\\\Date")

            directive @whereConditions(
                """
                Restrict the allowed column names to a well-defined list.
                This improves introspection capabilities and security.
                Mutually exclusive with `columnsEnum`.
                """
                columns: [String!]

                """
                Use an existing enumeration type to restrict the allowed columns to a predefined list.
                This allows you to re-use the same enum for multiple fields.
                Mutually exclusive with `columns`.
                """
                columnsEnum: String

                """
                Reference a method that applies the client given conditions to the query builder.

                Expected signature: `(
                    \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder \$builder,
                    array<string, mixed> \$whereConditions
                ): void`

                Consists of two parts: a class name and a method name, separated by an `@` symbol.
                If you pass only a class name, the method name defaults to `__invoke`.
                """
                handler: String = "\\\\Nuwave\\\\Lighthouse\\\\WhereConditions\\\\WhereConditionsHandler"
            ) on ARGUMENT_DEFINITION
        TEST;
    }

    private function getModel($class): ?ModelAttribute
    {
        if (! $this->isValid($class)) {
            return null;
        }

        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(ModelAttribute::class);
        $arguements = [];

        if (count($attributes)) {
            $arguements = $attributes[0]->getArguments();
        }

        $arguements['class'] = $class;

        return new ModelAttribute(...$arguements);
    }

    private function getObject(string $class, \ReflectionAttribute $attribute)
    {
        $aclass = $attribute->getName();
        $arguments = $attribute->getArguments();
        $arguments['class'] = $class;

        return new $aclass(...$arguments);
    }

    private function isValid($class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        $reflection = new \ReflectionClass($class);

        if (! $reflection->isSubclassOf(Model::class)) {
            return false;
        }

        if ($reflection->isAbstract()) {
            return false;
        }

        if (! config('laraql.models.auto_include') && ! count($reflection->getAttributes(ModelAttribute::class))) {
            return false;
        }

        return true;

    }

    private function getClassFromPath(string $path): string
    {
        $path = str_replace(app_path('/'), '', $path);

        return sprintf(
            '%s%s',
            app()->getNamespace(),
            strtr(substr($path, 0, strrpos($path, '.') ?: null), '/', '\\')
        );
    }
}
