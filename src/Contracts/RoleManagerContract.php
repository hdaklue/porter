<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Contracts;

use DomainException;
use Hdaklue\Porter\Models\Roster;
use Illuminate\Support\Collection;

interface RoleManagerContract
{
    /**
     * Assign a role to a user on a specific target entity.
     *
     * @throws DomainException If role doesn't exist
     */
    public function assign(AssignableEntity $user, RoleableEntity $target, string $roleKey): void;

    /**
     * Remove all role assignments for a user on a specific target entity.
     */
    public function remove(AssignableEntity $user, RoleableEntity $target): void;

    /**
     * Change the role of a user on a specific target entity.
     *
     * @throws DomainException If new role doesn't exist
     */
    public function changeRoleOn(AssignableEntity $user, RoleableEntity $target, string $roleKey): void;

    /**
     * Get all participants (users) who have a specific role on a target entity.
     *
     * @return Collection<int, AssignableEntity> Collection of users with the role
     * @throws DomainException If role doesn't exist
     */
    public function getParticipantsHasRole(RoleableEntity $target, string $roleKey): Collection;

    /**
     * Gets assigned entities by Entity Type and Entity Keys.
     *
     * @return Collection<int|string, mixed>
     */
    public function getAssignedEntitiesByKeysByType(AssignableEntity $target, array $keys, string $type): Collection;

    /**
     * Get Assigned Entities for a Participant by Entity Type.
     */
    public function getAssignedEntitiesByType(AssignableEntity $entity, string $type): Collection;

    /**
     * Get participants with their roles.
     *
     * @return Collection<Roster>
     */
    public function getParticipantsWithRoles(RoleableEntity $target): Collection;

    /**
     * Check if user has specific role on target entity.
     */
    public function hasRoleOn(AssignableEntity $user, RoleableEntity $target, string $roleKey): bool;

    /**
     * Check if user has any role on target entity.
     */
    public function hasAnyRoleOn(AssignableEntity $user, RoleableEntity $target): bool;

    /**
     * Get the role key for user on target entity.
     */
    public function getRoleOn(AssignableEntity $user, RoleableEntity $target): ?string;

    /**
     * Ensure that a role exists in the system.
     *
     * @throws DomainException if the role does not exist.
     */
    public function ensureRoleExists(string $roleKey): void;

    /**
     * Clear role assignment cache for target.
     */
    public function clearCache(RoleableEntity $target): void;

    /**
     * Generate cache key for participants.
     */
    public function generateParticipantsCacheKey(RoleableEntity $target): string;

    /**
     * Bulk clear cache for multiple targets.
     *
     * @param Collection<RoleableEntity> $targets
     */
    public function bulkClearCache(Collection $targets): void;
}