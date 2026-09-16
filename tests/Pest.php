<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Nodesol\LaraQL\Tests\TestCase;
use Workbench\App\Models\Article;
use Workbench\App\Models\User;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

/**
 * Create an article, including the columns that are not mass assignable.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeArticle(array $attributes = []): Article
{
    $article = new Article;
    $article->forceFill(array_merge([
        'title' => 'Untitled',
        'slug' => 'slug-'.Str::lower(Str::random(8)),
        'status' => 'draft',
        'views' => 0,
    ], $attributes));
    $article->save();

    return $article;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeUser(array $attributes = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Amer',
        'email' => Str::lower(Str::random(10)).'@example.com',
    ], $attributes));
}
