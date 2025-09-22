<?php

declare(strict_types=1);

namespace App\GraphQL\ScoutFilters;

use GraphQL\Error\Error;

class MeilisearchOperator implements Operator
{
    public static function missingValueForColumn(string $column): Error
    {
        return new Error("Did not receive a value to match the ScoutFilters for column {$column}.");
    }

    public function enumDefinition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"The available SQL operators that are used to filter query results."
enum SQLOperator {
    "Equal operator (`=`)"
    EQ @enum(value: "=")

    "Not equal operator (`!=`)"
    NEQ @enum(value: "!=")

    "Greater than operator (`>`)"
    GT @enum(value: ">")

    "Greater than or equal operator (`>=`)"
    GTE @enum(value: ">=")

    "Less than operator (`<`)"
    LT @enum(value: "<")

    "Less than or equal operator (`<=`)"
    LTE @enum(value: "<=")

    "Whether a value is within a set of values (`IN`)"
    IN @enum(value: "IN")

    "Whether a value is not within a set of values (`NOT IN`)"
    NOT_IN @enum(value: "NOT IN")



    "Whether a value exists within a set of values (`EXISTS`)"
    EXISTS @enum(value: "EXISTS")

    "Whether a value exists within a set of values (`NOT EXISTS`)"
    NOT_EXISTS @enum(value: "NOT EXISTS")

    "Whether a value is empty within a set of values (`IS EMPTY`)"
    IS_EMPTY @enum(value: "IS EMPTY")

    "Whether a value is not empty within a set of values (`IS NOT EMPTY`)"
    IS_NOT_EMPTY @enum(value: "IS NOT EMPTY")

    "Whether a value exists and is null within a set of values (`IS NULL`)"
    IS_NULL @enum(value: "IS NULL")

    "Whether a value exists and is not null within a set of values (`IS NOT NULL`)"
    NOT_NULL @enum(value: "IS NOT NULL")

    "Whether a value is BETWEEN a set of values (`BETWEEN`)"
    BETWEEN @enum(value: "TO")

    "Apply function based filters like _geoRadius()"
    FUNCTION @enum(value: "FUNC")
}
GRAPHQL;
    }

    public function default(): string
    {
        return 'EQ';
    }

    public function applyConditions(array $scoutFilters): string
    {
        $column = $scoutFilters['column'] ?? '';
        $value = $scoutFilters['value'] ?? '';
        $exploded_value = explode(',', $value) ?? [];

        // Some operators require calling Laravel's conditions in different ways
        $operator = $scoutFilters['operator'];

        return match ($operator) {
            'EXISTS', 'NOT EXISTS', 'IS EMPTY', 'IS NOT EMPTY', 'IS NULL', 'IS NOT NULL' => "$column $operator",
            'IN', 'NOT IN' => "$column $operator [$value]",
            'TO' => $column.' '.$exploded_value[0].' TO '.$exploded_value[1],
            'FUNC' => $value,
            default => "$column $operator $value"
        };
    }
}
