<?php

namespace Nodesol\LaraQL;

use Illuminate\Support\Facades\Event;
use Nodesol\LaraQL\Listeners\BuildSchemaStringListener;
use Nuwave\Lighthouse\Events\BuildSchemaString;
use Nuwave\Lighthouse\WhereConditions\WhereConditionsServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Illuminate\Contracts\Config\Repository;

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
            __DIR__ . '/default-schema.graphql' => $configRepository->get('lighthouse.schema_path'),
        ], 'laraql-schema');
    }

    public function packageRegistered()
    {
        // $this->app->register(WhereConditionsServiceProvider::class);
        // $this->app->register(WhereConditionsServiceProvider::class);
        // $this->app->register(\Nuwave\Lighthouse\LighthouseServiceProvider::class);
        // $this->app->register(\Nuwave\Lighthouse\Async\AsyncServiceProvider::class);
        // $this->app->register(\Nuwave\Lighthouse\Auth\AuthServiceProvider::class);
        // $this->app->register(\Nuwave\Lighthouse\Bind\BindServiceProvider::class);
        // $this->app->register(\Nuwave\Lighthouse\Cache\CacheServiceProvider::class);
        // $this->app->register(\Nuwave\Lighthouse\GlobalId\GlobalIdServiceProvider::class);
        // $this->app->register(\Nuwave\Lighthouse\OrderBy\OrderByServiceProvider::class);
        // $this->app->register(\Nuwave\Lighthouse\Pagination\PaginationServiceProvider::class);
        // $this->app->register(\Nuwave\Lighthouse\SoftDeletes\SoftDeletesServiceProvider::class);
        // $this->app->register(\Nuwave\Lighthouse\Testing\TestingServiceProvider::class);
        // $this->app->register(\Nuwave\Lighthouse\Validation\ValidationServiceProvider::class);
        if(class_exists("\\MLL\\GraphiQL\\GraphiQLServiceProvider")) {
            $this->app->register("\\MLL\\GraphiQL\\GraphiQLServiceProvider");
        }
    }
}
