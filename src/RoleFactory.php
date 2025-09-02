<?php

declare(strict_types=1);

namespace Hdaklue\Porter;

use Hdaklue\Porter\Roles\BaseRole;
use Hdaklue\Porter\Validators\RoleValidator;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Ultra-Minimal Role Factory
 *
 * Creates Role Class instances from encrypted database keys.
 * No database dependencies - pure PHP class instantiation.
 */
final class RoleFactory
{
    /**
     * Create a Role Class instance from role key (plain or encrypted).
     *
     * @param  string  $roleKey  The role key (plain snake_case or encrypted from database)
     * @return BaseRole The instantiated role class
     *
     * @throws InvalidArgumentException If role doesn't exist
     */
    public static function make(string $roleKey): BaseRole
    {
        // Try encrypted key first
        $role = BaseRole::fromDbKey($roleKey);

        // If that fails, try as plain key
        if ($role === null) {
            $role = BaseRole::fromPlainKey($roleKey);
        }

        if ($role === null) {
            throw new InvalidArgumentException("Role '{$roleKey}' does not exist.");
        }

        return $role;
    }

    /**
     * Check if a role exists for the given key (plain or encrypted).
     *
     * @param  string  $roleKey  The role key (plain snake_case or encrypted from database)
     * @return bool True if role exists, false otherwise
     */
    public static function exists(string $roleKey): bool
    {
        // Try encrypted key first, then plain key
        return BaseRole::fromDbKey($roleKey) !== null || BaseRole::fromPlainKey($roleKey) !== null;
    }

    /**
     * Try to create a Role Class instance, returns null if not found.
     *
     * @param  string  $roleKey  The role key (plain snake_case or encrypted from database)
     * @return BaseRole|null The role instance or null
     */
    public static function tryMake(string $roleKey): ?BaseRole
    {
        // Try encrypted key first
        $role = BaseRole::fromDbKey($roleKey);

        // If that fails, try as plain key
        if ($role === null) {
            $role = BaseRole::fromPlainKey($roleKey);
        }

        return $role;
    }

    /**
     * Get all available roles with their plain keys.
     *
     * @return array<string, BaseRole> Array keyed by plain keys
     */
    public static function getAllWithKeys(): array
    {
        $roles = BaseRole::all();
        $result = [];

        foreach ($roles as $role) {
            $plainKey = $role::getPlainKey();
            $result[$plainKey] = $role;
        }

        return $result;
    }

    /**
     * Magic method to create roles dynamically from Porter directory.
     *
     * Usage: RoleFactory::admin() -> Creates Admin role
     *        RoleFactory::projectManager() -> Creates ProjectManager role
     *
     * @param  string  $method  The method name (should be camelCase role name)
     * @param  array  $arguments  Not used, but required for __callStatic signature
     * @return BaseRole The role instance
     *
     * @throws InvalidArgumentException If role doesn't exist
     */
    public static function __callStatic(string $method, array $arguments): BaseRole
    {
        // Convert camelCase method name to PascalCase role name
        $roleName = Str::studly($method);

        // Get configuration
        $porterDir = config('porter.directory');
        $namespace = config('porter.namespace');

        // Try to create role by class name
        $existingRoles = RoleValidator::getExistingRoles($porterDir);

        if (! isset($existingRoles['names'][$roleName])) {
            throw new InvalidArgumentException("Role '{$roleName}' does not exist in Porter directory '{$porterDir}'. Available roles: ".implode(', ', array_keys($existingRoles['names'])));
        }

        // Build the role class name and file path
        $roleClass = "{$namespace}\\{$roleName}";
        $filePath = $existingRoles['names'][$roleName];

        // Include the role file if class doesn't exist
        if (! class_exists($roleClass)) {
            if (file_exists($filePath)) {
                require_once $filePath;
            }

            if (! class_exists($roleClass)) {
                throw new InvalidArgumentException("Role class '{$roleClass}' not found after including file '{$filePath}'.");
            }
        }

        return new $roleClass();
    }

    /**
     * Get all roles from the Porter directory dynamically.
     *
     * @return array<string, BaseRole> Array keyed by role names
     */
    public static function allFromPorterDirectory(): array
    {
        $porterDir = config('porter.directory');
        $namespace = config('porter.namespace');
        $existingRoles = RoleValidator::getExistingRoles($porterDir);
        $result = [];

        foreach ($existingRoles['names'] as $roleName => $filePath) {
            $roleClass = "{$namespace}\\{$roleName}";

            // Include the role file if class doesn't exist
            if (! class_exists($roleClass)) {
                if (file_exists($filePath)) {
                    require_once $filePath;
                }
            }

            if (class_exists($roleClass)) {
                $result[$roleName] = new $roleClass();
            }
        }

        return $result;
    }

    /**
     * Check if a role exists in the Porter directory.
     *
     * @param  string  $roleName  The role name (PascalCase)
     * @return bool True if role exists, false otherwise
     */
    public static function existsInPorterDirectory(string $roleName): bool
    {
        $porterDir = config('porter.directory');
        $existingRoles = RoleValidator::getExistingRoles($porterDir);

        return isset($existingRoles['names'][$roleName]);
    }
}
