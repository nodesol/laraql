<?php

namespace Workbench\App\GraphQL;

use Nodesol\LaraQL\Attributes\Input;

/**
 * An input generated from the class properties.
 */
#[Input]
class SummaryFilter
{
    public string $headline;

    public ?int $views;

    public array $tags;

    public $untyped;
}
