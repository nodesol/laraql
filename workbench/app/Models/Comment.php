<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nodesol\LaraQL\Attributes\Model as LaraQL;

#[LaraQL(
    operations: [
        'query_collection' => [
            // Opts into `@orderBy(relations: [...])`, which Lighthouse answers with a
            // generated clause type instead of the shared `OrderByClause`.
            'order_by_relations' => true,
        ],
    ],
)]
class Comment extends Model
{
    protected $fillable = ['article_id', 'user_id', 'body'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
