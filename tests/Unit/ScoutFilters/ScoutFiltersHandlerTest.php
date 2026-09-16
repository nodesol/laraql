<?php

use GraphQL\Error\Error;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;
use Nodesol\LaraQL\ScoutFilters\ScoutFiltersHandler;
use Workbench\App\Models\SearchablePost;
use Workbench\App\Scout\FakeScout;

beforeEach(function () {
    FakeScout::reset();
});

function applyScoutFilters(array $scoutFilters, ?object $builder = null, mixed $model = null): object
{
    $builder ??= SearchablePost::query();

    app(ScoutFiltersHandler::class)($builder, $scoutFilters, $model);

    return $builder;
}

it('searches scout and constrains the eloquent query to the returned keys', function () {
    FakeScout::willReturn([['id' => 2], ['id' => 5]]);

    $builder = applyScoutFilters([
        'search' => 'laravel',
        'filters' => ['column' => 'title', 'operator' => '=', 'value' => 'GraphQL'],
    ]);

    $search = FakeScout::lastSearch(SearchablePost::class);

    expect($search['query'])->toBe('laravel')
        ->and($search['options']['filter'])->toBe('title = GraphQL')
        // The limit comes from config('scout.<driver>.index-settings.<model>.pagination.maxTotalHits').
        ->and($search['options']['limit'])->toBe(250)
        ->and($search['options']['attributesToRetrieve'])->toBe(['id'])
        ->and($builder->getQuery()->wheres[0]['type'])->toBe('In')
        ->and($builder->getQuery()->wheres[0]['column'])->toBe('id')
        ->and($builder->getQuery()->wheres[0]['values'])->toBe([2, 5])
        ->and($builder->toSql())->toContain('where "id" in (?, ?)');
});

it('falls back to a default limit when the index settings are missing', function () {
    config()->set('scout.meilisearch.index-settings', []);

    applyScoutFilters(['search' => 'laravel']);

    expect(FakeScout::lastSearch(SearchablePost::class)['options']['limit'])->toBe(100000);
});

it('searches scout without a filter when only a search term is given', function () {
    applyScoutFilters(['search' => 'laravel']);

    $search = FakeScout::lastSearch(SearchablePost::class);

    expect($search['query'])->toBe('laravel')
        ->and($search['options'])->not->toHaveKey('filter')
        ->and(applyScoutFilters([])->getQuery()->wheres)->not->toBeEmpty();
});

it('uses an empty search term when none is given', function () {
    applyScoutFilters(['filters' => ['column' => 'title', 'operator' => '=', 'value' => 'GraphQL']]);

    expect(FakeScout::lastSearch(SearchablePost::class)['query'])->toBe('');
});

it('builds nested and conditions', function () {
    // Lighthouse always resolves the `operator` of a condition object, defaulting to EQ.
    applyScoutFilters([
        'filters' => [
            'operator' => '=',
            'AND' => [
                ['column' => 'title', 'operator' => '=', 'value' => 'a'],
                ['column' => 'body', 'operator' => '=', 'value' => 'b'],
            ],
        ],
    ]);

    expect(FakeScout::lastSearch(SearchablePost::class)['options']['filter'])
        ->toBe('( title = a ) AND ( body = b )');
});

it('builds nested or conditions', function () {
    applyScoutFilters([
        'filters' => [
            'operator' => '=',
            'OR' => [
                ['column' => 'title', 'operator' => '=', 'value' => 'a'],
                ['column' => 'title', 'operator' => '=', 'value' => 'b'],
            ],
        ],
    ]);

    expect(FakeScout::lastSearch(SearchablePost::class)['options']['filter'])
        ->toBe('( title = a ) OR ( title = b )');
});

it('concatenates and/or groups without a joining keyword', function () {
    // NOTE: this documents current behaviour. Sibling AND and OR groups are joined
    // without a boolean keyword, which Meilisearch cannot parse.
    applyScoutFilters([
        'filters' => [
            'operator' => '=',
            'AND' => [
                ['column' => 'title', 'operator' => '=', 'value' => 'a'],
            ],
            'OR' => [
                ['column' => 'body', 'operator' => '=', 'value' => 'b'],
            ],
            'column' => 'status',
            'value' => 'published',
        ],
    ]);

    expect(FakeScout::lastSearch(SearchablePost::class)['options']['filter'])
        ->toBe('( title = a )  ( body = b )  status = published');
});

it('supports function based conditions', function () {
    applyScoutFilters([
        'filters' => ['operator' => 'FUNC', 'value' => '_geoRadius(34.15, -104.64, 10000)'],
    ]);

    expect(FakeScout::lastSearch(SearchablePost::class)['options']['filter'])
        ->toBe('_geoRadius(34.15, -104.64, 10000)');
});

it('supports function based conditions nested in a group', function () {
    applyScoutFilters([
        'filters' => [
            'operator' => '=',
            'AND' => [
                ['operator' => 'FUNC', 'value' => '_geoRadius(1, 2, 3)'],
            ],
        ],
    ]);

    expect(FakeScout::lastSearch(SearchablePost::class)['options']['filter'])
        ->toBe('( _geoRadius(1, 2, 3) )');
});

it('infers the model from an eloquent builder', function () {
    applyScoutFilters(['search' => 'laravel']);

    expect(FakeScout::lastSearch(SearchablePost::class))->not->toBeNull();
});

it('accepts an explicit model for a plain query builder', function () {
    FakeScout::willReturn([['id' => 3]]);

    $builder = applyScoutFilters(['search' => 'laravel'], DB::table('searchable_posts'), new SearchablePost);

    expect($builder)->not->toBeInstanceOf(EloquentBuilder::class)
        ->and(FakeScout::lastSearch(SearchablePost::class)['query'])->toBe('laravel')
        ->and($builder->toSql())->toContain('where "id" in (?)')
        ->and($builder->getBindings())->toBe([3]);
});

it('rejects a column name that could break the filter expression', function () {
    expect(fn () => applyScoutFilters([
        'filters' => ['column' => '1; drop table users', 'value' => 'x'],
    ]))->toThrow(Error::class);
});

it('allows json paths in column names', function () {
    applyScoutFilters([
        'filters' => ['column' => 'meta->title', 'operator' => '=', 'value' => 'x'],
    ]);

    expect(FakeScout::lastSearch(SearchablePost::class)['options']['filter'])->toBe('meta->title = x');
});

it('describes which column names are invalid', function () {
    expect(ScoutFiltersHandler::invalidColumnName('-title'))
        ->toBe('Column names may contain only alphanumerics or underscores, and may not begin with a digit, got: -title');
});
