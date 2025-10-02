<?php

declare(strict_types=1);

use Hdaklue\Porter\Multitenancy\Exceptions\TenantIntegrityException;
use Hdaklue\Porter\RoleManager;
use Hdaklue\Porter\Tests\Fixtures\TestProject;
use Hdaklue\Porter\Tests\Fixtures\TestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test tables with multitenancy columns
    Schema::create('test_users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('current_tenant_id')->nullable();
        $table->timestamps();
    });

    Schema::create('test_projects', function ($table) {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->string('tenant_id')->nullable();
        $table->timestamps();
    });

    // Add tenant_id to roster table for multitenancy tests
    if (Schema::hasTable('roster')) {
        Schema::table('roster', function ($table) {
            if (! Schema::hasColumn('roster', 'tenant_id')) {
                $table->string('tenant_id')->nullable();
                $table->index(['tenant_id'], 'porter_tenant_idx');
            }
        });
    }

    // Enable multitenancy for these tests
    config([
        'porter.multitenancy.enabled' => true,
        'porter.multitenancy.tenant_key_type' => 'string',
        'porter.multitenancy.auto_scope' => true,
        'porter.multitenancy.cache_per_tenant' => true,
    ]);
});

afterEach(function () {
    // Reset multitenancy config
    config([
        'porter.multitenancy.enabled' => false,
    ]);
});

describe('Basic Multitenancy', function () {
    it('respects multitenancy enabled config', function () {
        config(['porter.multitenancy.enabled' => false]);

        $user = new TestUser(['name' => 'Test User', 'email' => 'test@example.com']);
        $user->save();

        $project = new TestProject(['name' => 'Test Project', 'description' => 'Test']);
        $project->save();

        // Should work without tenant validation when disabled
        expect(fn () => app(RoleManager::class)->assign($user, $project, 'TestAdmin'))
            ->not->toThrow(TenantIntegrityException::class);
    });
});

describe('Tenant Integrity Validation', function () {
    it('allows assignment when both entities have same tenant', function () {
        $user = new TestUser(['name' => 'Test User', 'email' => 'test@example.com', 'current_tenant_id' => 'team_123']);
        $user->save();

        $project = new TestProject(['name' => 'Test Project', 'description' => 'Test', 'tenant_id' => 'team_123']);
        $project->save();

        $roleManager = app(RoleManager::class);
        $roleManager->assign($user, $project, 'TestAdmin');

        expect($user->hasRoleOn($project, 'TestAdmin'))->toBeTrue();
    });

    it('throws exception when assignable has no tenant but roleable does', function () {
        $user = new TestUser(['name' => 'Test User', 'email' => 'test@example.com']);
        $user->save();

        $project = new TestProject(['name' => 'Test Project', 'description' => 'Test', 'tenant_id' => 'team_123']);
        $project->save();

        expect(fn () => app(RoleManager::class)->assign($user, $project, 'TestAdmin'))
            ->toThrow(TenantIntegrityException::class, 'Assignable entity does not have a tenant context');
    });

    it('throws exception when roleable has no tenant but assignable does', function () {
        $user = new TestUser(['name' => 'Test User', 'email' => 'test@example.com', 'current_tenant_id' => 'team_123']);
        $user->save();

        $project = new TestProject(['name' => 'Test Project', 'description' => 'Test']);
        $project->save();

        expect(fn () => app(RoleManager::class)->assign($user, $project, 'TestAdmin'))
            ->toThrow(TenantIntegrityException::class, 'Roleable entity does not have a tenant context');
    });

    it('throws exception when tenants mismatch on first assignment', function () {
        $user = new TestUser(['name' => 'Test User', 'email' => 'test@example.com', 'current_tenant_id' => 'team_123']);
        $user->save();

        $project = new TestProject(['name' => 'Test Project', 'description' => 'Test', 'tenant_id' => 'team_456']);
        $project->save();

        expect(fn () => app(RoleManager::class)->assign($user, $project, 'TestAdmin'))
            ->toThrow(TenantIntegrityException::class, 'Tenant access denied');
    });

    it('allows role changes for existing participants even with tenant mismatch', function () {
        $user = new TestUser(['name' => 'Test User', 'email' => 'test@example.com', 'current_tenant_id' => 'team_123']);
        $user->save();

        $project = new TestProject(['name' => 'Test Project', 'description' => 'Test', 'tenant_id' => 'team_123']);
        $project->save();

        $roleManager = app(RoleManager::class);

        // Initial assignment with matching tenant
        $roleManager->assign($user, $project, 'TestAdmin');
        expect($user->hasRoleOn($project, 'TestAdmin'))->toBeTrue();

        // Change user's current tenant
        $user->current_tenant_id = 'team_456';
        $user->save();

        // Should still allow role changes for existing participants
        $roleManager->changeRoleOn($user, $project, 'TestEditor');
        expect($user->hasRoleOn($project, 'TestEditor'))->toBeTrue();
        expect($user->hasRoleOn($project, 'TestAdmin'))->toBeFalse();
    });

    it('allows assignment when both entities have no tenant', function () {
        $user = new TestUser(['name' => 'Test User', 'email' => 'test@example.com']);
        $user->save();

        $project = new TestProject(['name' => 'Test Project', 'description' => 'Test']);
        $project->save();

        $roleManager = app(RoleManager::class);
        $roleManager->assign($user, $project, 'TestAdmin');

        expect($user->hasRoleOn($project, 'TestAdmin'))->toBeTrue();
    });

    it('allows re-assignment for existing participants', function () {
        $user = new TestUser(['name' => 'Test User', 'email' => 'test@example.com', 'current_tenant_id' => 'team_123']);
        $user->save();

        $project = new TestProject(['name' => 'Test Project', 'description' => 'Test', 'tenant_id' => 'team_123']);
        $project->save();

        $roleManager = app(RoleManager::class);

        // Initial assignment
        $roleManager->assign($user, $project, 'TestAdmin');
        expect($user->hasRoleOn($project, 'TestAdmin'))->toBeTrue();

        // User switches tenant context
        $user->current_tenant_id = 'team_999';
        $user->save();

        // Re-assign with different role (should work because user already has a role)
        $roleManager->assign($user, $project, 'TestEditor');
        expect($user->hasRoleOn($project, 'TestEditor'))->toBeTrue();
    });
});

