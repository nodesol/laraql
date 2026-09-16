<?php

namespace Workbench\App\Scout;

/**
 * Records what the ScoutFilters directive asks Scout for, and hands back
 * a canned set of hits so the suite never needs a real Meilisearch index.
 */
class FakeScout
{
    /** @var array<int, array<string, mixed>> */
    public static array $hits = [];

    /** @var array<int, array{model: string, query: string, options: array<string, mixed>}> */
    public static array $searches = [];

    public static function reset(): void
    {
        static::$hits = [];
        static::$searches = [];
    }

    /** @param  array<int, array<string, mixed>>  $hits */
    public static function willReturn(array $hits): void
    {
        static::$hits = $hits;
    }

    public static function lastSearch(?string $model = null): ?array
    {
        $searches = $model === null
            ? static::$searches
            : array_values(array_filter(static::$searches, static fn (array $search): bool => $search['model'] === $model));

        return end($searches) ?: null;
    }
}
