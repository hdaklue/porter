<?php

declare(strict_types=1);

namespace Hdaklue\Porter;

use DomainException;
use Hdaklue\Porter\Contracts\AssignableEntity;
use Hdaklue\Porter\Contracts\RoleableEntity;
use Hdaklue\Porter\Contracts\RoleManagerContract;
use Hdaklue\Porter\Events\RoleAssigned;
use Hdaklue\Porter\Events\RoleChanged;
use Hdaklue\Porter\Events\RoleRemoved;
use Hdaklue\Porter\Models\Roster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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
    public function assign(AssignableEntity $user, RoleableEntity $target, string $roleKey): void
    {
        $this->ensureRoleExists($roleKey);

        $assignmentStrategy = config('porter.security.assignment_strategy', 'replace');

        // If strategy is 'replace', remove all existing roles for this assignable on this roleable
        if ($assignmentStrategy === 'replace') {
            $this->remove($user, $target);
        }

        // Get encrypted key for storage
        $role = RoleFactory::make($roleKey);
        $encryptedKey = $role::getDbKey();

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
            RoleAssigned::dispatch($user, $target, $role);
        }

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
        // Get the assignments that will be removed for event dispatching
        $assignments = Roster::where([
            'assignable_type' => $user->getMorphClass(),
            'assignable_id' => $user->getKey(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->get();

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

        $this->clearCache($target);
        $this->clearAssignableEntityCache($user, $target->getMorphClass());
    }

    /**
     * Change the role of a user on a specific target entity.
     *
     *
     * @throws DomainException If new role doesn't exist
     */
    public function changeRoleOn(AssignableEntity $user, RoleableEntity $target, string $roleKey): void
    {
        $this->ensureRoleExists($roleKey);

        $newRole = RoleFactory::make($roleKey);
        $newEncryptedKey = $newRole::getDbKey();

        $model = Roster::where([
            'assignable_type' => $user->getMorphClass(),
            'assignable_id' => $user->getKey(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->first();

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
        }

        $this->clearCache($target);
        $this->clearAssignableEntityCache($user, $target->getMorphClass());
    }

    /**
     * Get all participants (users) who have a specific role on a target entity.
     *
     * @param  RoleableEntity  $target  The entity to get participants for
     * @param  string  $roleKey  The specific role to filter by
     * @return Collection<int, AssignableEntity> Collection of users with the role
     *
     * @throws DomainException If role doesn't exist
     */
    public function getParticipantsHasRole(RoleableEntity $target, string $roleKey): Collection
    {
        $this->ensureRoleExists($roleKey);

        $role = RoleFactory::make($roleKey);
        $encryptedKey = $role::getDbKey();

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
    public function hasRoleOn(AssignableEntity $user, RoleableEntity $target, string $roleKey): bool
    {
        $role = RoleFactory::tryMake($roleKey);
        if (! $role) {
            return false;
        }

        $encryptedKey = $role::getDbKey();

        return Roster::where([
            'assignable_id' => $user->getKey(),
            'assignable_type' => $user->getMorphClass(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
            'role_key' => $encryptedKey,
        ])->exists();
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
    public function ensureRoleExists(string $roleKey): void
    {
        try {
            RoleFactory::make($roleKey);
        } catch (\InvalidArgumentException $e) {
            throw new DomainException("Role '{$roleKey}' does not exist.", 0, $e);
        }
    }

    /**
     * Clear role assignment cache for target.
     */
    public function clearCache(RoleableEntity $target): void
    {
        $key = $this->generateParticipantsCacheKey($target);
        Cache::forget($key);
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
