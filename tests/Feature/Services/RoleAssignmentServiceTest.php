<?php

declare(strict_types=1);

use Hdaklue\MargRbac\Collections\Role\ParticipantsCollection;
use Hdaklue\MargRbac\Enums\Role\RoleEnum;
use Hdaklue\MargRbac\Models\ModelHasRole;
use Hdaklue\MargRbac\Models\Role;
use Hdaklue\MargRbac\Models\Tenant;
use Hdaklue\MargRbac\Models\User;
use Hdaklue\MargRbac\Services\Role\RoleAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * RoleAssignmentService Tests
 *
 * This test suite verifies the RoleAssignmentService functionality including
 * role assignment, removal, caching, and validation.
 */
describe('RoleAssignmentService', function () {

    beforeEach(function () {
        // Reset cache configuration for consistent testing
        config(['role.should_cache' => true]);
        Cache::flush();

        $this->service = new RoleAssignmentService();
        $this->user = User::factory()->create();
        $this->tenant = Tenant::factory()->create(['creator_id' => $this->user->id]);

        // Create system roles for the tenant (following the package pattern)
        $this->tenant->systemRoles()->createMany([
            ['name' => 'admin'],
            ['name' => 'viewer'],
            ['name' => 'editor'],
            ['name' => 'manager'],
            ['name' => 'contributor'],
            ['name' => 'guest'],
        ]);

        $this->adminRole = $this->tenant->systemRoles()->where('name', 'admin')->first();
        $this->viewerRole = $this->tenant->systemRoles()->where('name', 'viewer')->first();
    });

    describe('Role Assignment', function () {

        it('can assign a role using string', function () {
            $this->service->assign($this->user, $this->tenant, 'admin');

            expect(ModelHasRole::where([
                'model_type' => $this->user->getMorphClass(),
                'model_id' => $this->user->getKey(),
                'roleable_type' => $this->tenant->getMorphClass(),
                'roleable_id' => $this->tenant->getKey(),
                'role_id' => $this->adminRole->getKey(),
            ])->exists())->toBeTrue();
        });

        it('can assign a role using RoleEnum', function () {
            $this->service->assign($this->user, $this->tenant, RoleEnum::ADMIN);

            expect(ModelHasRole::where([
                'model_type' => $this->user->getMorphClass(),
                'model_id' => $this->user->getKey(),
                'roleable_type' => $this->tenant->getMorphClass(),
                'roleable_id' => $this->tenant->getKey(),
                'role_id' => $this->adminRole->getKey(),
            ])->exists())->toBeTrue();
        });

        it('does not create duplicate role assignments', function () {
            $this->service->assign($this->user, $this->tenant, 'admin');
            $this->service->assign($this->user, $this->tenant, 'admin');

            $count = ModelHasRole::where([
                'model_type' => $this->user->getMorphClass(),
                'model_id' => $this->user->getKey(),
                'roleable_type' => $this->tenant->getMorphClass(),
                'roleable_id' => $this->tenant->getKey(),
            ])->count();

            expect($count)->toBe(1);
        });

        it('clears cache after role assignment', function () {
            Cache::put('test-key', 'test-value');
            expect(Cache::has('test-key'))->toBeTrue();

            $this->service->assign($this->user, $this->tenant, 'admin');

            // Cache should be cleared for the specific target
            $cacheKey = $this->service->generateParticipantsCacheKey($this->tenant);
            expect(Cache::has($cacheKey))->toBeFalse();
        });

        it('throws exception for invalid role', function () {
            expect(fn () => $this->service->assign($this->user, $this->tenant, 'invalid-role'))
                ->toThrow(\Exception::class);
        });
    });

    describe('Role Removal', function () {

        it('can remove role assignment', function () {
            // First assign a role
            $this->service->assign($this->user, $this->tenant, 'admin');

            expect(ModelHasRole::where([
                'model_type' => $this->user->getMorphClass(),
                'model_id' => $this->user->getKey(),
                'roleable_type' => $this->tenant->getMorphClass(),
                'roleable_id' => $this->tenant->getKey(),
            ])->exists())->toBeTrue();

            // Remove the role
            $this->service->remove($this->user, $this->tenant);

            expect(ModelHasRole::where([
                'model_type' => $this->user->getMorphClass(),
                'model_id' => $this->user->getKey(),
                'roleable_type' => $this->tenant->getMorphClass(),
                'roleable_id' => $this->tenant->getKey(),
            ])->exists())->toBeFalse();
        });

        it('handles removal of non-existent role gracefully', function () {
            // Try to remove a role that doesn't exist - should not throw exception
            $this->service->remove($this->user, $this->tenant);

            // If we get here, no exception was thrown
            expect(true)->toBeTrue();
        });

        it('clears cache after role removal', function () {
            $this->service->assign($this->user, $this->tenant, 'admin');
            $this->service->remove($this->user, $this->tenant);

            $cacheKey = $this->service->generateParticipantsCacheKey($this->tenant);
            expect(Cache::has($cacheKey))->toBeFalse();
        });
    });

    describe('Role Change', function () {

        it('can change user role', function () {
            // First assign admin role
            $this->service->assign($this->user, $this->tenant, 'admin');

            // Change to viewer role
            $this->service->changeRoleOn($this->user, $this->tenant, 'viewer');

            $assignment = ModelHasRole::where([
                'model_type' => $this->user->getMorphClass(),
                'model_id' => $this->user->getKey(),
                'roleable_type' => $this->tenant->getMorphClass(),
                'roleable_id' => $this->tenant->getKey(),
            ])->first();

            expect($assignment->role_id)->toBe($this->viewerRole->getKey());
        });

        it('can change role using RoleEnum', function () {
            $this->service->assign($this->user, $this->tenant, RoleEnum::ADMIN);
            $this->service->changeRoleOn($this->user, $this->tenant, RoleEnum::VIEWER);

            $assignment = ModelHasRole::where([
                'model_type' => $this->user->getMorphClass(),
                'model_id' => $this->user->getKey(),
                'roleable_type' => $this->tenant->getMorphClass(),
                'roleable_id' => $this->tenant->getKey(),
            ])->first();

            expect($assignment->role_id)->toBe($this->viewerRole->getKey());
        });
    });

    describe('Role Checking', function () {

        it('can check if user has specific role', function () {
            expect($this->service->hasRoleOn($this->user, $this->tenant, 'admin'))->toBeFalse();

            $this->service->assign($this->user, $this->tenant, 'admin');

            expect($this->service->hasRoleOn($this->user, $this->tenant, 'admin'))->toBeTrue();
            expect($this->service->hasRoleOn($this->user, $this->tenant, 'viewer'))->toBeFalse();
        });

        it('can check role using RoleEnum', function () {
            $this->service->assign($this->user, $this->tenant, RoleEnum::ADMIN);

            expect($this->service->hasRoleOn($this->user, $this->tenant, RoleEnum::ADMIN))->toBeTrue();
            expect($this->service->hasRoleOn($this->user, $this->tenant, RoleEnum::VIEWER))->toBeFalse();
        });

        it('can check if user has any role', function () {
            expect($this->service->hasAnyRoleOn($this->user, $this->tenant))->toBeFalse();

            $this->service->assign($this->user, $this->tenant, 'admin');

            expect($this->service->hasAnyRoleOn($this->user, $this->tenant))->toBeTrue();
        });

        it('works correctly without caching', function () {
            config(['role.should_cache' => false]);

            $this->service->assign($this->user, $this->tenant, 'admin');

            expect($this->service->hasRoleOn($this->user, $this->tenant, 'admin'))->toBeTrue();
            expect($this->service->hasAnyRoleOn($this->user, $this->tenant))->toBeTrue();
        });
    });

    describe('Getting Role Information', function () {

        it('can get user role on target', function () {
            expect($this->service->getRoleOn($this->user, $this->tenant))->toBeNull();

            $this->service->assign($this->user, $this->tenant, 'admin');

            $role = $this->service->getRoleOn($this->user, $this->tenant);
            expect($role)->toBeInstanceOf(Role::class);
            expect($role->name)->toBe('admin');
        });
    });

    describe('Participant Management', function () {

        it('can get participants with roles', function () {
            $user2 = User::factory()->create();

            $this->service->assign($this->user, $this->tenant, 'admin');
            $this->service->assign($user2, $this->tenant, 'viewer');

            $participants = $this->service->getParticipantsWithRoles($this->tenant);

            expect($participants)->toBeInstanceOf(Collection::class);
            expect($participants)->toHaveCount(2);
            expect($participants->first())->toBeInstanceOf(ModelHasRole::class);
        });

        it('can get participants as ParticipantsCollection', function () {
            $this->service->assign($this->user, $this->tenant, 'admin');

            $participants = $this->service->getParticipants($this->tenant);

            expect($participants)->toBeInstanceOf(ParticipantsCollection::class);
        });

        it('can get participants with specific role', function () {
            $user2 = User::factory()->create();

            $this->service->assign($this->user, $this->tenant, 'admin');
            $this->service->assign($user2, $this->tenant, 'viewer');

            $adminUsers = $this->service->getParticipantsHasRole($this->tenant, 'admin');
            expect($adminUsers)->toHaveCount(1);
            expect($adminUsers->first()->getKey())->toBe($this->user->getKey());

            $viewerUsers = $this->service->getParticipantsHasRole($this->tenant, 'viewer');
            expect($viewerUsers)->toHaveCount(1);
            expect($viewerUsers->first()->getKey())->toBe($user2->getKey());
        });

        it('can get participants with role using RoleEnum', function () {
            $this->service->assign($this->user, $this->tenant, RoleEnum::ADMIN);

            $participants = $this->service->getParticipantsHasRole($this->tenant, RoleEnum::ADMIN);
            expect($participants)->toHaveCount(1);
        });
    });

    describe('Entity Assignment Queries', function () {

        it('can get assigned entities by type', function () {
            $tenant2 = Tenant::factory()->create(['creator_id' => $this->user->id]);

            // Create system roles for tenant2
            $tenant2->systemRoles()->createMany([
                ['name' => 'admin'],
                ['name' => 'viewer'],
                ['name' => 'editor'],
                ['name' => 'manager'],
                ['name' => 'contributor'],
                ['name' => 'guest'],
            ]);

            $this->service->assign($this->user, $this->tenant, 'admin');
            $this->service->assign($this->user, $tenant2, 'viewer');

            $assignedTenants = $this->service->getAssignedEntitiesByType($this->user, $this->tenant->getMorphClass());

            expect($assignedTenants)->toHaveCount(2);
            expect($assignedTenants->pluck('id'))->toContain($this->tenant->id, $tenant2->id);
        });

        it('can get assigned entities by keys and type', function () {
            $tenant2 = Tenant::factory()->create(['creator_id' => $this->user->id]);
            $tenant3 = Tenant::factory()->create(['creator_id' => $this->user->id]);

            // Create system roles for tenant2 only (tenant3 intentionally left without roles)
            $tenant2->systemRoles()->createMany([
                ['name' => 'admin'],
                ['name' => 'viewer'],
                ['name' => 'editor'],
                ['name' => 'manager'],
                ['name' => 'contributor'],
                ['name' => 'guest'],
            ]);

            $this->service->assign($this->user, $this->tenant, 'admin');
            $this->service->assign($this->user, $tenant2, 'viewer');
            // Don't assign to tenant3

            $keys = [$this->tenant->id, $tenant2->id, $tenant3->id];
            $assignedTenants = $this->service->getAssignedEntitiesByKeysByType(
                $this->user,
                $keys,
                $this->tenant->getMorphClass()
            );

            expect($assignedTenants)->toHaveCount(2);
            expect($assignedTenants->pluck('id'))->toContain($this->tenant->id, $tenant2->id);
            $tenantIds = $assignedTenants->pluck('id')->toArray();
            expect(in_array($tenant3->id, $tenantIds))->toBeFalse();
        });
    });

    describe('Caching Functionality', function () {

        it('caches participants when caching is enabled', function () {
            config(['role.should_cache' => true]);
            $this->service->assign($this->user, $this->tenant, 'admin');

            // First call should cache the result
            $participants1 = $this->service->getParticipantsWithRoles($this->tenant);

            // Second call should use cache
            $participants2 = $this->service->getParticipantsWithRoles($this->tenant);

            expect($participants1)->toEqual($participants2);

            // Verify cache key exists
            $cacheKey = $this->service->generateParticipantsCacheKey($this->tenant);
            expect(Cache::has($cacheKey))->toBeTrue();
        });

        it('does not cache when caching is disabled', function () {
            config(['role.should_cache' => false]);
            $this->service->assign($this->user, $this->tenant, 'admin');

            $this->service->getParticipantsWithRoles($this->tenant);

            // Cache key should not exist
            $cacheKey = $this->service->generateParticipantsCacheKey($this->tenant);
            expect(Cache::has($cacheKey))->toBeFalse();
        });

        it('caches assigned entities by type', function () {
            config(['role.should_cache' => true]);
            $this->service->assign($this->user, $this->tenant, 'admin');

            // First call should cache
            $entities1 = $this->service->getAssignedEntitiesByType($this->user, $this->tenant->getMorphClass());

            // Second call should use cache
            $entities2 = $this->service->getAssignedEntitiesByType($this->user, $this->tenant->getMorphClass());

            expect($entities1)->toEqual($entities2);
        });

        it('can bulk clear cache', function () {
            $tenant2 = Tenant::factory()->create(['creator_id' => $this->user->id]);

            // Create system roles for tenant2
            $tenant2->systemRoles()->createMany([
                ['name' => 'admin'],
                ['name' => 'viewer'],
                ['name' => 'editor'],
                ['name' => 'manager'],
                ['name' => 'contributor'],
                ['name' => 'guest'],
            ]);

            $this->service->assign($this->user, $this->tenant, 'admin');
            $this->service->assign($this->user, $tenant2, 'admin');

            // Cache participants for both tenants
            $this->service->getParticipantsWithRoles($this->tenant);
            $this->service->getParticipantsWithRoles($tenant2);

            $cacheKey1 = $this->service->generateParticipantsCacheKey($this->tenant);
            $cacheKey2 = $this->service->generateParticipantsCacheKey($tenant2);

            expect(Cache::has($cacheKey1))->toBeTrue();
            expect(Cache::has($cacheKey2))->toBeTrue();

            // Bulk clear cache
            $this->service->bulkClearCache(collect([$this->tenant, $tenant2]));

            expect(Cache::has($cacheKey1))->toBeFalse();
            expect(Cache::has($cacheKey2))->toBeFalse();
        });
    });

    describe('Cache Key Generation', function () {

        it('generates correct participants cache key', function () {
            $expectedKey = "participants:{$this->tenant->getMorphClass()}:{$this->tenant->getKey()}";
            $actualKey = $this->service->generateParticipantsCacheKey($this->tenant);

            expect($actualKey)->toBe($expectedKey);
        });

        it('generates different cache keys for different entities', function () {
            $tenant2 = Tenant::factory()->create(['creator_id' => $this->user->id]);

            $key1 = $this->service->generateParticipantsCacheKey($this->tenant);
            $key2 = $this->service->generateParticipantsCacheKey($tenant2);

            expect($key1 !== $key2)->toBeTrue();
        });
    });

    describe('Role Validation', function () {

        it('ensures role belongs to correct tenant', function () {
            $otherTenant = Tenant::factory()->create(['creator_id' => $this->user->id]);
            // Don't create roles for other tenant

            // Try to assign a role that doesn't exist in the target tenant
            expect(fn () => $this->service->assign($this->user, $otherTenant, 'admin'))
                ->toThrow(\Exception::class);
        });

        it('validates role exists before assignment', function () {
            $role = $this->service->ensureRoleBelongsToTenant($this->tenant, 'admin');

            expect($role)->toBeInstanceOf(Role::class);
            expect($role->name)->toBe('admin');
            expect($role->tenant_id)->toBe($this->tenant->id);
        });

        it('resolves RoleEnum to string correctly', function () {
            $role = $this->service->ensureRoleBelongsToTenant($this->tenant, RoleEnum::ADMIN);

            expect($role)->toBeInstanceOf(Role::class);
            expect($role->name)->toBe('admin');
        });
    });

    describe('Performance and Edge Cases', function () {

        it('handles multiple users on same tenant', function () {
            $users = User::factory(5)->create();

            foreach ($users as $user) {
                $this->service->assign($user, $this->tenant, 'viewer');
            }

            $participants = $this->service->getParticipants($this->tenant);
            expect($participants)->toHaveCount(5);
        });

        it('handles user assigned to multiple tenants', function () {
            $tenants = Tenant::factory(3)->create(['creator_id' => $this->user->id]);

            // Create system roles for all tenants
            foreach ($tenants as $tenant) {
                $tenant->systemRoles()->createMany([
                    ['name' => 'admin'],
                    ['name' => 'viewer'],
                    ['name' => 'editor'],
                    ['name' => 'manager'],
                    ['name' => 'contributor'],
                    ['name' => 'guest'],
                ]);
            }

            foreach ($tenants as $tenant) {
                $this->service->assign($this->user, $tenant, 'admin');
            }

            // Check each assignment exists
            foreach ($tenants as $tenant) {
                expect($this->service->hasRoleOn($this->user, $tenant, 'admin'))->toBeTrue();
            }
        });

        it('handles role checking efficiently', function () {
            // Create multiple assignments to test performance
            $users = User::factory(10)->create();

            foreach ($users as $user) {
                $this->service->assign($user, $this->tenant, 'viewer');
            }

            $startTime = microtime(true);

            // Test role checking for all users
            foreach ($users as $user) {
                $this->service->hasRoleOn($user, $this->tenant, 'viewer');
                $this->service->hasAnyRoleOn($user, $this->tenant);
            }

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000;

            // Should complete within reasonable time
            expect($executionTime)->toBeLessThan(500); // Less than 500ms
        });
    });
});
