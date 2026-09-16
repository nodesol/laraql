<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Nodesol\LaraQL\Attributes\Model as LaraQL;
use Workbench\App\Scout\UsesFakeScout;

#[LaraQL(
    operations: [
        'query_collection' => [
            'name' => 'searchablePosts',
            'filters_override' => [
                // Restricts the client to the title column and swaps in a custom handler.
                'scoutSearch: _ @scoutFilters(columns: ["title"], handler: "Workbench\\\\App\\\\GraphQL\\\\CustomScoutHandler")',
            ],
        ],
    ],
)]
class SearchablePost extends Model
{
    use UsesFakeScout;

    protected $fillable = ['title', 'body'];
}
