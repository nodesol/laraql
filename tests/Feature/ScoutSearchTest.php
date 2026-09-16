<?php

use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Workbench\App\GraphQL\CustomScoutHandler;
use Workbench\App\Models\Article;
use Workbench\App\Models\SearchablePost;
use Workbench\App\Scout\FakeScout;

uses(MakesGraphQLRequests::class);

beforeEach(function () {
    FakeScout::reset();
    CustomScoutHandler::$calls = 0;
    CustomScoutHandler::$received = [];
});

it('filters a paginated collection through scout filters', function () {
    $first = makeArticle(['title' => 'First', 'status' => 'draft']);
    makeArticle(['title' => 'Second', 'status' => 'published']);

    FakeScout::willReturn([['id' => $first->id]]);

    $response = $this->graphQL(/** @lang GraphQL */ '
        query {
            publishedArticles(
                scoutSearch: {
                    search: "laravel"
                    filters: { AND: [{ column: "status", operator: EQ, value: "draft" }] }
                }
            ) {
                data { id title }
                paginatorInfo { total }
            }
        }
    ');

    $response->assertGraphQLErrorFree();

    expect($response->json('data.publishedArticles.data'))->toHaveCount(1)
        ->and($response->json('data.publishedArticles.data.0.title'))->toBe('First')
        ->and($response->json('data.publishedArticles.paginatorInfo.total'))->toBe(1)
        ->and(FakeScout::lastSearch(Article::class)['query'])->toBe('laravel')
        ->and(FakeScout::lastSearch(Article::class)['options']['filter'])->toBe('( status = draft )');
});

it('combines scout filters with where conditions and ordering', function () {
    $draft = makeArticle(['title' => 'Draft', 'status' => 'draft']);
    $published = makeArticle(['title' => 'Published', 'status' => 'published']);

    // Scout returns both articles, the Eloquent query then filters out the published one.
    FakeScout::willReturn([['id' => $draft->id], ['id' => $published->id]]);

    $response = $this->graphQL(/** @lang GraphQL */ '
        query {
            publishedArticles(
                where: { column: "status", operator: EQ, value: "draft" }
                orderBy: [{ column: "id", order: DESC }]
                scoutSearch: { search: "laravel" }
            ) {
                data { id title }
                paginatorInfo { total }
            }
        }
    ');

    $response->assertGraphQLErrorFree();

    expect($response->json('data.publishedArticles.data'))->toHaveCount(1)
        ->and($response->json('data.publishedArticles.data.0.title'))->toBe('Draft')
        ->and(FakeScout::lastSearch(Article::class)['options'])->not->toHaveKey('filter');
});

it('accepts an explicit null scout filter', function () {
    makeArticle(['title' => 'Any']);

    $response = $this->graphQL(/** @lang GraphQL */ '
        query {
            publishedArticles(scoutSearch: null) {
                data { title }
                paginatorInfo { total }
            }
        }
    ');

    $response->assertGraphQLErrorFree();

    expect($response->json('data.publishedArticles.paginatorInfo.total'))->toBe(1)
        // The directive short circuits before touching Scout.
        ->and(FakeScout::$searches)->toBeEmpty();
});

it('uses the restricted input and the custom handler of a model', function () {
    $post = SearchablePost::query()->create(['title' => 'Restricted']);

    FakeScout::willReturn([['id' => $post->id]]);

    $response = $this->graphQL(/** @lang GraphQL */ '
        query {
            searchablePosts(
                scoutSearch: {
                    search: "term"
                    filters: { column: TITLE, operator: EQ, value: "Restricted" }
                }
            ) {
                data { id title }
                paginatorInfo { total }
            }
        }
    ');

    $response->assertGraphQLErrorFree();

    expect($response->json('data.searchablePosts.data.0.title'))->toBe('Restricted')
        ->and(CustomScoutHandler::$calls)->toBe(1)
        // The custom handler receives the resolved input, including the defaulted operator.
        ->and(CustomScoutHandler::$received[0]['filters']['column'])->toBe('title')
        ->and(CustomScoutHandler::$received[0]['filters']['operator'])->toBe('=')
        // It is the handler that talks to Scout, so nothing was searched by LaraQL.
        ->and(FakeScout::$searches)->toBeEmpty();
});

it('rejects columns outside the restricted set', function () {
    $this->graphQL(/** @lang GraphQL */ '
        query {
            searchablePosts(scoutSearch: { filters: { column: BODY } }) {
                data { id }
            }
        }
    ')->assertGraphQLErrorMessage('Value "BODY" does not exist in "QuerySearchablePostsScoutSearchColumn" enum.');
});

it('accepts an existing enum as the columns list', function () {
    $post = SearchablePost::query()->create(['title' => 'Enum column']);

    FakeScout::willReturn([['id' => $post->id]]);

    $response = $this->graphQL(/** @lang GraphQL */ '
        query {
            enumColumnPosts(
                scoutSearch: { search: "term", filters: { column: DRAFT, operator: EQ, value: "draft" } }
            ) {
                data { id title }
                paginatorInfo { total }
            }
        }
    ');

    $response->assertGraphQLErrorFree();

    expect($response->json('data.enumColumnPosts.data.0.title'))->toBe('Enum column')
        // The plain SDL enum resolves to its value name.
        ->and(FakeScout::lastSearch(SearchablePost::class)['options']['filter'])->toBe('DRAFT = draft');
});
