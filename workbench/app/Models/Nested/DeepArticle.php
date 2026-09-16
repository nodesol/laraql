<?php

namespace Workbench\App\Models\Nested;

use Illuminate\Database\Eloquent\Model;
use Nodesol\LaraQL\Attributes\Model as LaraQL;

/**
 * Models in sub directories of a scanned path must be resolved to their nested namespace.
 */
#[LaraQL]
class DeepArticle extends Model
{
    protected $fillable = ['title'];
}
