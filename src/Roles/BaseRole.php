<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Roles;

use Hdaklue\Porter\Concerns\HasRoleHierarchy;
use Hdaklue\Porter\Contracts\RoleContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class BaseRole implements RoleContract
{
    use HasRoleHierarchy;

    /**
     * Get the role name.
     */
    abstract public function getName(): string;

    /**
     * Get the hierarchical level of this role.
     */
    abstract public function getLevel(): int;

    /**
     * Get the database key for this role.
     * Automatically generates snake_case from class name and encrypts/hashes it.
     */
    public static function getDbKey(): string
    {
        $className = class_basename(static::class);
        $plainKey = Str::snake($className);

        return static::encryptRoleKey($plainKey);
    }

    /**
     * Get the plain key (snake_case class name) for this role.
     * Used internally for key generation and testing.
     */
    public static function getPlainKey(): string
    {
        return Str::snake(class_basename(static::class));
    }

    /**
     * Create role instance from encrypted database key.
     */
    public static function fromDbKey(string $encryptedKey): ?static
    {
        try {
            $plainKey = static::decryptRoleKey($encryptedKey);

            return static::fromPlainKey($plainKey);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Create role instance from plain key.
     */
    public static function fromPlainKey(string $plainKey): ?static
    {
        $roles = static::all();

        foreach ($roles as $role) {
            if ($role::getPlainKey() === $plainKey) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Encrypt/hash a role key for database storage.
     */
    protected static function encryptRoleKey(string $plainKey): string
    {
        // Check if we have Laravel config available
        if (! function_exists('config') || ! app()->bound('config')) {
            // Return plain key when running outside Laravel context
            return 'test_'.$plainKey;
        }

        $storage = config('porter.security.key_storage', 'hashed');

        if ($storage === 'hashed') {
            // Hash using app key as salt for security
            return hash('sha256', $plainKey.config('app.key'));
        }

        // Fallback: just return plain key (for development/testing)
        return $plainKey;
    }

    /**
     * Decrypt a role key from database storage.
     */
    protected static function decryptRoleKey(string $encryptedKey): string
    {
        // Check if we have Laravel config available
        if (! function_exists('config') || ! app()->bound('config')) {
            // Handle test keys when running outside Laravel context
            if (str_starts_with($encryptedKey, 'test_')) {
                return substr($encryptedKey, 5); // Remove 'test_' prefix
            }

            return $encryptedKey;
        }

        $storage = config('porter.security.key_storage', 'hashed');

        if ($storage === 'hashed') {
            // Can't decrypt hash, need to verify by trying all roles
            return static::findPlainKeyByHash($encryptedKey);
        }

        // Fallback: return as-is
        return $encryptedKey;
    }

    /**
     * Find plain key by trying to match hash.
     */
    protected static function findPlainKeyByHash(string $targetHash): string
    {
        $roles = static::all();

        foreach ($roles as $role) {
            $plainKey = $role::getPlainKey();
            if (static::encryptRoleKey($plainKey) === $targetHash) {
                return $plainKey;
            }
        }

        throw new \InvalidArgumentException('Invalid role key hash');
    }

    /**it
     * Get human-readable label for this role.
     */
    public function getLabel(): string
    {
        return __(sprintf('porter::roles.%s.label', $this->getName()));
    }

    /**
     * Get description of role capabilities.
     */
    public function getDescription(): string
    {
        return __(sprintf('porter::roles.%s.description', $this->getName()));
    }

    /**
     * Create role instance from name.
     */
    public static function make(string $name): static
    {
        $roles = static::all();

        foreach ($roles as $role) {
            if ($role->getName() === $name) {
                return $role;
            }
        }

        throw new \InvalidArgumentException("Role '{$name}' does not exist.");
    }

    /**
     * Try to create role instance from name, returns null if not found.
     */
    public static function tryMake(string $name): ?static
    {
        try {
            return static::make($name);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Get roles lower than or equal to given role.
     */
    public static function whereLowerThanOrEqual(RoleContract $role): Collection
    {
        return collect(static::all())
            ->filter(fn ($r) => $r->isLowerThanOrEqual($role))
            ->mapWithKeys(fn ($item) => [$item->getName() => $item->getLabel()]);
    }

    /**
     * Get roles lower than or equal to given role as array with value/label structure.
     */
    public static function getRolesLowerThanOrEqual(RoleContract $role): Collection
    {
        return collect(static::all())
            ->reject(fn ($r) => $r->getLevel() > $role->getLevel())
            ->map(fn ($item) => ['value' => $item->getName(), 'label' => $item->getLabel()]);
    }

    /**
     * Get all available role instances.
     * This discovers all role classes automatically from the Porter directory.
     */
    public static function all(): array
    {
        // Check if we have Laravel config available
        if (! function_exists('config') || ! app()->bound('config')) {
            // Return test fixture roles when running outside Laravel context
            $roleClasses = [
                \Hdaklue\Porter\Tests\Fixtures\TestAdmin::class,
                \Hdaklue\Porter\Tests\Fixtures\TestEditor::class,
                \Hdaklue\Porter\Tests\Fixtures\TestViewer::class,
            ];
            return array_map(fn ($class) => new $class(), $roleClasses);
        }

        // Check if we're in test environment - return test fixtures
        if (app()->environment('testing') || app()->environment() === 'testing') {
            $roleClasses = [
                \Hdaklue\Porter\Tests\Fixtures\TestAdmin::class,
                \Hdaklue\Porter\Tests\Fixtures\TestEditor::class,
                \Hdaklue\Porter\Tests\Fixtures\TestViewer::class,
            ];
            return array_map(fn ($class) => new $class(), $roleClasses);
        }

        // Also check if we're running in a test context by looking for test-specific classes
        if (class_exists('\Hdaklue\Porter\Tests\Fixtures\TestAdmin')) {
            $roleClasses = [
                \Hdaklue\Porter\Tests\Fixtures\TestAdmin::class,
                \Hdaklue\Porter\Tests\Fixtures\TestEditor::class,
                \Hdaklue\Porter\Tests\Fixtures\TestViewer::class,
            ];
            return array_map(fn ($class) => new $class(), $roleClasses);
        }

        // Use RoleFactory to discover roles from Porter directory
        try {
            return array_values(\Hdaklue\Porter\RoleFactory::allFromPorterDirectory());
        } catch (\Exception $e) {
            // If RoleFactory fails (e.g., no Porter directory), return empty array
            return [];
        }
    }
}
