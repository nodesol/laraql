<?php

namespace Workbench\App\GraphQL;

use Nodesol\LaraQL\Attributes\Query;
use Workbench\App\Models\Article;

/**
 * A resolver holder for two hand written queries: one fully configured, one
 * relying on every LaraQL default.
 */
#[Query(
    name: 'articleBySlug',
    filters: ['slug: String! @eq'],
    return_type: 'Article',
    query: '@field(resolver: "Workbench\\\\App\\\\GraphQL\\\\ArticleQueries@findBySlug")',
)]
#[Query(
    return_type: 'Article',
)]
class ArticleQueries
{
    public function findBySlug(mixed $rootValue, array $args): ?Article
    {
        return Article::query()->where('slug', $args['slug'])->first();
    }
}
