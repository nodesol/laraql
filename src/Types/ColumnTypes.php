<?php

namespace Nodesol\LaraQL\Types;

class ColumnTypes
{
    public const SMALLINT = 'smallint';

    public const MEDIUMINT = 'mediumint';

    public const INT = 'int';

    public const INTEGER = 'integer';

    public const BIGINT = 'bigint';

    public const YEAR = 'year';

    public const BINARY = 'binary';

    public const BOOLEAN = 'boolean';

    public const TINYINT = 'tinyint';

    public const TINYTEXT = 'tinytext';

    public const TEXT = 'text';

    public const MEDIUMTEXT = 'mediumtext';

    public const TINYBLOB = 'tinyblob';

    public const BLOB = 'blob';

    public const MEDIUMBLOB = 'mediumblob';

    public const STRING = 'string';

    public const ASCII_STRING = 'ascii_string';

    public const ARRAY = 'array';

    public const VARCHAR = 'varchar';

    public const ENUM = 'enum';

    public const FLOAT = 'float';

    public const DECIMAL = 'decimal';

    public const JSON = 'json';

    public const OBJECT = 'object';

    public const DATE = 'date';

    public const DATETIME = 'datetime';

    public const DATE_IMMUTABLE = 'date_immutable';

    public const DATEINTERVAL = 'dateinterval';

    public const DATETIME_IMMUTABLE = 'datetime_immutable';

    public const DATETIMETZ_IMMUTABLE = 'datetimetz_immutable';

    public const TIME = 'time';

    public const TIME_IMMUTABLE = 'time_immutable';

    public const TIMESTAMP = 'timestamp';

    /** @var string[] */
    public const INT_TYPES = [
        self::SMALLINT,
        self::MEDIUMINT,
        self::INT,
        self::INTEGER,
        self::BIGINT,
        self::YEAR,
        self::BINARY,
    ];

    public const BOOLEAN_TYPES = [
        self::BOOLEAN,
        self::TINYINT,
    ];

    public const STRING_TYPES = [
        self::TINYTEXT,
        self::TEXT,
        self::MEDIUMTEXT,
        self::TINYBLOB,
        self::BLOB,
        self::MEDIUMBLOB,
        self::JSON,
        self::STRING,
        self::ASCII_STRING,
        self::ARRAY,
        self::VARCHAR,
        self::ENUM,
    ];

    public const FLOAT_TYPES = [
        self::FLOAT,
        self::DECIMAL,
    ];

    public const JSON_TYPES = [
        self::JSON,
        self::OBJECT,
    ];

    public const DATETIME_TYPES = [
        self::DATE,
        self::DATETIME,
        self::DATE_IMMUTABLE,
        self::DATEINTERVAL,
        self::DATETIME_IMMUTABLE,
        self::DATETIMETZ_IMMUTABLE,
        self::TIME,
        self::TIME_IMMUTABLE,
        self::TIMESTAMP,
    ];

    public static function getType($type, $auto_increment = false)
    {
        return match (true) {
            (in_array($type, self::INT_TYPES) && $auto_increment) => 'ID',
            in_array($type, self::INT_TYPES) => 'Int',
            in_array($type, self::STRING_TYPES) => 'String',
            in_array($type, self::BOOLEAN_TYPES) => 'Boolean',
            in_array($type, self::FLOAT_TYPES) => 'Float',
            in_array($type, self::JSON_TYPES) => 'Json',
            $type === 'date' => 'Date',
            $type === 'datetimetz' => 'DateTimeTz',
            in_array($type, self::DATETIME_TYPES) => 'DateTime',
            default => 'String'
        };
    }

    private function getColumnTypes(): array
    {
        $intTypes = [
            'smallint',
            'mediumint',
            'int',
            'integer',
            'bigint',
            'year',
            'binary',
        ];

        $booleanTypes = [
            'boolean',
            'tinyint',
        ];

        $stringTypes = [
            'tinytext',
            'text',
            'mediumtext',
            'tinyblob',
            'blob',
            'mediumblob',
            'json',
            'string',
            'ascii_string',
            'array',
            'varchar',
            'enum',
        ];

        $floatTypes = [
            'float',
            'decimal',
        ];

        $jsonTypes = [
            'json',
            'object',
        ];

        $timeTypes = [
            'date_immutable',
            'dateinterval',
            'datetime_immutable',
            'datetimetz_immutable',
            'time',
            'time_immutable',
            'timestamp',
        ];

        return compact(
            'intTypes',
            'booleanTypes',
            'stringTypes',
            'jsonTypes',
            'timeTypes',
            'floatTypes'
        );
    }
}
