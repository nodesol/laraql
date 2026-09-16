<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nodesol\LaraQL\Attributes\Model as LaraQL;

#[LaraQL]
class Profile extends Model
{
    protected $fillable = ['article_id', 'bio'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
