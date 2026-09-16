<?php

namespace Workbench\App\GraphQL;

use Nodesol\LaraQL\Attributes\QueryCollection;

/**
 * A paginated collection query that is not tied to a single model, with extra
 * filters layered on top of the LaraQL defaults.
 */
#[QueryCollection(
    name: 'paginatedArticles',
    return_type: '[Article!]!',
    filters_override: [
        'status: String @eq',
        'minViews: Int @where(operator: ">=", key: "views")',
    ],
    query: '@paginate(defaultCount: 5, maxCount: 20)',
)]
class ArticleCollections {}
