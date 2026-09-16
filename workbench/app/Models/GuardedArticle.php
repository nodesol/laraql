<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Nodesol\LaraQL\Attributes\Model as LaraQL;

#[LaraQL(authorize: true)]
class GuardedArticle extends Model
{
    protected $fillable = ['title'];
}
