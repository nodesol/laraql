<?php

namespace Nodesol\LaraQL;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Nodesol\LaraQL\Commands\LaraQLCommand;

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
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laraql_table')
            ->hasCommand(LaraQLCommand::class);
    }
}
