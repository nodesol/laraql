<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\GuardedArticle;

class GuardedArticlePolicy
{
    public function view(?object $user, GuardedArticle $article): bool
    {
        return true;
    }

    public function viewAny(?object $user): bool
    {
        return true;
    }

    public function create(?object $user): bool
    {
        return true;
    }

    public function update(?object $user, GuardedArticle $article): bool
    {
        return true;
    }

    /**
     * Deleting is never allowed, which proves that the generated `@canFind`
     * directive is actually enforced at runtime.
     */
    public function delete(?object $user, GuardedArticle $article): bool
    {
        return false;
    }
}
