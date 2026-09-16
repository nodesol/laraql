<?php

namespace Workbench\App\GraphQL;

use Nodesol\LaraQL\Attributes\Type;
use Workbench\App\Models\User;

/**
 * A type generated from the class properties, using every property shape LaraQL
 * understands. Object properties are only valid on a type, not on an input.
 */
#[Type]
class ArticleSummary
{
    public string $headline;

    public ?int $views;

    public array $tags;

    public User $author;

    public $untyped;
}
