<?php

declare(strict_types=1);

namespace Hdaklue\Porter;

use DomainException;
use Hdaklue\Porter\Contracts\AssignableEntity;
use Hdaklue\Porter\Contracts\RoleableEntity;
use Hdaklue\Porter\Contracts\RoleContract;
use Hdaklue\Porter\Contracts\RoleManagerContract;
use Hdaklue\Porter\Events\RoleAssigned;
use Hdaklue\Porter\Events\RoleChanged;
use Hdaklue\Porter\Events\RoleRemoved;
use Hdaklue\Porter\Multitenancy\Contracts\PorterAssignableContract;
use Hdaklue\Porter\Multitenancy\Contracts\PorterRoleableContract;
use Hdaklue\Porter\Multitenancy\Contracts\PorterTenantContract;
use Hdaklue\Porter\Multitenancy\Exceptions\TenantIntegrityException;
use Hdaklue\Porter\Models\Roster;
use Hdaklue\Porter\Roles\BaseRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RoleManager implements RoleManagerContract
{
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

        $this->clearCache($target, $user);
    }

    public function remove(AssignableEntity $user, RoleableEntity $target): void
    {
        DB::transaction(function () use ($user, $target) {
            $this->removeWithinTransaction($user, $target);
        });

        $this->clearCache($target, $user);
    }

    public function check(AssignableEntity $assignableEntity, RoleableEntity $roleableEntity, RoleContract $roleContract): bool
    {
        return $this->performRoleCheck($assignableEntity, $roleableEntity, $roleContract::getDbKey());
    }

    public function changeRoleOn(AssignableEntity $user, RoleableEntity $target, string|RoleContract $role): void
    {
        DB::transaction(function () use ($user, $target, $role) {
            $newRole = $this->resolveRole($role);

            $newEncryptedKey = $newRole::getDbKey();

            $model = Roster::where([
                'assignable_type' => $user->getMorphClass(),
                'assignable_id' => $user->getKey(),
                'roleable_id' => $target->getKey(),
                'roleable_type' => $target->getMorphClass(),
            ])->lockForUpdate()->first();

            if ($model) {
                $oldEncryptedKey = $model->getRoleDBKey();
                $oldRole = RoleFactory::tryMake($oldEncryptedKey);

                // Clear old role cache before changing
                $oldCacheKey = $this->generateRoleCheckCacheKeyByEncryptedKey($user, $target, $oldEncryptedKey);
                Cache::forget($oldCacheKey);

                $model->role_key = $newEncryptedKey;
                $model->save();

                if ($oldRole) {
                    RoleChanged::dispatch($user, $target, $oldRole, $newRole);
                }
            } else {
                $this->assign($user, $target, $newRole);

                return;
            }
        });

        $this->clearCache($target, $user);
    }

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

    public function getAssignedEntitiesByType(AssignableEntity $entity, string $type): Collection
    {
        if (! $this->shouldCache()) {
            return Roster::where([
                'roleable_type' => $type,
                'assignable_type' => $entity->getMorphClass(),
                'assignable_id' => $entity->getKey(),
            ])
                ->with('roleable')
                ->get()->pluck('roleable');
        }

        $cacheKey = $this->generateAssignedEntitiesCacheKey($entity, $type);

        return Cache::remember($cacheKey, $this->getCacheTtl('assigned_entities'), fn () => Roster::where([
            'roleable_type' => $type,
            'assignable_type' => $entity->getMorphClass(),
            'assignable_id' => $entity->getKey(),
        ])
            ->with('roleable')
            ->get()
            ->pluck('roleable'));
    }

    public function getParticipantsWithRoles(RoleableEntity $target): Collection
    {
        if (! $this->shouldCache()) {
            return Roster::where([
                'roleable_id' => $target->getKey(),
                'roleable_type' => $target->getMorphClass(),
            ])
                ->with('assignable')
                ->get();
        }

        return Cache::remember($this->generateParticipantsCacheKey($target), $this->getCacheTtl('participants'), function () use ($target) {
            return Roster::where([
                'roleable_id' => $target->getKey(),
                'roleable_type' => $target->getMorphClass(),
            ])
                ->with('assignable')
                ->get();
        });
    }

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

    public function hasAnyRoleOn(AssignableEntity $user, RoleableEntity $target): bool
    {
        return Roster::where([
            'assignable_id' => $user->getKey(),
            'assignable_type' => $user->getMorphClass(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->exists();
    }

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

    public function isAtLeastOn(AssignableEntity $user, RoleContract $role, RoleableEntity $target): bool
    {
        $userRole = $this->getRoleOn($user, $target);

        if (! $userRole) {
            return false;
        }

        return $userRole->isAtLeast($role);
    }

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

    public function clearCache(RoleableEntity $target, ?AssignableEntity $user = null): void
    {
        // Forget participants cache
        Cache::forget($this->generateParticipantsCacheKey($target));

        if ($user) {
            // Forget assignable entity cache for this specific type
            Cache::forget($this->generateAssignedEntitiesCacheKey($user, $target->getMorphClass()));

            // Clear all role check caches for this user-target combination
            $this->clearRoleCheckCaches($user, $target);

            // Clear assigned entities cache for all types for this user
            $this->clearAllAssignedEntitiesCaches($user);
        } else {
            // If no user specified, clear all participant-related caches
            $this->clearAllParticipantRelatedCaches($target);
        }
    }

    public function generateParticipantsCacheKey(RoleableEntity $target): string
    {
        $prefix = config('porter.cache.key_prefix', 'porter');

        return "{$prefix}:participants:{$target->getMorphClass()}:{$target->getKey()}";
    }

    public function bulkClearCache(Collection $targets): void
    {
        $targets->each(function (RoleableEntity $target) {
            $this->clearCache($target);
        });
    }

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
                $cacheKey = $this->generateRoleCheckCacheKeyByEncryptedKey($user, $target, $encryptedKey);
                Cache::forget($cacheKey);
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

    private function performRoleCheck(AssignableEntity $user, RoleableEntity $target, string $encryptedKey): bool
    {
        if (! $this->shouldCache()) {
            return $this->executeRoleCheck($user, $target, $encryptedKey);
        }

        $cacheKey = $this->generateRoleCheckCacheKeyByEncryptedKey($user, $target, $encryptedKey);

        return Cache::remember($cacheKey, $this->getCacheTtl('role_check'),
            fn () => $this->executeRoleCheck($user, $target, $encryptedKey)
        );
    }

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

    private function generateRoleCheckCacheKey(AssignableEntity $user, RoleableEntity $target, RoleContract $role): string
    {
        $prefix = config('porter.cache.key_prefix', 'porter');
        $tenantKey = $this->getTenantCacheKey($user, $target);

        return "{$prefix}:role_check{$tenantKey}:{$user->getMorphClass()}:{$user->getKey()}:{$target->getMorphClass()}:{$target->getKey()}:{$role->getName()}";
    }

    private function generateRoleCheckCacheKeyByEncryptedKey(AssignableEntity $user, RoleableEntity $target, string $encryptedKey): string
    {
        $prefix = config('porter.cache.key_prefix', 'porter');
        $tenantKey = $this->getTenantCacheKey($user, $target);

        return "{$prefix}:role_check_key{$tenantKey}:{$user->getMorphClass()}:{$user->getKey()}:{$target->getMorphClass()}:{$target->getKey()}:".hash('sha256', $encryptedKey);
    }

    private function clearAssignableEntityCache(AssignableEntity $user, string $type): void
    {
        Cache::forget($this->generateAssignedEntitiesCacheKey($user, $type));
    }

    private function generateAssignedEntitiesCacheKey(AssignableEntity $target, string $type): string
    {
        $prefix = config('porter.cache.key_prefix', 'porter');

        return "{$prefix}:{$target->getMorphClass()}:{$target->getKey()}_{$type}_entities";
    }

    private function clearRoleCheckCaches(AssignableEntity $user, RoleableEntity $target): void
    {
        $prefix = config('porter.cache.key_prefix', 'porter');

        // Clear role name-based caches
        $baseKey = "{$prefix}:role_check:{$user->getMorphClass()}:{$user->getKey()}:{$target->getMorphClass()}:{$target->getKey()}";
        $commonRoles = ['admin', 'manager', 'editor', 'viewer', 'user', 'guest'];
        foreach ($commonRoles as $roleName) {
            Cache::forget("{$baseKey}:{$roleName}");
        }

        // Role check caches are cleared directly in the transaction methods
    }

    private function clearAllAssignedEntitiesCaches(AssignableEntity $user): void
    {
        $prefix = config('porter.cache.key_prefix', 'porter');

        // Clear assigned entities caches for common entity types
        $commonTypes = ['App\\Models\\User', 'App\\Models\\Project', 'App\\Models\\Organization', 'App\\Models\\Team'];
        foreach ($commonTypes as $type) {
            $cacheKey = "{$prefix}:{$user->getMorphClass()}:{$user->getKey()}_{$type}_entities";
            Cache::forget($cacheKey);
        }
    }

    private function clearAllParticipantRelatedCaches(RoleableEntity $target): void
    {
        // For now, just clear the participants cache
        // In a more comprehensive implementation, we might clear related caches
        Cache::forget($this->generateParticipantsCacheKey($target));
    }

    private function shouldCache(): bool
    {
        return config('porter.cache.enabled', true);
    }

    public function getCacheTtl(string $type): int
    {
        return match ($type) {
            'role_check' => (int) config('porter.cache.role_check_ttl', config('porter.cache.ttl', 1800)),
            'participants' => (int) config('porter.cache.participants_ttl', config('porter.cache.ttl', 3600)),
            'assigned_entities' => (int) config('porter.cache.assigned_entities_ttl', config('porter.cache.ttl', 3600)),
            default => (int) config('porter.cache.ttl', 3600)
        };
    }

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
     */
    private function validateTenantIntegrity(AssignableEntity $user, RoleableEntity $target): void
    {
        if (!config('porter.multitenancy.enabled', false)) {
            return;
        }

        // Special handling when roleable IS the tenant entity
        if ($target instanceof PorterTenantContract) {
            $assignableTenant = $user instanceof PorterAssignableContract ? $user->getCurrentTenantKey() : null;
            $targetTenantKey = $target->getPorterTenantKey(); // Self-reference
            
            // Assignable must belong to the same tenant as the target tenant entity
            if ($assignableTenant !== null && $assignableTenant !== $targetTenantKey) {
                throw TenantIntegrityException::mismatch($assignableTenant, $targetTenantKey);
            }
            
            // If assignable has no tenant but target is a tenant entity, that's also invalid
            if ($assignableTenant === null && $targetTenantKey !== null) {
                throw TenantIntegrityException::assignableWithoutTenant();
            }
            
            return; // Skip normal validation for tenant entities
        }

        // Normal validation for non-tenant roleables
        $assignableTenant = $user instanceof PorterAssignableContract ? $user->getCurrentTenantKey() : null;
        $roleableTenant = $target instanceof PorterRoleableContract ? $target->getPorterTenantKey() : null;

        // Both must have tenant context or both must not have it
        if ($assignableTenant === null && $roleableTenant !== null) {
            throw TenantIntegrityException::assignableWithoutTenant();
        }

        if ($assignableTenant !== null && $roleableTenant === null) {
            throw TenantIntegrityException::roleableWithoutTenant();
        }

        // If both have tenant context, they must match
        if ($assignableTenant !== null && $roleableTenant !== null && $assignableTenant !== $roleableTenant) {
            throw TenantIntegrityException::mismatch($assignableTenant, $roleableTenant);
        }
    }

    /**
     * Get tenant cache key segment for cache scoping.
     */
    private function getTenantCacheKey(AssignableEntity $user, RoleableEntity $target): string
    {
        if (!config('porter.multitenancy.enabled', false) || !config('porter.multitenancy.cache_per_tenant', true)) {
            return '';
        }

        $tenantKey = $user instanceof PorterAssignableContract ? $user->getCurrentTenantKey() : null;
        
        return $tenantKey ? ":t:{$tenantKey}" : '';
    }

    /**
     * Resolve the tenant ID for a role assignment.
     * Handles both regular entities and tenant entities as roleables.
     */
    private function resolveTenantIdForAssignment(AssignableEntity $user, RoleableEntity $target): ?string
    {
        if (!config('porter.multitenancy.enabled', false)) {
            return null;
        }

        // If roleable IS the tenant entity, use its key as tenant_id
        if ($target instanceof PorterTenantContract) {
            return $target->getPorterTenantKey();
        }

        // For regular roleables, get tenant from assignable entity
        return $user instanceof PorterAssignableContract ? $user->getCurrentTenantKey() : null;
    }

    /**
     * Destroy all role assignments for a specific tenant.
     * Cache will self-heal as stale entries expire and new queries return correct results.
     */
    public function destroyTenantRoles(string $tenantKey): int
    {
        if (!config('porter.multitenancy.enabled', false)) {
            throw new DomainException('Multitenancy is not enabled. Cannot destroy tenant roles.');
        }

        $tenantColumn = config('porter.multitenancy.tenant_column', 'tenant_id');
        
        return Roster::where($tenantColumn, $tenantKey)->delete();
    }
}
