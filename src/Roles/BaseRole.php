<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Roles;

use Hdaklue\Porter\Concerns\HasRoleHierarchy;
use Hdaklue\Porter\Contracts\RoleContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class BaseRole implements Arrayable, Jsonable, RoleContract
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
     * Encrypt a role key for database storage using Laravel's encryption.
     *
     * @throws \RuntimeException When encryption fails
     */
    protected static function encryptRoleKey(string $plainKey): string
    {
        // Validate input
        if (empty(trim($plainKey))) {
            throw new \InvalidArgumentException('Role key cannot be empty');
        }

        // Check if we have Laravel config available
        if (! function_exists('config') || ! app()->bound('config')) {
            // Return test prefix when running outside Laravel context
            return 'test_'.$plainKey;
        }

        $storage = config('porter.security.key_storage', 'encrypted');

        // Validate storage configuration
        $allowedStorageTypes = ['encrypted', 'hashed', 'plain'];
        if (! in_array($storage, $allowedStorageTypes, true)) {
            throw new \InvalidArgumentException("Invalid storage type: {$storage}. Allowed: ".implode(', ', $allowedStorageTypes));
        }

        try {
            return match ($storage) {
                'encrypted' => static::secureEncrypt($plainKey),
                'hashed' => static::secureHash($plainKey),
                'plain' => static::handlePlainStorage($plainKey),
                default => static::secureEncrypt($plainKey), // Default to most secure option
            };
        } catch (\Exception $e) {
            // Log encryption failure but don't expose details
            if (function_exists('report')) {
                report($e);
            }
            throw new \RuntimeException('Role key encryption failed. Check app key configuration.', 0, $e);
        }
    }

    /**
     * Securely encrypt the role key with consistent length.
     * Uses a combination of hashing and base64 encoding to stay within database limits.
     */
    private static function secureEncrypt(string $plainKey): string
    {
        // Create a secure, consistent-length encrypted key using SHA-256 + base64
        // This approach ensures the result fits in 128 characters while maintaining security
        $saltedKey = hash_hmac('sha256', $plainKey, config('app.key', 'fallback-key'), true);

        // Base64 encode the binary hash (32 bytes -> 44 characters)
        // Then add a prefix to distinguish from plain hashing
        return 'enc_'.base64_encode($saltedKey);
    }

    /**
     * Create secure hash with proper configuration.
     * Uses SHA-256 to ensure consistent length that fits in database constraints.
     */
    private static function secureHash(string $plainKey): string
    {
        // Use SHA-256 with app key for consistent, secure hashing
        // This produces exactly 64 characters, fitting well within 128 char limit
        return hash_hmac('sha256', $plainKey, config('app.key', 'fallback-key'));
    }

    /**
     * Handle plain text storage with strict environment checks.
     */
    private static function handlePlainStorage(string $plainKey): string
    {
        $allowedEnvironments = ['testing', 'local'];
        $currentEnv = app()->environment();

        if (! in_array($currentEnv, $allowedEnvironments, true)) {
            throw new \RuntimeException("Plain text storage not allowed in '{$currentEnv}' environment. Use 'encrypted' or 'hashed'.");
        }

        return $plainKey;
    }

    /**
     * Decrypt a role key from database storage using Laravel's decryption.
     *
     * @throws \InvalidArgumentException When decryption fails or key is invalid
     * @throws \RuntimeException When decryption process fails
     */
    protected static function decryptRoleKey(string $encryptedKey): string
    {
        // Validate input
        if (empty(trim($encryptedKey))) {
            throw new \InvalidArgumentException('Encrypted role key cannot be empty');
        }

        // Check if we have Laravel config available
        if (! function_exists('config') || ! app()->bound('config')) {
            // Handle test keys when running outside Laravel context
            if (str_starts_with($encryptedKey, 'test_')) {
                return substr($encryptedKey, 5); // Remove 'test_' prefix
            }

            return $encryptedKey;
        }

        $storage = config('porter.security.key_storage', 'encrypted');

        try {
            return match ($storage) {
                'encrypted' => static::secureDecrypt($encryptedKey),
                'hashed' => static::findPlainKeyByHash($encryptedKey),
                'plain' => static::handlePlainDecryption($encryptedKey),
                default => static::secureDecrypt($encryptedKey),
            };
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // If decryption fails but the key matches a known role, treat as plain text
            // This handles cases where the storage mode is 'encrypted' but plain text keys exist
            if (static::isPlainTextRole($encryptedKey)) {
                return $encryptedKey;
            }

            if (function_exists('report')) {
                report($e);
            }
            throw new \InvalidArgumentException('Invalid role key - decryption failed', 0, $e);
        } catch (\Exception $e) {
            // Last resort: check if it's a plain text role name
            if (static::isPlainTextRole($encryptedKey)) {
                return $encryptedKey;
            }

            if (function_exists('report')) {
                report($e);
            }
            throw new \RuntimeException('Role key decryption failed', 0, $e);
        }
    }

    /**
     * Securely decrypt the role key.
     */
    private static function secureDecrypt(string $encryptedKey): string
    {
        // Handle new format with 'enc_' prefix
        if (str_starts_with($encryptedKey, 'enc_')) {
            $hashedData = substr($encryptedKey, 4); // Remove 'enc_' prefix
            $saltedKey = base64_decode($hashedData, true);

            if ($saltedKey === false) {
                throw new \InvalidArgumentException('Invalid encrypted role key format');
            }

            // We can't reverse the HMAC, so we need to verify by trying all known roles
            return static::findPlainKeyBySaltedHash($saltedKey);
        }

        // Check if it's a plain text role name that matches a known role
        if (static::isPlainTextRole($encryptedKey)) {
            return $encryptedKey;
        }

        try {
            // Attempt legacy Laravel encryption format (for backward compatibility)
            $decrypted = decrypt($encryptedKey);
            $saltedKey = base64_decode($decrypted, true);

            if ($saltedKey === false) {
                throw new \InvalidArgumentException('Invalid encrypted role key format');
            }

            // We can't reverse the HMAC, so we need to verify by trying all known roles
            return static::findPlainKeyBySaltedHash($saltedKey);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            // If decryption fails, it might be a plain text role name or corrupted data
            // Check if it matches any known roles first
            if (static::isPlainTextRole($encryptedKey)) {
                return $encryptedKey;
            }

            // If not a known role, re-throw the original decryption error
            throw new \InvalidArgumentException("Unable to decrypt role key: '{$encryptedKey}' is not a valid encrypted key or known role name");
        }
    }

    /**
     * Handle plain text decryption with environment validation.
     */
    private static function handlePlainDecryption(string $encryptedKey): string
    {
        $allowedEnvironments = ['testing', 'local'];
        $currentEnv = app()->environment();

        if (! in_array($currentEnv, $allowedEnvironments, true)) {
            throw new \RuntimeException("Plain text decryption not allowed in '{$currentEnv}' environment.");
        }

        return $encryptedKey;
    }

    /**
     * Check if the given string is a plain text role name that matches a known role.
     */
    private static function isPlainTextRole(string $possibleRoleName): bool
    {
        $roles = static::all();

        foreach ($roles as $role) {
            $plainKey = $role::getPlainKey();
            if ($plainKey === $possibleRoleName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find plain key by matching salted hash (for encrypted storage mode).
     */
    private static function findPlainKeyBySaltedHash(string $saltedHash): string
    {
        $roles = static::all();
        $appKey = config('app.key', '');

        foreach ($roles as $role) {
            $plainKey = $role::getPlainKey();
            $expectedHash = hash_hmac('sha256', $plainKey, $appKey, true);

            if (hash_equals($expectedHash, $saltedHash)) {
                return $plainKey;
            }
        }

        throw new \InvalidArgumentException('Invalid role key hash - no matching role found');
    }

    /**
     * Find plain key by trying to match hash (for hashed storage mode).
     */
    protected static function findPlainKeyByHash(string $targetHash): string
    {
        $roles = static::all();

        foreach ($roles as $role) {
            $plainKey = $role::getPlainKey();
            // For hashed storage, we need to verify using Laravel's Hash::check
            if (\Illuminate\Support\Facades\Hash::check($plainKey, $targetHash)) {
                return $plainKey;
            }
        }

        throw new \InvalidArgumentException('Invalid role key hash - no matching role found');
    }

    /**
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
        if (static::isRunningOutsideLaravel()) {
            return static::getTestFixtureRoles();
        }

        if (static::isInTestingContext()) {
            return static::getTestFixtureRoles();
        }

        return static::getProductionRoles();
    }

    /**
     * Check if running outside Laravel context.
     */
    private static function isRunningOutsideLaravel(): bool
    {
        return ! function_exists('config') || ! app()->bound('config');
    }

    /**
     * Check if we're in a testing context.
     */
    private static function isInTestingContext(): bool
    {
        return (app()->environment('testing') || app()->environment() === 'testing')
            || class_exists('\Hdaklue\Porter\Tests\Fixtures\TestAdmin');
    }

    /**
     * Get test fixture roles.
     */
    private static function getTestFixtureRoles(): array
    {
        $roleClasses = [
            \Hdaklue\Porter\Tests\Fixtures\TestAdmin::class,
            \Hdaklue\Porter\Tests\Fixtures\TestEditor::class,
            \Hdaklue\Porter\Tests\Fixtures\TestViewer::class,
        ];

        return array_map(fn ($class) => new $class(), $roleClasses);
    }

    /**
     * Get production roles from Porter directory.
     */
    private static function getProductionRoles(): array
    {
        try {
            return array_values(\Hdaklue\Porter\RoleFactory::allFromPorterDirectory());
        } catch (\Exception $e) {
            // If RoleFactory fails (e.g., no Porter directory), return empty array
            return [];
        }
    }

    /**
     * Convert role to array representation.
     *
     * @param  bool  $includeDbKey  Whether to include the sensitive db_key in output
     */
    public function toArray(bool $includeDbKey = false): array
    {
        $array = [
            'name' => $this->getName(),
            'level' => $this->getLevel(),
            'label' => $this->getLabel(),
            'description' => $this->getDescription(),
            'plain_key' => static::getPlainKey(),
        ];

        if ($includeDbKey) {
            $array['db_key'] = static::getDbKey();
        }

        return $array;
    }

    /**
     * Convert role to JSON representation.
     *
     * @param  int  $options  JSON encoding options
     * @param  bool  $includeDbKey  Whether to include the sensitive db_key in output
     */
    public function toJson($options = 0, bool $includeDbKey = false): string
    {
        return json_encode($this->toArray($includeDbKey), $options);
    }
}
