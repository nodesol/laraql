<?php

namespace Workbench\App\GraphQL;

use Workbench\App\Models\User;

/**
 * A class without LaraQL attributes, used by the unit tests to exercise reflection
 * based input generation with a non built-in property type. Because the class is not
 * annotated, nothing is added to the schema.
 */
class UnannotatedFilter
{
    public string $headline;

    public User $author;
}
