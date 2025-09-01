<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection that should be used by LaraRbac models.
    | If null, it will use the default database connection.
    |
    */
    'database_connection' => env('LARARBAC_DB_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which models to use for the RBAC system.
    | You can extend these models to add your own functionality.
    |
    */
    'models' => [
        'role' => Hdaklue\LaraRbac\Models\Role::class,
        'roleable_has_role' => Hdaklue\LaraRbac\Models\RoleableHasRole::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Roles
    |--------------------------------------------------------------------------
    |
    | List all available role classes in your application.
    | Add custom roles by creating new classes that extend BaseRole.
    |
    */
    'roles' => [
        Hdaklue\LaraRbac\Roles\Admin::class,
        Hdaklue\LaraRbac\Roles\Manager::class,
        Hdaklue\LaraRbac\Roles\Editor::class,
        Hdaklue\LaraRbac\Roles\Contributor::class,
        Hdaklue\LaraRbac\Roles\Viewer::class,
        Hdaklue\LaraRbac\Roles\Guest::class,
        // Hdaklue\LaraRbac\Roles\OperationManager::class, // Example custom role
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | The table names used by LaraRbac models. These cannot be changed
    | after installation without running new migrations.
    |
    */
    'table_names' => [
        'roles' => 'roles',
        'roleable_has_roles' => 'roleable_has_roles',
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for improved performance. Uses Redis by default.
    |
    */
    'cache' => [
        'enabled' => env('LARARBAC_CACHE_ENABLED', true),
        'connection' => env('LARARBAC_CACHE_CONNECTION', 'default'),
        'key_prefix' => env('LARARBAC_CACHE_PREFIX', 'lararbac'),
        'ttl' => env('LARARBAC_CACHE_TTL', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy Configuration
    |--------------------------------------------------------------------------
    |
    | Legacy configuration for backwards compatibility.
    |
    */
    'should_cache' => env('LARARBAC_CACHE_ENABLED', true),
];