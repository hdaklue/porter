<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection that should be used by Porter models.
    | If null, it will use the default database connection.
    |
    */
    'database_connection' => env('PORTER_DB_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which models to use for the Porter system.
    | You can extend these models to add your own functionality.
    |
    */
    'models' => [
        'roster' => Hdaklue\Porter\Models\Roster::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Porter Directory
    |--------------------------------------------------------------------------
    |
    | The directory where Porter role classes are stored.
    | This should be an absolute path or relative to the app directory.
    |
    */
    'directory' => env('PORTER_DIRECTORY', app_path('Porter')),

    /*
    |--------------------------------------------------------------------------
    | Role Namespace
    |--------------------------------------------------------------------------
    |
    | The namespace where Porter role classes are located.
    | This should match the directory structure.
    |
    */
    'namespace' => env('PORTER_NAMESPACE', 'App\\Porter'),

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
        // Default roles - created by porter:install command
        App\Porter\Admin::class,
        App\Porter\Manager::class,
        App\Porter\Editor::class,
        App\Porter\Contributor::class,
        App\Porter\Viewer::class,
        App\Porter\Guest::class,

        // Add your custom roles here
        // App\Porter\ProjectManager::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | ID Strategy Configuration
    |--------------------------------------------------------------------------
    |
    | Configure what type of primary keys your models use. Porter adapts
    | to your existing model architecture automatically.
    |
    | Supported strategies:
    | - 'ulid' (recommended): Time-ordered, URL-safe identifiers
    | - 'uuid': Standard UUID v4 identifiers
    | - 'integer': Traditional auto-increment integers
    |
    */
    'id_strategy' => env('PORTER_ID_STRATEGY', 'ulid'),

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | The table names used by Porter models. These cannot be changed
    | after installation without running new migrations.
    |
    */
    'table_names' => [
        'roaster' => 'roaster',
    ],

    'column_names' => [
        'role_key' => 'role_key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configure security features for role key storage and validation.
    | These settings protect against role enumeration and injection attacks.
    |
    */
    'security' => [
        /*
         | Configure how role keys are stored in the database.
         | - 'hashed': (Default) One-way hash for security.
         | - 'plain': Plain text, useful for debugging.
         */
        'key_storage' => env('PORTER_KEY_STORAGE', 'hashed'),

        /*
         | Enable automatic snake_case key generation from role class names.
         | When true: ProjectManager -> 'project_manager'
         | When false: Must implement getDbKey() manually in each role.
         */
        'auto_generate_keys' => env('PORTER_AUTO_KEYS', true),
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
        'enabled' => env('PORTER_CACHE_ENABLED', true),
        'connection' => env('PORTER_CACHE_CONNECTION', 'default'),
        'key_prefix' => env('PORTER_CACHE_PREFIX', 'porter'),
        'ttl' => env('PORTER_CACHE_TTL', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy Configuration
    |--------------------------------------------------------------------------
    |
    | Legacy configuration for backwards compatibility.
    |
    */
    'should_cache' => env('PORTER_CACHE_ENABLED', true),
];
