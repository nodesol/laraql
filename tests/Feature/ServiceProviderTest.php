<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Nodesol\LaraQL\LaraQLServiceProvider;
use Nodesol\LaraQL\Listeners\BuildSchemaStringListener;
use Nodesol\LaraQL\ScoutFilters\ScoutFiltersServiceProvider;
use Nuwave\Lighthouse\Events\BuildSchemaString;

it('registers the LaraQL service provider', function () {
    expect(app()->getLoadedProviders())
        ->toHaveKey(LaraQLServiceProvider::class);
});

it('registers the scout filters service provider', function () {
    expect(app()->getLoadedProviders())
        ->toHaveKey(ScoutFiltersServiceProvider::class);
});

it('listens for the Lighthouse schema building event', function () {
    expect(Event::hasListeners(BuildSchemaString::class))->toBeTrue();

    $listeners = app('events')->getRawListeners()[BuildSchemaString::class] ?? [];

    expect($listeners)->toContain(BuildSchemaStringListener::class);
});

it('merges its own configuration', function () {
    expect(config('laraql.directories'))->toBe([
        app_path('Models'),
        app_path('GraphQL'),
    ])
        ->and(config('laraql.models.auto_include'))->toBeFalse()
        ->and(config('laraql.cache'))->toBeFalse();
});

it('publishes an empty Lighthouse schema file', function () {
    $target = config('lighthouse.schema_path');
    $before = file_get_contents($target);

    try {
        // The package ships an empty schema file that Lighthouse can stitch.
        unlink($target);

        Artisan::call('vendor:publish', ['--tag' => 'laraql-schema', '--force' => true]);

        expect($target)->toBeFile()
            ->and(file_get_contents($target))->toBe('');
    } finally {
        file_put_contents($target, $before);
    }
});

it('publishes its configuration file', function () {
    $target = base_path('config/laraql.php');
    $existed = file_exists($target);

    try {
        Artisan::call('vendor:publish', ['--tag' => 'laraql-config', '--force' => true]);

        expect($target)->toBeFile()
            ->and(file_get_contents($target))->toContain("'directories' => [");
    } finally {
        if (! $existed) {
            @unlink($target);
        }
    }
});
