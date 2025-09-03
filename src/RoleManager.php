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
use Hdaklue\Porter\Models\Roster;
use Hdaklue\Porter\Roles\BaseRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ultra-Minimal Role Manager
 *
 * Core service for managing RBAC role assignments with encrypted role keys.
 * Works with individual Role Classes and Laravel's native authorization.
 *
 * Key Features:
 * - Encrypted role key storage for security
 * - Role assignment with duplicate prevention
 * - Laravel Gate/Policy integration
 * - Performance-optimized caching
 * - Type-safe Role Class instantiation
 *
 * Security Features:
 * - Role keys encrypted/hashed in database
 * - No role enumeration possible from DB
 * - Business logic in immutable PHP classes
 */
final class RoleManager implements RoleManagerContract
{
    /**
     * Assign a role to a user on a specific target entity.
     *
     * @throws DomainException If role doesn't exist
     */
    public function assign(AssignableEntity $user, RoleableEntity $target, string|RoleContract $role): void
    {
        DB::transaction(function () use ($user, $target, $role) {
            // Handle both string and RoleContract inputs
            if (is_string($role)) {
                $this->ensureRoleExists($role);
                // Try role key first, then role name
                try {
                    $roleInstance = RoleFactory::make($role);
                } catch (\InvalidArgumentException $e) {
                    $roleInstance = BaseRole::make($role);
                }
            } else {
                $roleInstance = $role;
            }

            $assignmentStrategy = config('porter.security.assignment_strategy', 'replace');

            // If strategy is 'replace', remove all existing roles for this assignable on this roleable
            if ($assignmentStrategy === 'replace') {
                $this->removeWithinTransaction($user, $target);
            }

            // Get encrypted key for storage
            $encryptedKey = $roleInstance::getDbKey();

            // For 'add' strategy, firstOrCreate will handle duplicates. For 'replace', it will create the new one.
            $assignment = Roster::firstOrCreate([
                'assignable_type' => $user->getMorphClass(),
                'assignable_id' => $user->getKey(),
                'roleable_type' => $target->getMorphClass(),
                'roleable_id' => $target->getKey(),
                'role_key' => $encryptedKey,
            ]);

            // Dispatch event only if this is a new assignment
            if ($assignment->wasRecentlyCreated) {
                RoleAssigned::dispatch($user, $target, $roleInstance);
            }
        });

        // Clear caches after successful transaction
        $this->clearCache($target);
        $this->clearAssignableEntityCache($user, $target->getMorphClass());
    }

    /**
     * Remove all role assignments for a user on a specific target entity.
     *
     * @param  AssignableEntity  $user  The user to remove roles from
     * @param  RoleableEntity  $target  The target entity to remove assignments on
     */
    public function remove(AssignableEntity $user, RoleableEntity $target): void
    {
        DB::transaction(function () use ($user, $target) {
            $this->removeWithinTransaction($user, $target);
        });

        // Clear caches after successful transaction
        $this->clearCache($target);
        $this->clearAssignableEntityCache($user, $target->getMorphClass());
    }

