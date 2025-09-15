<?php

declare(strict_types=1);

use Hdaklue\Porter\Concerns\ReceivesRoleAssignments;
use Hdaklue\Porter\Contracts\RoleableEntity;
use Hdaklue\Porter\Multitenancy\Concerns\IsPorterTenant;
use Hdaklue\Porter\Multitenancy\Contracts\PorterTenantContract;
use Hdaklue\Porter\RoleManager;
use Hdaklue\Porter\Tests\Fixtures\TestUser;
use Illuminate\Database\Eloquent\Model;
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

    Schema::create('test_organizations', function ($table) {
        $table->id();
        $table->string('name');
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

// Test organization model that implements PorterTenantContract
class TestOrganization extends Model implements PorterTenantContract, RoleableEntity
{
    use IsPorterTenant;
    use ReceivesRoleAssignments;

    protected $table = 'test_organizations';

    protected $fillable = ['name'];
}

describe('Self-Reference Tenant Scoping', function () {
    it('allows role assignment when roleable is tenant entity itself', function () {
        $organization = TestOrganization::create(['name' => 'Test Org']);
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'current_tenant_id' => (string) $organization->id,
        ]);

        $roleManager = app(RoleManager::class);

        // This should work - user can have role on their organization
        $roleManager->assign($user, $organization, 'TestAdmin');

        // Verify the assignment was created
        expect($user->hasRoleOn($organization, 'TestAdmin'))->toBeTrue();
    });

    it('can check roles on tenant entity correctly', function () {
        $organization = TestOrganization::create(['name' => 'Test Org']);
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'current_tenant_id' => (string) $organization->id,
        ]);

        $roleManager = app(RoleManager::class);

        // Assign role
        $roleManager->assign($user, $organization, 'TestAdmin');

        // Check role using different methods
        expect($user->hasRoleOn($organization, 'TestAdmin'))->toBeTrue();
        expect($roleManager->hasRoleOn($user, $organization, 'TestAdmin'))->toBeTrue();
        expect($roleManager->hasAnyRoleOn($user, $organization))->toBeTrue();

        $role = $roleManager->getRoleOn($user, $organization);
        expect($role)->not->toBeNull();
        expect($role->getName())->toBe('TestAdmin');
    });

    it('can change roles on tenant entity', function () {
        $organization = TestOrganization::create(['name' => 'Test Org']);
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'current_tenant_id' => (string) $organization->id,
        ]);

        $roleManager = app(RoleManager::class);

        // Assign initial role
        $roleManager->assign($user, $organization, 'TestAdmin');
        expect($user->hasRoleOn($organization, 'TestAdmin'))->toBeTrue();

        // Change role
        $roleManager->changeRoleOn($user, $organization, 'TestEditor');
        expect($user->hasRoleOn($organization, 'TestAdmin'))->toBeFalse();
        expect($user->hasRoleOn($organization, 'TestEditor'))->toBeTrue();
    });

    it('can remove roles from tenant entity', function () {
        $organization = TestOrganization::create(['name' => 'Test Org']);
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'current_tenant_id' => (string) $organization->id,
        ]);

        $roleManager = app(RoleManager::class);

        // Assign role
        $roleManager->assign($user, $organization, 'TestAdmin');
        expect($user->hasRoleOn($organization, 'TestAdmin'))->toBeTrue();

        // Remove role
        $roleManager->remove($user, $organization);
        expect($user->hasRoleOn($organization, 'TestAdmin'))->toBeFalse();
        expect($roleManager->hasAnyRoleOn($user, $organization))->toBeFalse();
    });

    it('works with multiple users on same tenant entity', function () {
        $organization = TestOrganization::create(['name' => 'Test Org']);

        $user1 = TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'current_tenant_id' => (string) $organization->id,
        ]);

        $user2 = TestUser::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'current_tenant_id' => (string) $organization->id,
        ]);

        $roleManager = app(RoleManager::class);

        // Assign different roles to different users
        $roleManager->assign($user1, $organization, 'TestAdmin');
        $roleManager->assign($user2, $organization, 'TestEditor');

        // Verify assignments
        expect($user1->hasRoleOn($organization, 'TestAdmin'))->toBeTrue();
        expect($user2->hasRoleOn($organization, 'TestEditor'))->toBeTrue();

        // Cross-check - users don't have each other's roles
        expect($user1->hasRoleOn($organization, 'TestEditor'))->toBeFalse();
        expect($user2->hasRoleOn($organization, 'TestAdmin'))->toBeFalse();

        // Both users have some role on the organization
        expect($roleManager->hasAnyRoleOn($user1, $organization))->toBeTrue();
        expect($roleManager->hasAnyRoleOn($user2, $organization))->toBeTrue();
    });
});
