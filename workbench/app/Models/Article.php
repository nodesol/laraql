<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Nodesol\LaraQL\Attributes\Model as LaraQL;
use RuntimeException;
use Workbench\App\Scout\UsesFakeScout;

#[LaraQL(
    type_override: [
        'views' => 'Int!',
        'status_label' => 'String! @method(name: "statusLabel")',
    ],
    input_override: [
        'title' => 'String! @rules(apply: ["required", "max:200"])',
        'published_at' => 'DateTime',
    ],
    operations: [
        'query' => [
            'filters_override' => ['slug: String @eq'],
        ],
        'query_collection' => [
            'name' => 'publishedArticles',
            'filters_override' => [
                'status: String @eq',
                'scoutSearch: _ @scoutFilters',
            ],
        ],
    ],
)]
class Article extends Model
{
    use UsesFakeScout;

    protected $fillable = ['title', 'slug', 'body', 'status', 'user_id', 'is_active'];

    protected $hidden = ['internal_notes'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function morphComments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Declared for relation-discovery coverage; the keys are never queried in this suite.
     */
    public function userComments(): HasManyThrough
    {
        return $this->hasManyThrough(Comment::class, User::class, 'id', 'user_id', 'user_id', 'id');
    }

    /**
     * Declared for relation-discovery coverage; the keys are never queried in this suite.
     */
    public function userProfile(): HasOneThrough
    {
        return $this->hasOneThrough(Profile::class, User::class, 'id', 'article_id', 'user_id', 'id');
    }

    /** Not a relation: the return type is built in. */
    public function titleLength(): int
    {
        return strlen((string) $this->title);
    }

    /** Not a relation: relation methods must not take arguments. */
    public function commentsForUser(int $userId): HasMany
    {
        return $this->hasMany(Comment::class)->where('user_id', $userId);
    }

    /** Not a relation: the return type is not declared. */
    public function messages()
    {
        return $this->hasMany(Comment::class);
    }

    /** A relation that explodes while the schema is generated, which LaraQL must swallow. */
    public function brokenRelation(): HasMany
    {
        throw new RuntimeException('This relation is intentionally broken.');
    }

    public function statusLabel(): string
    {
        return strtoupper((string) $this->status);
    }
}
