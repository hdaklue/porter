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
    | Porter automatically discovers roles from your Porter directory.
    | No manual configuration needed - just create role classes that extend BaseRole.
    |
    | Directory: config('porter.directory')
    | Namespace: config('porter.namespace')
    |
    */

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
         | Configure the assignment strategy when assigning a role.
         | - 'replace': (Default) Any existing roles for the assignable entity on the roleable entity will be removed before assigning the new role.
         | - 'add': The new role will be added alongside any existing roles. If the role already exists, no action is taken.
         */
        'assignment_strategy' => env('PORTER_ASSIGNMENT_STRATEGY', 'replace'),

        /*
         | Configure how role keys are stored in the database.
         | - 'encrypted': (Default) Reversible encryption using Laravel's encrypt()/decrypt().
         | - 'hashed': One-way hash using Laravel's Hash facade (requires role verification).
         | - 'plain': Plain text, only allowed in local/testing environments.
         */
        'key_storage' => env('PORTER_KEY_STORAGE', 'encrypted'),

        /*
         | Hash rounds for bcrypt when using 'hashed' storage mode.
         | Higher values increase security but also CPU usage.
         | Recommended: 12-16 for production, 4-8 for testing.
         */
        'hash_rounds' => env('PORTER_HASH_ROUNDS', 12),

        /*
         | Enable automatic snake_case key generation from role class names.
         | When true: ProjectManager -> 'project_manager'
         | When false: Must implement getDbKey() manually in each role.
         */
        'auto_generate_keys' => env('PORTER_AUTO_KEYS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Database-specific settings for optimized performance and reliability.
    |
    */
    'database' => [
        'transaction_attempts' => env('PORTER_DB_TRANSACTION_ATTEMPTS', 3),
        'lock_timeout' => env('PORTER_DB_LOCK_TIMEOUT', 10),
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
        'use_tags' => env('PORTER_CACHE_USE_TAGS', true),
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
