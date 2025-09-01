<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Services\Role;

use DomainException;
use Hdaklue\LaraRbac\Collections\Role\ParticipantsCollection;
use Hdaklue\LaraRbac\Contracts\Role\AssignableEntity;
use Hdaklue\LaraRbac\Contracts\Role\RoleableEntity;
use Hdaklue\LaraRbac\Contracts\Role\RoleAssignmentManagerInterface;
use Hdaklue\LaraRbac\Contracts\Role\RoleInterface;
use Hdaklue\LaraRbac\Events\Role\EntityAllRolesRemoved;
use Hdaklue\LaraRbac\Events\Role\EntityRoleAssigned;
use Hdaklue\LaraRbac\Events\Role\EntityRoleRemoved;
use Hdaklue\LaraRbac\Models\RoleableHasRole;
use Hdaklue\LaraRbac\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Role Assignment Service - Core service for managing RBAC role assignments.
 *
 * This service provides the primary interface for assigning, removing, and querying
 * roles within the RBAC system. It implements comprehensive caching strategies
 * for optimal performance.
 *
 * Key Features:
 * - Role assignment with duplicate prevention
 * - Role removal and modification
 * - Participant queries with role filtering
 * - Entity assignment tracking
 * - Performance-optimized 1-hour caching
 * - Bulk cache operations for efficiency
 *
 * Performance Characteristics:
 * - Redis-based caching with configurable TTL
 * - Efficient database queries with proper indexing
 * - Bulk operations support for large datasets
 *
 * Cache Strategy:
 * - Participant data cached for 1 hour per entity
 * - Cache keys follow "participants:{morph_class}:{key}" pattern
 * - Automatic cache invalidation on role changes
 * - Configurable caching via 'lararbac.should_cache' setting
 * - Bulk cache clearing for multiple entities
 *
 * Security & Validation:
 * - Strict role validation before assignment
 * - Proper morph type checking for entity types
 * - Domain exceptions for invalid operations
 *
 * @implements RoleAssignmentManagerInterface
 */
final class RoleAssignmentService implements RoleAssignmentManagerInterface
{
    /**
     * Assign a role to a user on a specific target entity.
     *
     * Creates a role assignment between a user and target entity (like a project),
     * ensuring the role exists. Prevents duplicate assignments and automatically
     * clears relevant caches.
     *
     * @param  AssignableEntity  $user  The user to assign the role to
     * @param  RoleableEntity  $target  The target entity (project, etc.)
     * @param  string|RoleInterface  $role  The role to assign (string name or role object)
     *
     * @throws DomainException If role doesn't exist
     */
    public function assign(AssignableEntity $user, RoleableEntity $target, string|RoleInterface $role): void
    {
        $roleToAssign = $this->ensureRoleExists($role);
        
        $assignment = RoleableHasRole::firstOrCreate([
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->getKey(),
            'roleable_type' => $target->getMorphClass(),
            'roleable_id' => $target->getKey(),
            'role_id' => $roleToAssign->getKey(),
        ]);

        // Dispatch event only if this is a new assignment
        if ($assignment->wasRecentlyCreated) {
            EntityRoleAssigned::dispatch($user, $target, $role);
        }

        $this->clearCache($target);
        $this->clearAssignableEntityCache($user, $target->getMorphClass());
    }