describe('DestroyTenantRoles Method', function () {
    it('deletes all roles for a specific tenant', function () {
        // Create users and projects
        $user1 = new TestUser(['name' => 'User 1', 'email' => 'user1@example.com', 'current_tenant_id' => '123']);
        $user1->save();

        $user2 = new TestUser(['name' => 'User 2', 'email' => 'user2@example.com', 'current_tenant_id' => '123']);
        $user2->save();

        $project1 = new TestProject(['name' => 'Project 1', 'description' => 'Test', 'tenant_id' => '123']);
        $project1->save();

        $project2 = new TestProject(['name' => 'Project 2', 'description' => 'Test', 'tenant_id' => '123']);
        $project2->save();

        // Create role in different tenant (should not be affected)
        $user3 = new TestUser(['name' => 'User 3', 'email' => 'user3@example.com', 'current_tenant_id' => '456']);
        $user3->save();

        $project3 = new TestProject(['name' => 'Project 3', 'description' => 'Test', 'tenant_id' => '456']);
        $project3->save();

        $roleManager = app(RoleManager::class);

        $roleManager->assign($user1, $project1, 'TestAdmin');
        $roleManager->assign($user2, $project2, 'TestEditor');
        $roleManager->assign($user3, $project3, 'TestAdmin'); // Different tenant

        // Verify initial state
        expect(\Hdaklue\Porter\Models\Roster::where('tenant_id', '123')->count())->toBe(2);
        expect(\Hdaklue\Porter\Models\Roster::where('tenant_id', '456')->count())->toBe(1);

        // Destroy tenant 123 roles
        $deletedCount = $roleManager->destroyTenantRoles('123');

        expect($deletedCount)->toBe(2);
        expect(\Hdaklue\Porter\Models\Roster::where('tenant_id', '123')->count())->toBe(0);
        expect(\Hdaklue\Porter\Models\Roster::where('tenant_id', '456')->count())->toBe(1);
    });

    it('throws exception when multitenancy is disabled', function () {
        config(['porter.multitenancy.enabled' => false]);

        expect(fn () => app(RoleManager::class)->destroyTenantRoles('123'))
            ->toThrow(\DomainException::class, 'Multitenancy is not enabled');
    });

    it('returns zero when no roles exist for tenant', function () {
        $deletedCount = app(RoleManager::class)->destroyTenantRoles('nonexistent');

        expect($deletedCount)->toBe(0);
    });
});
