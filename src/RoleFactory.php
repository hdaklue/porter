<?php

declare(strict_types=1);

namespace Hdaklue\Porter;

use Hdaklue\Porter\Roles\BaseRole;
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
}