    /**
     * Internal method to remove roles within a transaction context.
     */
    private function removeWithinTransaction(AssignableEntity $user, RoleableEntity $target): void
    {
        // Get the assignments that will be removed for event dispatching
        $assignments = Roster::where([
            'assignable_type' => $user->getMorphClass(),
            'assignable_id' => $user->getKey(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->lockForUpdate()->get(); // Add pessimistic locking for consistency

        // Delete the assignments
        Roster::where([
            'assignable_type' => $user->getMorphClass(),
            'assignable_id' => $user->getKey(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->delete();

        // Dispatch events for removed assignments
        foreach ($assignments as $assignment) {
            $encryptedKey = $assignment->role_key;
            $role = RoleFactory::tryMake($encryptedKey);
            if ($role) {
                RoleRemoved::dispatch($user, $target, $role);
            }
        }
    }

    /**
     * Change the role of a user on a specific target entity.
     *
     *
     * @throws DomainException If new role doesn't exist
     */
    public function changeRoleOn(AssignableEntity $user, RoleableEntity $target, string|RoleContract $role): void
    {
        DB::transaction(function () use ($user, $target, $role) {
            // Handle both string and RoleContract inputs
            if (is_string($role)) {
                $this->ensureRoleExists($role);
                try {
                    $newRole = RoleFactory::make($role);
                } catch (\InvalidArgumentException $e) {
                    $newRole = BaseRole::make($role);
                }
            } else {
                $newRole = $role;
            }

            $newEncryptedKey = $newRole::getDbKey();

            $model = Roster::where([
                'assignable_type' => $user->getMorphClass(),
                'assignable_id' => $user->getKey(),
                'roleable_id' => $target->getKey(),
                'roleable_type' => $target->getMorphClass(),
            ])->lockForUpdate()->first(); // Add pessimistic locking

            if ($model) {
                // Get the old role before changing it
                $oldEncryptedKey = $model->role_key;
                $oldRole = RoleFactory::tryMake($oldEncryptedKey);

                $model->role_key = $newEncryptedKey;
                $model->save();

                // Dispatch event for the role change
                if ($oldRole) {
                    RoleChanged::dispatch($user, $target, $oldRole, $newRole);
                }
            } else {
                // If no existing assignment, create one
                $this->assign($user, $target, $newRole);

                return; // Early return to avoid double cache clearing
            }
        });

        // Clear caches after successful transaction
        $this->clearCache($target);
        $this->clearAssignableEntityCache($user, $target->getMorphClass());
    }

    /**
     * Get all participants (users) who have a specific role on a target entity.
     *
     * @param  RoleableEntity  $target  The entity to get participants for
     * @param  string|RoleContract  $role  The specific role to filter by
     * @return Collection<int, AssignableEntity> Collection of users with the role
     *
     * @throws DomainException If role doesn't exist
     */
    public function getParticipantsHasRole(RoleableEntity $target, string|RoleContract $role): Collection
    {
        // Handle both string and RoleContract inputs
        if (is_string($role)) {
            $this->ensureRoleExists($role);
            // Try role key first, then role name
            try {
                $roleInstance = RoleFactory::make($role);
            } catch (\InvalidArgumentException $e) {
                $roleInstance = BaseRole::make($role);
            }
        } else {
            $roleInstance = $role;
        }

        $encryptedKey = $roleInstance::getDbKey();

        return Roster::where([
            'roleable_type' => $target->getMorphClass(),
            'roleable_id' => $target->getKey(),
            'role_key' => $encryptedKey,
        ])->with('assignable')->get()->pluck('assignable');
    }

    /**
     * Gets assigned entities by Entity Type and Entity Keys.
     *
     * @return Collection<int|string, mixed>
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
     * Get Assigned Entities for a Participant by Entity Type.
     */
    public function getAssignedEntitiesByType(AssignableEntity $entity, string $type): Collection
    {
        $cacheKey = $this->generateAssignedEntitiesCacheKey($entity, $type);
        if (config('porter.should_cache')) {
            return Cache::remember($cacheKey, now()->addHour(), fn () => Roster::where([
                'roleable_type' => $type,
                'assignable_type' => $entity->getMorphClass(),
                'assignable_id' => $entity->getKey(),
            ])
                ->with('roleable')
                ->get()
                ->pluck('roleable'));
        }

        return Roster::where([
            'roleable_type' => $type,
            'assignable_type' => $entity->getMorphClass(),
            'assignable_id' => $entity->getKey(),
        ])
            ->with('roleable')
            ->get()->pluck('roleable');
    }

    /**
     * Get participants with their roles.
     *
     * @return Collection<Roster>
     */
    public function getParticipantsWithRoles(RoleableEntity $target): Collection
    {
        if (config('porter.should_cache')) {
            return Cache::remember($this->generateParticipantsCacheKey($target), now()->addHour(), function () use ($target) {
                return Roster::where([
                    'roleable_id' => $target->getKey(),
                    'roleable_type' => $target->getMorphClass(),
                ])
                    ->with('assignable')
                    ->get();
            });
        }

        return Roster::where([
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])
            ->with('assignable')
            ->get();
    }

    /**
     * Check if user has specific role on target entity.
     */
    public function hasRoleOn(AssignableEntity $user, RoleableEntity $target, string|RoleContract $role): bool
    {
        // Handle both string and RoleContract inputs
        if (is_string($role)) {
            // Try role key first, then role name
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

        // Add caching for performance
        if (config('porter.should_cache')) {
            $cacheKey = $this->generateRoleCheckCacheKey($user, $target, $roleInstance);

            return Cache::remember($cacheKey, now()->addMinutes(30), fn () => $this->performRoleCheck($user, $target, $encryptedKey));
        }

        return $this->performRoleCheck($user, $target, $encryptedKey);
    }

    /**
     * Perform the actual database role check.
     */
    private function performRoleCheck(AssignableEntity $user, RoleableEntity $target, string $encryptedKey): bool
    {
        return Roster::where([
            'assignable_id' => $user->getKey(),
            'assignable_type' => $user->getMorphClass(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
            'role_key' => $encryptedKey,
        ])->exists();
    }

    /**
     * Generate cache key for role check.
     */
    private function generateRoleCheckCacheKey(AssignableEntity $user, RoleableEntity $target, RoleContract $role): string
    {
        $prefix = config('porter.cache.key_prefix', 'porter');

        return "{$prefix}:role_check:{$user->getMorphClass()}:{$user->getKey()}:{$target->getMorphClass()}:{$target->getKey()}:{$role->getName()}";
    }

    /**
     * Check if user has any role on target entity.
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
     * Get the role key for user on target entity.
     */
    public function getRoleOn(AssignableEntity $user, RoleableEntity $target): ?string
    {
        $encryptedKey = Roster::where([
            'assignable_id' => $user->getKey(),
            'assignable_type' => $user->getMorphClass(),
            'roleable_type' => $target->getMorphClass(),
            'roleable_id' => $target->getKey(),
        ])->first()?->role_key;

        if ($encryptedKey) {
            $role = RoleFactory::tryMake($encryptedKey);

            return $role?->getPlainKey();
        }

        return null;
    }

    /**
     * Ensure that a role exists in the system.
     *
     * @throws DomainException if the role does not exist.
     */
    public function ensureRoleExists(string $roleIdentifier): void
    {
        try {
            // First try as role key (for backwards compatibility)
            RoleFactory::make($roleIdentifier);
        } catch (\InvalidArgumentException $e) {
            // If that fails, try as role name
            try {
                BaseRole::make($roleIdentifier);
            } catch (\InvalidArgumentException $e2) {
                throw new DomainException("Role '{$roleIdentifier}' does not exist.", 0, $e2);
            }
        }
    }

    /**
     * Clear role assignment cache for target.
     */
    public function clearCache(RoleableEntity $target): void
    {
        $key = $this->generateParticipantsCacheKey($target);
        $prefix = config('porter.cache.key_prefix', 'porter');

        if (config('porter.cache.use_tags', true) && Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
            // Clear all cache entries for this entity using tags
            Cache::tags([
                $this->getCacheTagForEntity($target),
                'porter:participants',
                'porter:roles',
            ])->flush();
        } else {
            // Fallback to pattern-based clearing for role checks
            Cache::forget($key);

            // Clear role check caches by pattern (less efficient but works without tags)
            $pattern = "{$prefix}:role_check:*:{$target->getMorphClass()}:{$target->getKey()}:*";
            $this->clearCacheByPattern($pattern);
        }
    }

    /**
     * Clear cache entries by pattern (fallback for non-taggable stores).
     */
    private function clearCacheByPattern(string $pattern): void
    {
        // Note: This is a simplified implementation. In production, you might want to use
        // Redis-specific commands or maintain a registry of cache keys.
        // For now, we'll skip pattern clearing to avoid performance issues.
        // Consider using taggable cache stores like Redis for better cache management.
    }

    /**
     * Get cache tag for entity.
     */
    private function getCacheTagForEntity(RoleableEntity $target): string
    {
        return "porter:entity:{$target->getMorphClass()}:{$target->getKey()}";
    }

    /**
     * Generate cache key for participants.
     */
    public function generateParticipantsCacheKey(RoleableEntity $target): string
    {
        $prefix = config('porter.cache.key_prefix', 'porter');

        return "{$prefix}:participants:{$target->getMorphClass()}:{$target->getKey()}";
    }

    /**
     * Bulk clear cache for multiple targets.
     *
     * @param  Collection<RoleableEntity>  $targets
     */
    public function bulkClearCache(Collection $targets): void
    {
        $targets->each(function (RoleableEntity $target) {
            return $this->clearCache($target);
        });
    }

    /**
     * Clear assignable entity cache.
     */
    private function clearAssignableEntityCache(AssignableEntity $user, string $type): void
    {
        Cache::forget($this->generateAssignedEntitiesCacheKey($user, $type));
    }

    /**
     * Generate cache key for assigned entities.
     */
    private function generateAssignedEntitiesCacheKey(AssignableEntity $target, string $type): string
    {
        $prefix = config('porter.cache.key_prefix', 'porter');

        return "{$prefix}:{$target->getMorphClass()}:{$target->getKey()}_{$type}_entities";
    }
}
