<?php

namespace Workbench\App\Models\Concerns;

/**
 * Traits living in the scanned directories are tolerated: LaraQL derives a class
 * name from the file path, finds no model, and moves on.
 */
trait HasUniqueSlug
{
    public function slugFromTitle(): string
    {
        return strtolower(str_replace(' ', '-', (string) $this->title));
    }
}
