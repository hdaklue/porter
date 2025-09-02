<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Providers;

use Hdaklue\Porter\Console\Commands\CreateRoleCommand;
use Hdaklue\Porter\Console\Commands\DoctorCommand;
use Hdaklue\Porter\Console\Commands\InstallCommand;
use Hdaklue\Porter\Console\Commands\ListCommand;
use Hdaklue\Porter\Contracts\RoleManagerContract;
use Hdaklue\Porter\RoleManager;
use Illuminate\Support\ServiceProvider;

class PorterServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../../config/porter.php',
            'porter'
        );

        // Register cache configuration if enabled
        if (config('porter.cache.enabled')) {
            $cacheConnection = config('porter.cache.connection', 'default');
            $prefix = config('porter.cache.key_prefix', 'porter');

            config([
                "cache.stores.{$prefix}_cache" => [
                    'driver' => 'redis',
                    'connection' => $cacheConnection,
                    'prefix' => $prefix,
                ],
            ]);
        }

        // Bind RoleManager
        $this->app->singleton(RoleManagerContract::class, RoleManager::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CreateRoleCommand::class,
                ListCommand::class,
                DoctorCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config files
        $this->publishes([
            __DIR__.'/../../config/porter.php' => config_path('porter.php'),
        ], 'porter-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../../database/migrations/' => database_path('migrations'),
        ], 'porter-migrations');

        // Load migrations (for when running package tests)
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
