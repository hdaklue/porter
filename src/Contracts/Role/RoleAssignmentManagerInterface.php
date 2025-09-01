<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Contracts\Role;

use DomainException;
use Hdaklue\LaraRbac\Enums\Role\RoleEnum;
use Hdaklue\LaraRbac\Models\RoleableHasRole;
use Hdaklue\LaraRbac\Models\Role;
use Illuminate\Support\Collection;

interface RoleAssignmentManagerInterface
{
    /**
     * Assign a role to a user or assignable entity on a target.
     */
    public function assign(AssignableEntity $user, RoleableEntity $target, string|RoleEnum $role): void;

    /**
     * Revoke a role from a user or assignable entity on a target.
     */
    public function remove(AssignableEntity $user, RoleableEntity $target): void;

    /**
     * Determine if the user/entity has a specific role on the target.
     */
    public function hasRoleOn(AssignableEntity $user, RoleableEntity $target, string|RoleEnum $role): bool;

    /**
     * Determine if the user/entity has any role on the target.
     */
    public function hasAnyRoleOn(AssignableEntity $user, RoleableEntity $target): bool;

    /**
     * Get all users/entities assigned a specific role on the target.
     *
     *
     * @return Collection<AssignableEntity>
     */
    public function getParticipantsHasRole(RoleableEntity $target, string|RoleEnum $role): Collection;

    /**
     * Get all users/entities assigned to the target, regardless of role.
     *
     *
     * @return Collection<RoleableHasRole>
     */
    public function getParticipantsWithRoles(RoleableEntity $target): Collection;

    /**
     * Get all users/entities assigned to the target.
     *
     *
     * @return Collection<RoleableHasRole>
     */
    public function getParticipants(RoleableEntity $target): Collection;

    /**
     * Clear the role assignment cache for a specific user and target.
     */
    public function clearCache(RoleableEntity $target);

    /**
     * Summary of bulkClearCache.
     *
     * @param  Collection<RoleableEntity>  $targets
     */
    public function bulkClearCache(Collection $targets);

    public function getRoleOn(AssignableEntity $user, RoleableEntity $target): ?Role;

    public function getAssignedEntitiesByType(AssignableEntity $entity, string $type): Collection;

    /**
     * Summary of getAssignedEntitiesByKeysByType.
     *
     * @return Collection<int|string, mixed>
     */
    public function getAssignedEntitiesByKeysByType(AssignableEntity $target, array $keys, string $type): Collection;

    public function changeRoleOn(AssignableEntity $user, RoleableEntity $target, string|RoleEnum $role);

    /**
     * Generate a cache key for a role assignment.
     */
    public function generateParticipantsCacheKey(RoleableEntity $target): string;

    /**
     * Ensure that a role exists in the system.
     *
     * @throws DomainException if the role does not exist.
     */
    public function ensureRoleExists(string|RoleEnum $roleName): Role;
}