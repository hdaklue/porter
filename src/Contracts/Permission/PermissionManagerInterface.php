<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Contracts\Permission;

interface PermissionManagerInterface
{
    /**
     * Check if user can perform action on entity.
     */
    public function can($user, string $action, $entity, array $context = []): bool;

    /**
     * Check if any of the user's roles can perform action on entity type.
     */
    public function canByRoles(array $roles, string $action, string $entityType, $entity = null, array $context = []): bool;

    /**
     * Get all permissions for an entity type.
     */
    public function getPermissions(string $entityType): array;

    /**
     * Reload permissions from JSON files.
     */
    public function reloadPermissions(?string $entityType = null): void;

    /**
     * Validate permission JSON structure.
     */
    public function validatePermissionFile(string $entityType): bool;
}