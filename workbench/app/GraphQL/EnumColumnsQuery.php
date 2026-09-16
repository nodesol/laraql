<?php

namespace Workbench\App\GraphQL;

use Nodesol\LaraQL\Attributes\QueryCollection;

/**
 * Uses `columnsEnum` so the generated ScoutFilters input refers to an existing
 * enum instead of generating a columns enum.
 */
#[QueryCollection(
    name: 'enumColumnPosts',
    return_type: '[SearchablePost!]!',
    filters_override: [
        'scoutSearch: _ @scoutFilters(columnsEnum: "ArticleStatus")',
    ],
    query: '@paginate(defaultCount: 5)',
)]
class EnumColumnsQuery {}
