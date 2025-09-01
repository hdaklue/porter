<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Tests;

use Hdaklue\LaraRbac\Providers\LaraRbacServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaraRbacServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Use lararbac-connection as default for testing
        $app['config']->set('database.default', 'lararbac-connection');

        // Configure the lararbac-connection for testing with SQLite in-memory
        $app['config']->set('database.connections.lararbac-connection', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        // Configure package to use the test connection
        $app['config']->set('lararbac.database_connection', 'lararbac-connection');

        // Configure role table names
        $app['config']->set('lararbac.table_names.roleable_has_roles', 'roleable_has_roles');
        $app['config']->set('lararbac.table_names.roles', 'roles');

        // Configure caching for testing
        $app['config']->set('lararbac.cache.enabled', true);
        $app['config']->set('lararbac.should_cache', true);
        
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
    }
}
