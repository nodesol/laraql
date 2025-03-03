<?php

namespace Nodesol\LaraQL\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Nodesol\LaraQL\LaraQL
 */
class LaraQL extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Nodesol\LaraQL\LaraQL::class;
    }
}
