<?php

use Nodesol\LaraQL\Types\ColumnTypes;

it('maps a database column type to a graphql type', function (string $type, bool $autoIncrement, string $expected) {
    expect(ColumnTypes::getType($type, $autoIncrement))->toBe($expected);
})->with([
    // Integers become Int, or ID when they are auto incrementing.
    'smallint' => ['smallint', false, 'Int'],
    'mediumint' => ['mediumint', false, 'Int'],
    'int' => ['int', false, 'Int'],
    'integer' => ['integer', false, 'Int'],
    'bigint' => ['bigint', false, 'Int'],
    'year' => ['year', false, 'Int'],
    'binary' => ['binary', false, 'Int'],
    'auto increment' => ['bigint', true, 'ID'],
    'auto increment int' => ['int', true, 'ID'],

    // String-ish types.
    'tinytext' => ['tinytext', false, 'String'],
    'text' => ['text', false, 'String'],
    'mediumtext' => ['mediumtext', false, 'String'],
    'tinyblob' => ['tinyblob', false, 'String'],
    'blob' => ['blob', false, 'String'],
    'mediumblob' => ['mediumblob', false, 'String'],
    'string' => ['string', false, 'String'],
    'ascii_string' => ['ascii_string', false, 'String'],
    'array' => ['array', false, 'String'],
    'varchar' => ['varchar', false, 'String'],
    'enum' => ['enum', false, 'String'],

    // Booleans.
    'boolean' => ['boolean', false, 'Boolean'],
    'tinyint' => ['tinyint', false, 'Boolean'],

    // Numbers.
    'float' => ['float', false, 'Float'],
    'decimal' => ['decimal', false, 'Float'],

    // Dates and times.
    'date' => ['date', false, 'Date'],
    'datetimetz' => ['datetimetz', false, 'DateTimeTz'],
    'datetime' => ['datetime', false, 'DateTime'],
    'date_immutable' => ['date_immutable', false, 'DateTime'],
    'dateinterval' => ['dateinterval', false, 'DateTime'],
    'datetime_immutable' => ['datetime_immutable', false, 'DateTime'],
    'datetimetz_immutable' => ['datetimetz_immutable', false, 'DateTime'],
    'time' => ['time', false, 'DateTime'],
    'time_immutable' => ['time_immutable', false, 'DateTime'],
    'timestamp' => ['timestamp', false, 'DateTime'],

    // Anything unknown falls back to String.
    'numeric' => ['numeric', false, 'String'],
    'uuid' => ['uuid', false, 'String'],
    'geometry' => ['geometry', false, 'String'],
]);

it('maps json columns to String because the string group is matched first', function () {
    expect(ColumnTypes::getType('json'))->toBe('String')
        ->and(ColumnTypes::getType('object'))->toBe('Json');
});

it('knows the column type groups it maps', function () {
    expect(ColumnTypes::INT_TYPES)->toBe([
        ColumnTypes::SMALLINT,
        ColumnTypes::MEDIUMINT,
        ColumnTypes::INT,
        ColumnTypes::INTEGER,
        ColumnTypes::BIGINT,
        ColumnTypes::YEAR,
        ColumnTypes::BINARY,
    ])
        ->and(ColumnTypes::BOOLEAN_TYPES)->toBe([ColumnTypes::BOOLEAN, ColumnTypes::TINYINT])
        ->and(ColumnTypes::FLOAT_TYPES)->toBe([ColumnTypes::FLOAT, ColumnTypes::DECIMAL])
        ->and(ColumnTypes::JSON_TYPES)->toBe([ColumnTypes::JSON, ColumnTypes::OBJECT])
        ->and(ColumnTypes::DATETIME_TYPES)->toContain(ColumnTypes::DATE, ColumnTypes::TIMESTAMP)
        ->and(ColumnTypes::STRING_TYPES)->toContain(ColumnTypes::VARCHAR, ColumnTypes::JSON, ColumnTypes::ENUM);
});
