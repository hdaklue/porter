<?php

declare(strict_types=1);

use Hdaklue\MargRbac\Models\ModelHasRole;
use Hdaklue\MargRbac\Models\Role;
use Hdaklue\MargRbac\Models\Tenant;
use Hdaklue\MargRbac\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);
/**
 * Database Connection Configuration Tests
 *
 * This test suite verifies that the package can use the configured database
 * connection and database name correctly using the LivesInRbacDB trait.
 */
describe('Database Connection Configuration', function () {

    describe('Package Database Connection', function () {

        it('uses configured marg-rbac-connection as default', function () {
            $user = new User();

            expect($user->getConnectionName())->toBe('marg-rbac-connection');
        });

        it('uses configured marg-rbac database name', function () {
            $user = new User();
            $user->getConnectionName();

            // For testing we use SQLite in-memory, but the package config still shows marg-rbac
            expect(Config::get('margrbac.database.database'))->toBe('marg-rbac');
        });

        it('all models use the same configured connection', function () {
            $user = new User();
            $tenant = new Tenant();
            $role = new Role();
            $modelHasRole = new ModelHasRole();

            $userConnection = $user->getConnectionName();
            $tenantConnection = $tenant->getConnectionName();
            $roleConnection = $role->getConnectionName();
            $modelHasRoleConnection = $modelHasRole->getConnectionName();

            expect($userConnection)->toBe('marg-rbac-connection')
                ->and($tenantConnection)->toBe('marg-rbac-connection')
                ->and($roleConnection)->toBe('marg-rbac-connection')
                ->and($modelHasRoleConnection)->toBe('marg-rbac-connection')
                ->and($userConnection)->toBe($tenantConnection)
                ->and($tenantConnection)->toBe($roleConnection)
                ->and($roleConnection)->toBe($modelHasRoleConnection);
        });

        it('can retrieve database connection instance', function () {
            $user = new User();
            $connectionName = $user->getConnectionName();

            $connection = DB::connection($connectionName);
            expect($connection)->toBeInstanceOf(\Illuminate\Database\Connection::class);
            expect($connection->getName())->toBe('marg-rbac-connection');
        });
    });

    describe('Database Configuration from Package Config', function () {

        it('respects margrbac.database.connection configuration', function () {
            // Test current configuration
            expect(Config::get('margrbac.database.connection'))->toBe('marg-rbac-connection');
            expect(Config::get('margrbac.database.database'))->toBe('marg-rbac');

            $user = new User();
            expect($user->getConnectionName())->toBe('marg-rbac-connection');
        });

        it('falls back to default connection when package connection not configured', function () {
            Config::set('margrbac.database.connection', null);
            Config::set('database.default', 'fallback-connection');

            $user = new User();
            expect($user->getConnectionName())->toBe('fallback-connection');

            // Reset for other tests
            Config::set('margrbac.database.connection', 'marg-rbac-connection');
            Config::set('database.default', 'marg-rbac-connection');
        });

        it('automatically configures connection from package config', function () {
            // Set a new connection name
            Config::set('margrbac.database.connection', 'test-connection');
            Config::set('margrbac.database.host', 'test-host');
            Config::set('margrbac.database.port', '3307');
            Config::set('margrbac.database.database', 'test-database');
            Config::set('margrbac.database.username', 'test-user');
            Config::set('margrbac.database.password', 'test-password');

            $user = new User();
            $connectionName = $user->getConnectionName();

            expect($connectionName)->toBe('test-connection');

            // Verify connection configuration was set by the trait
            $config = Config::get("database.connections.{$connectionName}");
            expect($config['host'])->toBe('test-host');
            expect($config['port'])->toBe('3307');
            expect($config['database'])->toBe('test-database');
            expect($config['username'])->toBe('test-user');
            expect($config['password'])->toBe('test-password');
            expect($config['driver'])->toBe('mysql');
            expect($config['charset'])->toBe('utf8mb4');
            expect($config['collation'])->toBe('utf8mb4_unicode_ci');

            // Reset to avoid affecting other tests
            Config::set('margrbac.database.connection', 'marg-rbac-connection');
        });
    });

    describe('Table Names', function () {

        it('returns correct table names without database prefix', function () {
            $user = new User();
            $tenant = new Tenant();
            $role = new Role();
            $modelHasRole = new ModelHasRole();

            expect($user->getTable())->toBe('users');
            expect($tenant->getTable())->toBe('tenants');
            expect($role->getTable())->toBe('roles');
            expect($modelHasRole->getTable())->toBe('model_has_roles');
        });
    });

    describe('Database Operations', function () {

        it('verifies tables exist on configured connection', function () {
            $user = new User();
            $connectionName = $user->getConnectionName();

            expect(Schema::connection($connectionName)->hasTable('users'))->toBeTrue();
            expect(Schema::connection($connectionName)->hasTable('tenants'))->toBeTrue();
            expect(Schema::connection($connectionName)->hasTable('roles'))->toBeTrue();
            expect(Schema::connection($connectionName)->hasTable('model_has_roles'))->toBeTrue();
        });

        it('can create and query models using configured connection', function () {
            $user = User::factory()->create(['name' => 'Test User']);
            $tenant = Tenant::factory()->create(['creator_id' => $user->id]);
            $role = Role::factory()->create(['tenant_id' => $tenant->id]);

            // Verify models use correct connection
            expect($user->getConnectionName())->toBe('marg-rbac-connection');
            expect($tenant->getConnectionName())->toBe('marg-rbac-connection');
            expect($role->getConnectionName())->toBe('marg-rbac-connection');

            // Verify we can query the models
            $foundUser = User::where('name', 'Test User')->first();
            expect($foundUser)->toBeInstanceOf(User::class);
            expect($foundUser->name)->toBe('Test User');

            $foundTenant = Tenant::find($tenant->id);
            expect($foundTenant)->toBeInstanceOf(Tenant::class);
            expect($foundTenant->creator_id)->toBe($user->id);

            $foundRole = Role::find($role->id);
            expect($foundRole)->toBeInstanceOf(Role::class);
            expect($foundRole->tenant_id)->toBe($tenant->id);
        });

        it('handles relationships across the configured connection', function () {
            $user = User::factory()->create();
            $tenant = Tenant::factory()->create(['creator_id' => $user->id]);
            $role = Role::factory()->create(['tenant_id' => $tenant->id]);

            // Test relationships work
            expect($tenant->creator->id)->toBe($user->id);
            expect($role->tenant->id)->toBe($tenant->id);
            expect($user->createdTenants->first()->id)->toBe($tenant->id);
        });
    });

    describe('Error Handling', function () {

        it('handles missing database configuration gracefully', function () {
            Config::set('margrbac.database', null);

            $user = new User();

            // Should fallback to default connection
            expect($user->getConnectionName())->toBe('marg-rbac-connection');
        });

        it('handles empty connection name configuration', function () {
            Config::set('margrbac.database.connection', '');

            $user = new User();

            // Should fallback to default connection
            expect($user->getConnectionName())->toBe('marg-rbac-connection');
        });
    });

    describe('Performance', function () {

        it('efficiently handles database configuration', function () {
            $startTime = microtime(true);

            // Create multiple model instances
            for ($i = 0; $i < 50; $i++) {
                $user = new User();
                $user->getConnectionName();
            }

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000; // milliseconds

            // Should complete within reasonable time
            expect($executionTime)->toBeLessThan(100);
        });

        it('maintains configuration consistency across model instances', function () {
            $connections = [];

            for ($i = 0; $i < 10; $i++) {
                $connections[] = (new User())->getConnectionName();
                $connections[] = (new Tenant())->getConnectionName();
                $connections[] = (new Role())->getConnectionName();
            }

            $uniqueConnections = array_unique($connections);
            expect($uniqueConnections)->toHaveCount(1);
            expect($uniqueConnections[0])->toBe('marg-rbac-connection');
        });
    });
});
