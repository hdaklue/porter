<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests;

use Hdaklue\Porter\Providers\PorterServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    // Disabled RefreshDatabase due to Laravel version compatibility issues with migrations
    // The core package functionality is tested via unit tests
    // use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the table directly in tests to avoid migration issues
        if (! Schema::hasTable('roaster')) {
            Schema::create('roaster', function ($table) {
                $table->id();
                $table->string('assignable_type');
                $table->string('roleable_type');
                $table->string('assignable_id');
                $table->string('roleable_id');
                $table->text('role_key');
                $table->timestamps();
                $table->unique(['assignable_type', 'assignable_id', 'roleable_type', 'roleable_id', 'role_key'], 'porter_unique');
                $table->index(['assignable_id', 'assignable_type'], 'porter_assignable_idx');
                $table->index(['roleable_id', 'roleable_type'], 'porter_roleable_idx');
                $table->index(['role_key'], 'porter_role_key_idx');
                $table->index(['assignable_type', 'assignable_id', 'roleable_type'], 'porter_user_entity_idx');
                $table->index(['roleable_type', 'roleable_id'], 'porter_entity_idx');
            });
        }
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

        // Configure security (use plain text for simpler testing)
        $app['config']->set('porter.security.key_storage', 'plain');

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
