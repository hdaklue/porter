<?php

declare(strict_types=1);

namespace Hdaklue\Porter;

use DomainException;
use Hdaklue\Porter\Cache\RoleCacheManager;
use Hdaklue\Porter\Contracts\AssignableEntity;
use Hdaklue\Porter\Contracts\RoleableEntity;
use Hdaklue\Porter\Contracts\RoleContract;
use Hdaklue\Porter\Contracts\RoleManagerContract;
use Hdaklue\Porter\Events\RoleAssigned;
use Hdaklue\Porter\Events\RoleChanged;
use Hdaklue\Porter\Events\RoleRemoved;
use Hdaklue\Porter\Models\Roster;
use Hdaklue\Porter\Multitenancy\Contracts\PorterAssignableContract;
use Hdaklue\Porter\Multitenancy\Contracts\PorterRoleableContract;
use Hdaklue\Porter\Multitenancy\Contracts\PorterTenantContract;
use Hdaklue\Porter\Multitenancy\Exceptions\TenantIntegrityException;
use Hdaklue\Porter\Roles\BaseRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class RoleManager implements RoleManagerContract
{
    /**
     * Assign a role to a user on a target entity.
     *
     * @param AssignableEntity $user The user receiving the role
     * @param RoleableEntity $target The entity the role is assigned on
     * @param string|RoleContract $role The role to assign (name or instance)
     * @throws Throwable
     * @throws TenantIntegrityException
     */
    public function assign(AssignableEntity $user, RoleableEntity $target, string|RoleContract $role): void
    {
        // Check tenant integrity if multitenancy is enabled
        $this->validateTenantIntegrity($user, $target);

        DB::transaction(function () use ($user, $target, $role) {
            $roleInstance = $this->resolveRole($role);

            $assignmentStrategy = config('porter.security.assignment_strategy', 'replace');

            if ($assignmentStrategy === 'replace') {
                $this->removeWithinTransaction($user, $target);
            }

            $encryptedKey = $roleInstance::getDbKey();

            // Determine tenant_id for this assignment
            $tenantId = $this->resolveTenantIdForAssignment($user, $target);

            $assignmentData = [
                'assignable_type' => $user->getMorphClass(),
                'assignable_id' => $user->getKey(),
                'roleable_type' => $target->getMorphClass(),
                'roleable_id' => $target->getKey(),
                'role_key' => $encryptedKey,
            ];

            // Add tenant_id if multitenancy is enabled (always include column when enabled)
            if (config('porter.multitenancy.enabled', false)) {
                $tenantColumn = config('porter.multitenancy.tenant_column', 'tenant_id');
                $assignmentData[$tenantColumn] = $tenantId;
            }

            $assignment = Roster::firstOrCreate($assignmentData);

            if ($assignment->wasRecentlyCreated) {
                RoleAssigned::dispatch($user, $target, $roleInstance);
            }
        });

        RoleCacheManager::clearCache($target, $user);
    }

    /**
     * Remove all role assignments for a user on a target entity.
     *
     * @param AssignableEntity $user The user whose roles to remove
     * @param RoleableEntity $target The entity to remove roles from
     */
    public function remove(AssignableEntity $user, RoleableEntity $target): void
    {
        DB::transaction(function () use ($user, $target) {
            $this->removeWithinTransaction($user, $target);
        });

        RoleCacheManager::clearCache($target, $user);
    }

    /**
     * Check if an assignable entity has a specific role on a roleable entity.
     *
     * @param AssignableEntity $assignableEntity The entity to check
     * @param RoleableEntity $roleableEntity The target entity
     * @param RoleContract $roleContract The role to check for
     * @return bool True if the role exists
     */
    public function check(AssignableEntity $assignableEntity, RoleableEntity $roleableEntity, RoleContract $roleContract): bool
    {
        return $this->performRoleCheck($assignableEntity, $roleableEntity, $roleContract::getDbKey());
    }

    /**
     * Change a user's role on a target entity.
     * Removes the old role and assigns the new one, dispatching a RoleChanged event.
     *
     * @param AssignableEntity $user The user whose role to change
     * @param RoleableEntity $target The entity to change the role on
     * @param string|RoleContract $role The new role to assign
     */
    public function changeRoleOn(AssignableEntity $user, RoleableEntity $target, string|RoleContract $role): void
    {
        $newRole = $this->resolveRole($role);

        // Get the old role for event dispatching
        $oldRole = $this->getRoleOn($user, $target);

        $this->remove($user, $target);
        $this->assign($user, $target, $newRole);

        if ($oldRole) {
            RoleChanged::dispatch($user, $target, $oldRole, $newRole);
        }
    }

    /**
     * Get all participants who have a specific role on a target entity.
     *
     * @param RoleableEntity $target The entity to query
     * @param string|RoleContract $role The role to filter by
     * @return Collection<int, AssignableEntity> Collection of assignable entities
     */
    public function getParticipantsHasRole(RoleableEntity $target, string|RoleContract $role): Collection
    {
        $roleInstance = $this->resolveRole($role);
        $encryptedKey = $roleInstance::getDbKey();

        return Roster::where([
            'roleable_type' => $target->getMorphClass(),
            'roleable_id' => $target->getKey(),
            'role_key' => $encryptedKey,
        ])->with('assignable')->get()->pluck('assignable');
    }

    /**
     * Get assigned entities by specific keys and type for a user.
     *
     * @param AssignableEntity $target The user to query
     * @param array<int, mixed> $keys The entity IDs to filter by
     * @param string $type The entity type (morph class)
     * @return Collection<int, RoleableEntity> Collection of roleable entities
     */
    public function getAssignedEntitiesByKeysByType(AssignableEntity $target, array $keys, string $type): Collection
    {
        return Roster::query()->where([
            'assignable_type' => $target->getMorphClass(),
            'assignable_id' => $target->getKey(),
            'roleable_type' => $type,
        ])
            ->with('roleable')
            ->whereIn('roleable_id', $keys)
            ->get()
            ->pluck('roleable');
    }

    /**
     * Get all assigned entities of a specific type for a user.
     * Results are cached if caching is enabled.
     *
     * @param AssignableEntity $entity The user to query
     * @param string $type The entity type (morph class)
     * @return Collection<int, RoleableEntity> Collection of roleable entities
     */
    public function getAssignedEntitiesByType(AssignableEntity $entity, string $type): Collection
    {
        if (! RoleCacheManager::isEnabled()) {
            return Roster::where([
                'roleable_type' => $type,
                'assignable_type' => $entity->getMorphClass(),
                'assignable_id' => $entity->getKey(),
            ])
                ->with('roleable')
                ->get()->pluck('roleable');
        }

        $cacheKey = RoleCacheManager::generateAssignedEntitiesCacheKey($entity, $type);

        return RoleCacheManager::remember($cacheKey, RoleCacheManager::getTtl('assigned_entities'), fn () => Roster::where([
            'roleable_type' => $type,
            'assignable_type' => $entity->getMorphClass(),
            'assignable_id' => $entity->getKey(),
        ])
            ->with('roleable')
            ->get()
            ->pluck('roleable'));
    }

    /**
     * Get all participants with their role assignments for a target entity.
     * Results are cached if caching is enabled.
     *
     * @param RoleableEntity $target The entity to query
     * @return Collection<int, Roster> Collection of Roster models with assignable relationships
     */
    public function getParticipantsWithRoles(RoleableEntity $target): Collection
    {
        if (! RoleCacheManager::isEnabled()) {
            return Roster::where([
                'roleable_id' => $target->getKey(),
                'roleable_type' => $target->getMorphClass(),
            ])
                ->with('assignable')
                ->get();
        }

        return RoleCacheManager::remember(
            RoleCacheManager::generateParticipantsCacheKey($target),
            RoleCacheManager::getTtl('participants'),
            fn () => Roster::where([
                'roleable_id' => $target->getKey(),
                'roleable_type' => $target->getMorphClass(),
            ])
                ->with('assignable')
                ->get()
        );
    }

    /**
     * Check if a user has a specific role on a target entity.
     * Returns false if the role doesn't exist.
     *
     * @param AssignableEntity $user The user to check
     * @param RoleableEntity $target The target entity
     * @param string|RoleContract $role The role to check for
     * @return bool True if the user has the role
     */
    public function hasRoleOn(AssignableEntity $user, RoleableEntity $target, string|RoleContract $role): bool
    {
        if (is_string($role)) {
            $roleInstance = RoleFactory::tryMake($role);
            if (! $roleInstance) {
                $roleInstance = BaseRole::tryMake($role);
            }
            if (! $roleInstance) {
                return false;
            }
        } else {
            $roleInstance = $role;
        }

        $encryptedKey = $roleInstance::getDbKey();

        return $this->performRoleCheck($user, $target, $encryptedKey);
    }

    /**
     * Check if a user has any role on a target entity.
     *
     * @param AssignableEntity $user The user to check
     * @param RoleableEntity $target The target entity
     * @return bool True if the user has any role
     */
    public function hasAnyRoleOn(AssignableEntity $user, RoleableEntity $target): bool
    {
        return Roster::where([
            'assignable_id' => $user->getKey(),
            'assignable_type' => $user->getMorphClass(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->exists();
    }

    /**
     * Get the role a user has on a target entity.
     *
     * @param AssignableEntity $user The user to check
     * @param RoleableEntity $target The target entity
     * @return RoleContract|null The role instance or null if no role found
     */
    public function getRoleOn(AssignableEntity $user, RoleableEntity $target): ?RoleContract
    {
        $roster = Roster::where([
            'assignable_id' => $user->getKey(),
            'assignable_type' => $user->getMorphClass(),
            'roleable_type' => $target->getMorphClass(),
            'roleable_id' => $target->getKey(),
        ])->first();

        if (! $roster) {
            return null;
        }

        $encryptedKey = $roster->getRoleDBKey();

        return RoleFactory::tryMake($encryptedKey);
    }

    /**
     * Check if a user's role on a target is at least the specified role level.
     *
     * @param AssignableEntity $user The user to check
     * @param RoleContract $role The minimum role level required
     * @param RoleableEntity $target The target entity
     * @return bool True if the user's role is at least the specified level
     */
    public function isAtLeastOn(AssignableEntity $user, RoleContract $role, RoleableEntity $target): bool
    {
        $userRole = $this->getRoleOn($user, $target);

        if (! $userRole) {
            return false;
        }

        return $userRole->isAtLeast($role);
    }

    /**
     * Ensure a role exists in the system.
     *
     * @param string $roleIdentifier The role name or identifier
     * @throws DomainException If the role does not exist
     */
    public function ensureRoleExists(string $roleIdentifier): void
    {
        try {
            RoleFactory::make($roleIdentifier);
        } catch (InvalidArgumentException $e) {
            try {
                BaseRole::make($roleIdentifier);
            } catch (InvalidArgumentException $e2) {
                throw new DomainException("Role '{$roleIdentifier}' does not exist.", 0, $e2);
            }
        }
    }

    /**
     * Clear all caches related to a target entity.
     * Optionally clear caches for a specific user-target combination.
     *
     * @param RoleableEntity $target The target entity
     * @param AssignableEntity|null $user Optional user to clear specific caches for
     */
    public function clearCache(RoleableEntity $target, ?AssignableEntity $user = null): void
    {
        RoleCacheManager::clearCache($target, $user);
    }

    /**
     * Generate a cache key for participants of a target entity.
     *
     * @param RoleableEntity $target The target entity
     * @return string The cache key
     */
    public function generateParticipantsCacheKey(RoleableEntity $target): string
    {
        return RoleCacheManager::generateParticipantsCacheKey($target);
    }

    /**
     * Clear caches for multiple target entities in bulk.
     *
     * @param Collection<int, RoleableEntity> $targets Collection of entities to clear caches for
     */
    public function bulkClearCache(Collection $targets): void
    {
        RoleCacheManager::bulkClearCache($targets);
    }

    /**
     * Remove role assignments within a database transaction.
     * Locks rows for update, clears caches, and dispatches RoleRemoved events.
     *
     * @param AssignableEntity $user The user whose roles to remove
     * @param RoleableEntity $target The target entity
     */
    private function removeWithinTransaction(AssignableEntity $user, RoleableEntity $target): void
    {
        $assignments = Roster::where([
            'assignable_type' => $user->getMorphClass(),
            'assignable_id' => $user->getKey(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->lockForUpdate()->get();

        if ($assignments->isNotEmpty()) {
            // Clear role check caches BEFORE deletion
            foreach ($assignments as $assignment) {
                $encryptedKey = $assignment->getRoleDBKey();
                RoleCacheManager::clearRoleCheckCache($user, $target, $encryptedKey);
            }

            $assignmentIds = $assignments->pluck('id');
            Roster::whereIn('id', $assignmentIds)->delete();

            foreach ($assignments as $assignment) {
                $encryptedKey = $assignment->getRoleDBKey();
                $role = RoleFactory::tryMake($encryptedKey);
                if ($role) {
                    RoleRemoved::dispatch($user, $target, $role);
                }
            }
        }
    }

    /**
     * Perform a role check with optional caching.
     *
     * @param AssignableEntity $user The user to check
     * @param RoleableEntity $target The target entity
     * @param string $encryptedKey The encrypted role key
     * @return bool True if the role exists
     */
    private function performRoleCheck(AssignableEntity $user, RoleableEntity $target, string $encryptedKey): bool
    {
        if (! RoleCacheManager::isEnabled()) {
            return $this->executeRoleCheck($user, $target, $encryptedKey);
        }

        $cacheKey = RoleCacheManager::generateRoleCheckCacheKeyByEncryptedKey($user, $target, $encryptedKey);

        return RoleCacheManager::remember($cacheKey, RoleCacheManager::getTtl('role_check'),
            fn () => $this->executeRoleCheck($user, $target, $encryptedKey)
        );
    }

    /**
     * Execute the actual database query for role check.
     * Supports backward compatibility with plain text keys when using hashed storage.
     *
     * @param AssignableEntity $user The user to check
     * @param RoleableEntity $target The target entity
     * @param string $encryptedKey The encrypted role key
     * @return bool True if the role exists
     */
    private function executeRoleCheck(AssignableEntity $user, RoleableEntity $target, string $encryptedKey): bool
    {
        // First try exact match (most common case)
        $exists = Roster::where([
            'assignable_id' => $user->getKey(),
            'assignable_type' => $user->getMorphClass(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
            'role_key' => $encryptedKey,
        ])->exists();

        if ($exists) {
            return true;
        }

        // If no exact match found and we're using hashed storage,
        // check if there are any plain text role keys that match
        $storageType = config('porter.security.key_storage', 'hashed');
        if ($storageType === 'hashed') {
            // Try to find the role that corresponds to this encrypted key
            try {
                $role = BaseRole::fromDbKey($encryptedKey);
                if ($role) {
                    $plainKey = $role::getPlainKey();

                    // Check if there's a record with the plain text key
                    $exists = Roster::where([
                        'assignable_id' => $user->getKey(),
                        'assignable_type' => $user->getMorphClass(),
                        'roleable_id' => $target->getKey(),
                        'roleable_type' => $target->getMorphClass(),
                        'role_key' => $plainKey,
                    ])->exists();

                    return $exists;
                }
            } catch (\Exception) {
                // If we can't decrypt the key, fall back to false
            }
        }

        return false;
    }

    /**
     * Get the cache TTL for a specific cache type.
     *
     * @param string $type The cache type (role_check, participants, assigned_entities)
     * @return int The TTL in seconds
     */
    public function getCacheTtl(string $type): int
    {
        return RoleCacheManager::getTtl($type);
    }

    /**
     * Resolve a role from string or RoleContract instance.
     *
     * @param string|RoleContract $role The role to resolve
     * @return RoleContract The resolved role instance
     */
    private function resolveRole(string|RoleContract $role): RoleContract
    {
        if ($role instanceof RoleContract) {
            return $role;
        }

        $this->ensureRoleExists($role);

        try {
            return RoleFactory::make($role);
        } catch (InvalidArgumentException $e) {
            return BaseRole::make($role);
        }
    }

    /**
     * Validate tenant integrity between assignable and roleable entities.
     * Handles special case when roleable entity IS the tenant (self-reference).
     *
     * @param AssignableEntity $user The user to validate
     * @param RoleableEntity $target The target entity
     * @throws TenantIntegrityException If tenant integrity validation fails
     */
    private function validateTenantIntegrity(AssignableEntity $user, RoleableEntity $target): void
    {
        if (! config('porter.multitenancy.enabled', false)) {
            return;
        }

        // Special handling when roleable IS the tenant entity
        // Allow cross-tenant assignments when assigning TO a tenant entity
        if ($target instanceof PorterTenantContract) {
            return; // Skip validation - allow assignment to any tenant entity
        }

        // Normal validation for non-tenant roleables
        $assignableTenant = $user instanceof PorterAssignableContract ? $user->getPorterCurrentTenantKey() : null;
        $roleableTenant = $target instanceof PorterRoleableContract ? $target->getPorterTenantKey() : null;

        // Allow if user already has any role in the target's tenant (existing tenant participant)
        if ($roleableTenant !== null) {
            $tenantColumn = config('porter.multitenancy.tenant_column', 'tenant_id');
            $hasRoleInTenant = Roster::where([
                'assignable_type' => $user->getMorphClass(),
                'assignable_id' => $user->getKey(),
            ])->where($tenantColumn, $roleableTenant)->exists();

            if ($hasRoleInTenant) {
                return; // Allow assignment - user is already a participant in this tenant
            }
        }

        // Both must have tenant context or both must not have it
        if ($assignableTenant === null && $roleableTenant !== null) {
            throw TenantIntegrityException::assignableWithoutTenant();
        }

        if ($assignableTenant !== null && $roleableTenant === null) {
            throw TenantIntegrityException::roleableWithoutTenant();
        }

        // User must have a role in the target's tenant to be assigned
        if ($assignableTenant !== null && $roleableTenant !== null && $assignableTenant !== $roleableTenant) {
            throw TenantIntegrityException::noRoleInTenant($roleableTenant);
        }
    }

    /**
     * Resolve the tenant ID for a role assignment.
     * Handles both regular entities and tenant entities as roleables.
     *
     * @param AssignableEntity $user The user being assigned the role
     * @param RoleableEntity $target The target entity
     * @return string|null The tenant ID or null if multitenancy is disabled
     */
    private function resolveTenantIdForAssignment(AssignableEntity $user, RoleableEntity $target): ?string
    {
        if (! config('porter.multitenancy.enabled', false)) {
            return null;
        }

        // If roleable IS the tenant entity, use its key as tenant_id
        if ($target instanceof PorterTenantContract) {
            return $target->getPorterTenantKey();
        }

        // For regular roleables, get tenant from assignable entity
        return $user instanceof PorterAssignableContract ? $user->getPorterCurrentTenantKey() : null;
    }

    /**
     * Destroy all role assignments for a specific tenant.
     * Cache will self-heal as stale entries expire and new queries return correct results.
     *
     * @param string $tenantKey The tenant key/ID
     * @return bool True if any roles were deleted
     * @throws DomainException If multitenancy is not enabled
     */
    public function destroyTenantRoles(string $tenantKey): bool
    {
        if (! config('porter.multitenancy.enabled', false)) {
            throw new DomainException('Multitenancy is not enabled. Cannot destroy tenant roles.');
        }

        $tenantColumn = config('porter.multitenancy.tenant_column', 'tenant_id');

        return Roster::where($tenantColumn, $tenantKey)->delete() > 0;
    }
}
