<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Nodesol\LaraQL\Attributes\Model as LaraQL;

#[LaraQL]
class User extends Authenticatable
{
    protected $fillable = ['name', 'email'];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
