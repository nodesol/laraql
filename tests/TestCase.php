<?php

namespace Nodesol\LaraQL\Tests;

use Nodesol\LaraQL\LaraQLServiceProvider;
use Nuwave\Lighthouse\Auth\AuthServiceProvider;
use Nuwave\Lighthouse\GlobalId\GlobalIdServiceProvider;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Nuwave\Lighthouse\OrderBy\OrderByServiceProvider;
use Nuwave\Lighthouse\Pagination\PaginationServiceProvider;
use Nuwave\Lighthouse\Testing\TestingServiceProvider;
use Nuwave\Lighthouse\Validation\ValidationServiceProvider;
use Nuwave\Lighthouse\WhereConditions\WhereConditionsServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use Workbench\App\Models\SearchablePost;

abstract class TestCase extends Orchestra
{
    use WithWorkbench;

    /**
     * The Lighthouse providers a real LaraQL installation registers through Composer
     * package discovery, listed explicitly so the suite can boot them.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LighthouseServiceProvider::class,
            AuthServiceProvider::class,
            GlobalIdServiceProvider::class,
            OrderByServiceProvider::class,
            PaginationServiceProvider::class,
            ValidationServiceProvider::class,
            WhereConditionsServiceProvider::class,
            TestingServiceProvider::class,
            LaraQLServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // An in-memory SQLite database, migrated by the tests that need it.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        // Workbench models do not live in App\Models. Lighthouse resolves the model of a
        // generated field from its type name, so every model namespace must be listed.
        $app['config']->set('lighthouse.namespaces.models', [
            'Workbench\App\Models',
            'Workbench\App\Models\Nested',
            'Workbench\App',
        ]);

        // Never persist a compiled schema between test runs.
        $app['config']->set('lighthouse.schema_cache.enable', false);

        // LaraQL caches the generated SDL when app.debug is off.
        $app['config']->set('laraql.cache', false);

        // An array cache keeps cache assertions isolated per test.
        $app['config']->set('cache.default', 'array');

        // Stands in for config/scout.php so the ScoutFilters handler reads a limit from config.
        $app['config']->set('scout', [
            'driver' => 'meilisearch',
            'meilisearch' => [
                'index-settings' => [
                    SearchablePost::class => [
                        'pagination' => ['maxTotalHits' => 250],
                    ],
                ],
            ],
        ]);
    }
}
