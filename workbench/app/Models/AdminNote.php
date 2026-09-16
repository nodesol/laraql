<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Nodesol\LaraQL\Attributes\Model as LaraQL;

#[LaraQL(directives: ['@guard'])]
class AdminNote extends Model
{
    protected $fillable = ['note'];
}
