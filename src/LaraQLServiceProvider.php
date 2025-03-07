<?php

namespace Nodesol\LaraQL;

use Illuminate\Support\Facades\Event;
use Nodesol\LaraQL\Listeners\BuildSchemaStringListener;
use Nuwave\Lighthouse\Events\BuildSchemaString;
use Nuwave\Lighthouse\WhereConditions\WhereConditionsServiceProvider;
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
    }

    public function packageRegistered()
    {
        $this->app->register(WhereConditionsServiceProvider::class);
    }
}
