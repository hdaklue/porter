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
     | Database Configuration
     |--------------------------------------------------------------------------
     |
     | Eager-load Roster relations
     |
     */
    'load_relations' => true,
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
        'roster' => 'roster',
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
        'ttl' => env('PORTER_CACHE_TTL', 3600), // Default TTL: 1 hour
        'use_tags' => env('PORTER_CACHE_USE_TAGS', true),
        // Specific TTL configurations for different cache types (in seconds)
        'role_check_ttl' => env('PORTER_CACHE_ROLE_CHECK_TTL', 1800), // 30 minutes
        'participants_ttl' => env('PORTER_CACHE_PARTICIPANTS_TTL', 3600), // 1 hour
        'assigned_entities_ttl' => env('PORTER_CACHE_ASSIGNED_ENTITIES_TTL', 3600), // 1 hour
    ],

    /*
     |--------------------------------------------------------------------------
     | Multitenancy Configuration
     |--------------------------------------------------------------------------
     |
     | Configure optional multitenancy support. When enabled, role assignments
     | will be scoped to tenants automatically.
     |
     */
    'multitenancy' => [
        /*
         | Enable multitenancy support. When disabled, all multitenancy
         | features are ignored and Porter behaves as single-tenant.
         */
        'enabled' => env('PORTER_MULTITENANCY_ENABLED', false),

        /*
         | The data type for tenant keys. This determines the column type in the roster table.
         | Supported types:
         | - 'integer': For auto-increment or numeric tenant IDs
         | - 'uuid': For UUID v4 tenant identifiers  
         | - 'ulid': For ULID tenant identifiers
         | - 'string': For custom string-based tenant keys
         */
        'tenant_key_type' => env('PORTER_TENANT_KEY_TYPE', 'ulid'),

        /*
         | The column name to use for tenant key in the roster table.
         | This allows customization of the column name if needed.
         */
        'tenant_column' => env('PORTER_TENANT_COLUMN', 'tenant_id'),


        /*
         | Automatically scope all queries by current tenant. When true,
         | Porter will add tenant scoping to all roster queries automatically.
         | When false, you must manually scope queries using forTenant() method.
         */
        'auto_scope' => env('PORTER_AUTO_SCOPE', true),

        /*
         | Include tenant context in cache keys. When enabled, cached role
         | checks are scoped per tenant, preventing cross-tenant cache pollution.
         */
        'cache_per_tenant' => env('PORTER_CACHE_PER_TENANT', true),
    ],
];
