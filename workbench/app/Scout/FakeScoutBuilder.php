<?php

namespace Workbench\App\Scout;

/**
 * Mirrors what Laravel Scout returns from `search()`: a builder whose `raw()`
 * exposes the raw engine response produced by the callback.
 */
class FakeScoutBuilder
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function __construct(protected array $result) {}

    /** @param  callable(FakeScoutIndex, string, array<string, mixed>): mixed  $callback */
    public static function forQuery(string $model, string $query, ?callable $callback): self
    {
        $index = new FakeScoutIndex($model);
        $options = [];

        $result = $callback === null
            ? ['hits' => FakeScout::$hits]
            : $callback($index, $query, $options);

        return new self(is_array($result) ? $result : ['hits' => []]);
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->result;
    }
}
