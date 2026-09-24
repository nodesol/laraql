<?php

use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Workbench\App\Models\AdminNote;
use Workbench\App\Models\Profile;
use Workbench\App\Models\Tag;
use Workbench\App\Models\User;

uses(MakesGraphQLRequests::class);

it('resolves the generated single query', function () {
    $article = makeArticle(['title' => 'Hello', 'views' => 5]);

    $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID) {
            article(id: $id) {
                id
                title
                slug
                body
                status
                views
                status_label
                is_active
                published_at
            }
        }
    ', ['id' => $article->id])
        ->assertGraphQLErrorFree()
        ->assertJsonPath('data.article.title', 'Hello')
        ->assertJsonPath('data.article.status', 'draft')
        ->assertJsonPath('data.article.views', 5)
        // status_label comes from a type_override using the @method directive.
        ->assertJsonPath('data.article.status_label', 'DRAFT')
        ->assertJsonPath('data.article.is_active', true)
        ->assertJsonPath('data.article.published_at', null);
});

it('filters the single query with the filter added through the attribute', function () {
    makeArticle(['title' => 'Hello', 'slug' => 'hello']);

    $this->graphQL(/** @lang GraphQL */ '
        query {
            article(slug: "hello") { title }
        }
    ')
        ->assertGraphQLErrorFree()
        ->assertJsonPath('data.article.title', 'Hello');
});

it('keeps hidden columns out of the schema', function () {
    makeArticle();

    $this->graphQL(/** @lang GraphQL */ '
        query {
            article(id: 1) { internal_notes }
        }
    ')->assertGraphQLErrorMessage('Cannot query field "internal_notes" on type "Article".');
});

it('resolves generated relation fields', function () {
    $user = User::query()->create(['name' => 'Amer', 'email' => 'amer@example.com']);
    $article = makeArticle(['user_id' => $user->id]);
    $article->comments()->create(['body' => 'Nice']);
    $tag = Tag::query()->create(['name' => 'php']);
    $article->tags()->attach($tag);
    Profile::query()->create(['article_id' => $article->id, 'bio' => 'About the article']);
    $article->morphComments()->create(['body' => 'Morph comment']);

    $response = $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID) {
            article(id: $id) {
                user { id name articles { id } }
                comments { id body }
                tags { id name }
                profile { id bio }
                morphComments { id body }
            }
        }
    ', ['id' => $article->id]);

    $response->assertGraphQLErrorFree();

    expect($response->json('data.article.user.name'))->toBe('Amer')
        ->and($response->json('data.article.comments.0.body'))->toBe('Nice')
        ->and($response->json('data.article.tags.0.name'))->toBe('php')
        ->and($response->json('data.article.profile.bio'))->toBe('About the article')
        ->and($response->json('data.article.morphComments.0.body'))->toBe('Morph comment');
});

it('filters, orders and paginates the generated collection query', function () {
    makeArticle(['title' => 'One', 'status' => 'published', 'views' => 1]);
    makeArticle(['title' => 'Two', 'status' => 'published', 'views' => 2]);
    makeArticle(['title' => 'Three', 'status' => 'draft', 'views' => 3]);

    $response = $this->graphQL(/** @lang GraphQL */ '
        query {
            publishedArticles(
                where: { column: "status", operator: EQ, value: "published" }
                orderBy: [{ column: "id", order: DESC }]
                first: 1
                page: 2
            ) {
                data { id title }
                paginatorInfo { total count currentPage lastPage perPage hasMorePages }
            }
        }
    ');

    $response->assertGraphQLErrorFree();

    expect($response->json('data.publishedArticles.data.0.title'))->toBe('One')
        ->and($response->json('data.publishedArticles.paginatorInfo.total'))->toBe(2)
        ->and($response->json('data.publishedArticles.paginatorInfo.perPage'))->toBe(1)
        ->and($response->json('data.publishedArticles.paginatorInfo.currentPage'))->toBe(2)
        ->and($response->json('data.publishedArticles.paginatorInfo.lastPage'))->toBe(2)
        ->and($response->json('data.publishedArticles.paginatorInfo.hasMorePages'))->toBeFalse();
});

it('orders a collection that opted into relation order by', function () {
    $article = makeArticle(['title' => 'Ordered']);

    $article->comments()->create(['body' => 'First']);
    $article->comments()->create(['body' => 'Second']);

    // `comments` opts into `@orderBy(relations: ...)`; plain column ordering keeps working.
    $response = $this->graphQL(/** @lang GraphQL */ '
        query {
            comments(orderBy: [{ column: "id", order: DESC }]) {
                data { id body }
            }
        }
    ');

    $response->assertGraphQLErrorFree();

    expect($response->json('data.comments.data.0.body'))->toBe('Second');
});

it('resolves the renamed collection query with its extra filters', function () {
    makeArticle(['title' => 'Draft one', 'status' => 'draft']);
    makeArticle(['title' => 'Published one', 'status' => 'published']);

    $response = $this->graphQL(/** @lang GraphQL */ '
        query {
            publishedArticles(status: "published") {
                data { title }
                paginatorInfo { total }
            }
        }
    ');

    $response->assertGraphQLErrorFree();

    expect($response->json('data.publishedArticles.data.0.title'))->toBe('Published one')
        ->and($response->json('data.publishedArticles.paginatorInfo.total'))->toBe(1);
});

it('resolves a hand written collection query', function () {
    makeArticle(['title' => 'Low', 'views' => 1]);
    makeArticle(['title' => 'High', 'views' => 10]);

    $response = $this->graphQL(/** @lang GraphQL */ '
        query {
            paginatedArticles(minViews: 5, first: 5) {
                data { title }
                paginatorInfo { total }
            }
        }
    ');

    $response->assertGraphQLErrorFree();

    expect($response->json('data.paginatedArticles.data.0.title'))->toBe('High')
        ->and($response->json('data.paginatedArticles.paginatorInfo.total'))->toBe(1);
});

it('resolves a hand written query through a field resolver', function () {
    makeArticle(['title' => 'Slug query', 'slug' => 'slug-query']);

    $this->graphQL(/** @lang GraphQL */ '
        query {
            articleBySlug(slug: "slug-query") { title }
        }
    ')
        ->assertGraphQLErrorFree()
        ->assertJsonPath('data.articleBySlug.title', 'Slug query');
});

it('resolves a hand written query that relies on the defaults', function () {
    $article = makeArticle(['title' => 'Default query']);

    $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID) {
            article_queries(id: $id) { title }
        }
    ', ['id' => $article->id])
        ->assertGraphQLErrorFree()
        ->assertJsonPath('data.article_queries.title', 'Default query');
});

it('resolves a field that is defined by hand in the schema file', function () {
    $this->graphQL(/** @lang GraphQL */ '
        query {
            handWrittenPing
        }
    ')
        ->assertGraphQLErrorFree()
        ->assertJsonPath('data.handWrittenPing', 'pong');
});

it('requires authentication for operations behind the guard directive', function () {
    $note = AdminNote::query()->create(['note' => 'Internal']);

    $this->graphQL(/** @lang GraphQL */ '
        query ($id: ID) {
            admin_note(id: $id) { note }
        }
    ', ['id' => $note->id])->assertGraphQLErrorMessage('Unauthenticated.');

    $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com']);

    $this->actingAs($user)
        ->graphQL(/** @lang GraphQL */ '
            query ($id: ID) {
                admin_note(id: $id) { note }
            }
        ', ['id' => $note->id])
        ->assertGraphQLErrorFree()
        ->assertJsonPath('data.admin_note.note', 'Internal');
});
