<?php

namespace Workbench\App\Scout;

class FakeScoutIndex
{
    public function __construct(protected string $model) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function rawSearch(string $query, array $options): array
    {
        FakeScout::$searches[] = [
            'model' => $this->model,
            'query' => $query,
            'options' => $options,
        ];

        return ['hits' => FakeScout::$hits];
    }
}
