<?php

namespace Nodesol\LaraQL;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Event;
use Nodesol\LaraQL\Listeners\BuildSchemaStringListener;
use Nodesol\LaraQL\ScoutFilters\ScoutFiltersServiceProvider;
use Nuwave\Lighthouse\Events\BuildSchemaString;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaraQLServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laraql')
            ->hasConfigFile();
    }

    public function packageBooted()
    {
        Event::listen(
            BuildSchemaString::class,
            BuildSchemaStringListener::class
        );

        $configRepository = app(Repository::class);
        $this->publishes([
            __DIR__.'/../default-schema.graphql' => $configRepository->get('lighthouse.schema_path'),
        ], 'laraql-schema');
    }

    public function packageRegistered()
    {
        $this->app->register(ScoutFiltersServiceProvider::class);
    }
}
