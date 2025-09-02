<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests;

use Hdaklue\Porter\Providers\PorterServiceProvider;
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

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        // Work around Laravel 12 ConfigMakeCommand bug
        if (! class_exists('Illuminate\Foundation\Console\ConfigMakeCommand')) {
            class_alias('Illuminate\Foundation\Console\ConsoleMakeCommand', 'Illuminate\Foundation\Console\ConfigMakeCommand');
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            PorterServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Use porter-connection as default for testing
        $app['config']->set('database.default', 'porter-connection');

        // Configure the porter-connection for testing with SQLite in-memory
        $app['config']->set('database.connections.porter-connection', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        // Configure package to use the test connection
        $app['config']->set('porter.database_connection', 'porter-connection');

        // Configure role table names
        $app['config']->set('porter.table_names.roaster', 'roaster');
        $app['config']->set('porter.column_names.role_key', 'role_key');

        // Configure test roles
        $app['config']->set('porter.roles', [
            \Hdaklue\Porter\Tests\Fixtures\TestAdmin::class,
            \Hdaklue\Porter\Tests\Fixtures\TestEditor::class,
            \Hdaklue\Porter\Tests\Fixtures\TestViewer::class,
        ]);

        // Configure models
        $app['config']->set('porter.models.roster', \Hdaklue\Porter\Models\Roster::class);

        // Configure security (disable for simpler testing)
        $app['config']->set('porter.security.encrypt_role_keys', false);
        $app['config']->set('porter.security.hash_role_keys', false);
        $app['config']->set('porter.security.auto_generate_keys', true);

        // Configure caching for testing
        $app['config']->set('porter.cache.enabled', false); // Disable for testing
        $app['config']->set('porter.should_cache', false);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');

        // Set app key for encryption (needed even when disabled)
        $app['config']->set('app.key', 'base64:'.base64_encode('a16charsstringkey'));
    }

    protected function getEnvironmentSetUp($app)
    {
        // This method provides compatibility across Laravel versions
        $this->defineEnvironment($app);
    }
}
