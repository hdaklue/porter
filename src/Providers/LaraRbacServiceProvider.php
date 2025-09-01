<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Providers;

use Hdaklue\LaraRbac\Contracts\Permission\PermissionManagerInterface;
use Hdaklue\LaraRbac\Contracts\Role\RoleAssignmentManagerInterface;
use Hdaklue\LaraRbac\Services\Permission\JsonPermissionManager;
use Hdaklue\LaraRbac\Services\Permission\PermissionManagementService;
use Hdaklue\LaraRbac\Services\Role\RoleAssignmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class LaraRbacServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../../config/lararbac.php',
            'lararbac'
        );

        // Register cache configuration if enabled
        if (config('lararbac.cache.enabled')) {
            $cacheConnection = config('lararbac.cache.connection', 'default');
            $prefix = config('lararbac.cache.key_prefix', 'lararbac');
            
            config([
                "cache.stores.{$prefix}_cache" => [
                    'driver' => 'redis',
                    'connection' => $cacheConnection,
                    'prefix' => $prefix,
                ],
            ]);
        }

        // Bind RoleAssignmentService
        $this->app->singleton(RoleAssignmentManagerInterface::class, RoleAssignmentService::class);

        // Bind PermissionManager (legacy)
        $this->app->singleton(PermissionManagerInterface::class, JsonPermissionManager::class);

        // Bind PermissionManagementService (primary permission service)
        $this->app->singleton(PermissionManagementService::class);

        // Model configuration is handled by the application
        Model::shouldBeStrict();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config files
        $this->publishes([
            __DIR__.'/../../config/lararbac.php' => config_path('lararbac.php'),
        ], 'lararbac-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../../database/migrations/' => database_path('migrations'),
        ], 'lararbac-migrations');

        // Publish constraints
        $this->publishes([
            __DIR__.'/../../constraints/' => config_path('constraints'),
        ], 'lararbac-constraints');

        // Load migrations (for when running package tests)
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}