<?php

namespace Workbench\App\GraphQL;

use Workbench\App\Scout\FakeScout;

/**
 * A custom ScoutFilters handler: it is invokable, so it can be referenced with
 * the `handler` argument of the `@scoutFilters` directive.
 */
class CustomScoutHandler
{
    public static int $calls = 0;

    /** @var array<int, array<string, mixed>> */
    public static array $received = [];

    /**
     * @param  array<string, mixed>  $scoutFilters
     */
    public function __invoke(object $builder, array $scoutFilters): void
    {
        static::$calls++;
        static::$received[] = $scoutFilters;

        $builder->whereIn('id', array_column(FakeScout::$hits, 'id'));
    }
}
