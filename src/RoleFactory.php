<?php

declare(strict_types=1);

namespace Hdaklue\Porter;

use Hdaklue\Porter\Contracts\RoleContract;
use Hdaklue\Porter\Roles\BaseRole;
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
     * Cache for file existence checks to avoid repeated file system access.
     */
    private static array $fileExistsCache = [];

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

        // Handle null config values
        if (! $porterDir || ! $namespace) {
            throw new InvalidArgumentException('Porter directory or namespace not configured.');
        }

        // Use cached file check to avoid repeated file system access
        $filePath = "{$porterDir}/{$roleName}.php";

        if (! self::cachedFileExists($filePath)) {
            throw new InvalidArgumentException("Role '{$roleName}' does not exist in Porter directory '{$porterDir}'.");
        }

        // Build the role class name
        $roleClass = "{$namespace}\\{$roleName}";

        // Include the role file if the class doesn't exist
        if (! class_exists($roleClass)) {
            if (file_exists($filePath)) {
                require_once $filePath;
            }

            if (! class_exists($roleClass)) {
                throw new InvalidArgumentException(
                    "Role class '{$roleClass}' not found after including file '{$filePath}'.",
                );
            }
        }

        return new $roleClass();
    }

    /**
     * Get all roles that are lower than the given role.
     *
     * @param  RoleContract  $roleContract  The role to compare against
     * @return \Illuminate\Support\Collection Collection of roles with lower hierarchy level
     */
    public static function getRolesLowerThan(RoleContract $roleContract): \Illuminate\Support\Collection
    {
        return collect(BaseRole::all())->filter(fn (RoleContract $item) => $item->isLowerThan($roleContract));
    }

    /**
     * Get all roles that are higher than the given role.
     *
     * @param  RoleContract  $roleContract  The role to compare against
     * @return \Illuminate\Support\Collection Collection of roles with higher hierarchy level
     */
    public static function getRolesHigherThan(RoleContract $roleContract): \Illuminate\Support\Collection
    {
        return collect(BaseRole::all())->filter(fn (RoleContract $item) => $item->isHigherThan($roleContract));
    }

    /**
     * Get all roles that are lower than or equal to the given role.
     *
     * @param  RoleContract  $roleContract  The role to compare against
     * @return \Illuminate\Support\Collection Collection of roles with lower or equal hierarchy level
     */
    public static function getRolesLowerThanOrEqual(RoleContract $roleContract): \Illuminate\Support\Collection
    {
        return collect(BaseRole::all())->filter(fn (RoleContract $item) => $item->isLowerThanOrEqual($roleContract));
    }

    /**
     * Get all roles that are higher than or equal to the given role.
     *
     * @param  RoleContract  $roleContract  The role to compare against
     * @return \Illuminate\Support\Collection Collection of roles with higher or equal hierarchy level
     */
    public static function getRolesHigherThanOrEqual(RoleContract $roleContract): \Illuminate\Support\Collection
    {
        return collect(BaseRole::all())->filter(fn (RoleContract $item) => $item->isHigherThanOrEqual($roleContract));
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
        $result = [];

        // Handle null config values
        if (! $porterDir || ! $namespace) {
            return $result;
        }

        // Direct file scanning to avoid circular dependency
        if (! is_dir($porterDir)) {
            return $result;
        }

        $files = glob("{$porterDir}/*.php");
        foreach ($files as $filePath) {
            $roleName = pathinfo($filePath, PATHINFO_FILENAME);

            // Skip BaseRole
            if ($roleName === 'BaseRole') {
                continue;
            }

            $roleClass = "{$namespace}\\{$roleName}";

            // Include the role file if class doesn't exist
            if (! class_exists($roleClass)) {
                require_once $filePath;
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
        if ($roleName === 'BaseRole') {
            return false;
        }

        $porterDir = config('porter.directory');

        // Handle null config value
        if (! $porterDir) {
            return false;
        }

        $filePath = "{$porterDir}/{$roleName}.php";

        return self::cachedFileExists($filePath);
    }

    /**
     * Cached file existence check to avoid repeated file system calls.
     * In development/testing environments, caching is disabled for dynamic role discovery.
     */
    private static function cachedFileExists(string $filePath): bool
    {
        // Skip caching in local/testing environments for better developer experience
        if (app()->environment(['local', 'testing'])) {
            return file_exists($filePath);
        }

        if (! isset(self::$fileExistsCache[$filePath])) {
            self::$fileExistsCache[$filePath] = file_exists($filePath);
        }

        return self::$fileExistsCache[$filePath];
    }

    /**
     * Clear the file existence cache. Useful for testing or when roles are added/removed.
     */
    public static function clearCache(): void
    {
        self::$fileExistsCache = [];
    }
}
