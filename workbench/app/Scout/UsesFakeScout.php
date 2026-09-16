<?php

namespace Workbench\App\Scout;

/**
 * Replaces Laravel Scout's Searchable::search() with a fake that records the query
 * and returns canned hits, so the ScoutFilters directive can be exercised end to end.
 */
trait UsesFakeScout
{
    public static function search(string $query = '', ?callable $callback = null): FakeScoutBuilder
    {
        return FakeScoutBuilder::forQuery(static::class, $query, $callback);
    }
}