    /**
     * Remove all role assignments for a user on a specific target entity.
     *
     * Removes any existing role assignments between the user and target entity.
     * This operation is idempotent and won't fail if no assignments exist.
     * Automatically clears relevant caches after removal.
     *
     * @param  AssignableEntity  $user  The user to remove roles from
     * @param  RoleableEntity  $target  The target entity to remove assignments on
     */
    public function remove(AssignableEntity $user, RoleableEntity $target): void
    {
        // Get the assignments that will be removed for event dispatching
        $assignments = RoleableHasRole::where([
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->getKey(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->with('role')->get();

        // Delete the assignments
        RoleableHasRole::where([
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->getKey(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->delete();

        // Dispatch events for removed assignments
        if ($assignments->count() === 1) {
            $removedRole = $assignments->first()->role;
            EntityRoleRemoved::dispatch($user, $target, $removedRole->name);
        } elseif ($assignments->count() > 1) {
            EntityAllRolesRemoved::dispatch($user, $target);
        }

        $this->clearCache($target);
        $this->clearAssignableEntityCache($user, $target->getMorphClass());
    }

    /**
     * Change the role of a user on a specific target entity.
     *
     * Updates an existing role assignment to a new role. The user must already
     * have a role assignment on the target entity. Validates that the new role
     * exists before making the change.
     *
     * @param  AssignableEntity  $user  The user whose role to change
     * @param  RoleableEntity  $target  The target entity where role is assigned
     * @param  RoleInterface|string  $role  The new role to assign
     * @return void
     *
     * @throws DomainException If new role doesn't exist
     */
    public function changeRoleOn(AssignableEntity $user, RoleableEntity $target, RoleInterface|string $role)
    {
        $roleToAssign = $this->ensureRoleExists($role);

        $model = RoleableHasRole::where([
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->getKey(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->first();

        if ($model) {
            // Get the old role before changing it
            $oldRole = $model->role;
            
            $model->role()->associate($roleToAssign);
            $model->save();

            // Dispatch events for the role change (remove old, assign new)
            EntityRoleRemoved::dispatch($user, $target, $oldRole->name);
            EntityRoleAssigned::dispatch($user, $target, $role);
        }

        $this->clearCache($target);
        $this->clearAssignableEntityCache($user, $target->getMorphClass());
    }

    /**
     * Get all participants (users) who have a specific role on a target entity.
     *
     * Returns a collection of users who have been assigned the specified role
     * on the target entity. Uses caching when enabled for better performance.
     * Results are filtered to only include the exact role requested.
     *
     * @param  RoleableEntity  $target  The entity to get participants for
     * @param  string|RoleInterface  $role  The specific role to filter by
     * @return Collection<int, AssignableEntity> Collection of users with the role
     *
     * @throws DomainException If role doesn't exist
     */
    public function getParticipantsHasRole(RoleableEntity $target, string|RoleInterface $role): Collection
    {
        $roleToCheck = $this->ensureRoleExists($role);

        if (config('lararbac.should_cache')) {
            return $this->getParticipants($target)->filter(function (RoleableHasRole $participant) use ($roleToCheck) {
                return $participant->role->getKey() === $roleToCheck->getKey();
            })->pluck('model');
        }

        return RoleableHasRole::where([
            'roleable_type' => $target->getMorphClass(),
            'roleable_id' => $target->getKey(),
            'role_id' => $roleToCheck->getKey(),
        ])->with('model')->get()->pluck('model');
    }

    /**
     * Gets assigned entities by Entity Type and Entity Keys.
     *
     * @return Collection<int|string, mixed>
     */
    public function getAssignedEntitiesByKeysByType(AssignableEntity $target, array $keys, string $type): Collection
    {
        return RoleableHasRole::query()->where([
            'model_type' => $target->getMorphClass(),
            'model_id' => $target->getKey(),
            'roleable_type' => $type,
        ])
            ->with('roleable')
            ->whereIn('roleable_id', $keys)
            ->get()
            ->pluck('roleable');
    }

    /**
     * Get Assigned Entities for a Participant by Entity Type.
     * $type has To be A valid morphMapResult.
     */
    public function getAssignedEntitiesByType(AssignableEntity $entity, string $type): Collection
    {
        $cacheKey = $this->generateAssignedEntitiesCacheKey($entity, $type);
        if (config('lararbac.should_cache')) {
            return Cache::remember($cacheKey, now()->addHour(), fn () => RoleableHasRole::where([
                'roleable_type' => $type,
                'model_type' => $entity->getMorphClass(),
                'model_id' => $entity->getKey(),
            ])
                ->with('roleable')
                ->get()
                ->pluck('roleable'));
        }

        return RoleableHasRole::where([
            'roleable_type' => $type,
            'model_type' => $entity->getMorphClass(),
            'model_id' => $entity->getKey(),
        ])
            ->with('roleable')
            ->get()->pluck('roleable');
    }

    /**
     * Summary of getParticipantsWithRoles.
     *
     * @return Collection<RoleableHasRole>
     */
    public function getParticipantsWithRoles(RoleableEntity $target): Collection
    {
        if (config('lararbac.should_cache')) {
            return Cache::remember($this->generateParticipantsCacheKey($target), now()->addHour(), function () use ($target) {
                return RoleableHasRole::where([
                    'roleable_id' => $target->getKey(),
                    'roleable_type' => $target->getMorphClass(),
                ])
                    ->with(['model', 'role'])
                    ->get();
            });
        }

        return RoleableHasRole::where([
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])
            ->with(['model', 'role'])
            ->get();
    }

    /**
     * Summary of getParticipantsHasRole.
     *
     * @return Collection<RoleableHasRole>
     */
    public function getParticipants(RoleableEntity $target): ParticipantsCollection
    {
        return new ParticipantsCollection($this->getParticipantsWithRoles($target));
    }

    public function hasRoleOn(AssignableEntity $user, RoleableEntity $target, string|RoleInterface $role): bool
    {
        $roleName = $this->resolveRoleName($role);

        if (config('lararbac.should_cache')) {
            return $this->getParticipants($target)->filter(function (RoleableHasRole $participant) use ($user, $roleName) {
                return $participant->role->name === $roleName &&
                $participant->model->getKey() === $user->getKey() &&
                $participant->model->getMorphClass() === $user->getMorphClass();
            })->isNotEmpty();
        }

        return RoleableHasRole::where([
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->whereHas('role', function ($query) use ($roleName) {
            $query->where('name', $roleName);
        })->exists();
    }

    public function hasAnyRoleOn(AssignableEntity $user, RoleableEntity $target): bool
    {
        if (config('lararbac.should_cache')) {
            return $this->getParticipants($target)->filter(function (RoleableHasRole $participant) use ($user) {
                return $participant->model->getKey() === $user->getKey() &&
                $participant->model->getMorphClass() === $user->getMorphClass();
            })->isNotEmpty();
        }

        return RoleableHasRole::where([
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->exists();
    }

    public function getRoleOn(AssignableEntity $user, RoleableEntity $target): ?Role
    {
        return RoleableHasRole::where([
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
            'roleable_type' => $target->getMorphClass(),
            'roleable_id' => $target->getKey(),
        ])->first()?->getRole();
    }

    public function ensureRoleExists(string|RoleInterface $roleName): Role
    {
        $roleName = $this->resolveRoleName($roleName);
        
        $role = Role::byName($roleName)->first();

        if (!$role) {
            throw new DomainException("Role '{$roleName}' does not exist.");
        }

        return $role;
    }

    /**
     * {@inheritDoc}
     */
    public function clearCache(RoleableEntity $target)
    {
        $key = $this->generateParticipantsCacheKey($target);
        Cache::forget($key);
    }

    public function generateParticipantsCacheKey(RoleableEntity $target): string
    {
        $prefix = config('lararbac.cache.key_prefix', 'lararbac');
        return "{$prefix}:participants:{$target->getMorphClass()}:{$target->getKey()}";
    }

    /** @param Collection<RoleableEntity> $targets */
    public function bulkClearCache(Collection $targets)
    {
        $targets->each(function (RoleableEntity $target) {
            return $this->clearCache($target);
        });
    }

    private function clearAssignableEntityCache(AssignableEntity $user, string $type): void
    {
        Cache::forget($this->generateAssignedEntitiesCacheKey($user, $type));
    }

    private function generateAssignedEntitiesCacheKey(AssignableEntity $target, string $type): string
    {
        $prefix = config('lararbac.cache.key_prefix', 'lararbac');
        return "{$prefix}:{$target->getMorphClass()}:{$target->getKey()}_{$type}_entities";
    }

    private function resolveRoleName(string|RoleInterface $roleName): string
    {
        return $roleName instanceof RoleInterface ? $roleName->getName() : $roleName;
    }
}