<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Support;

use Illuminate\Foundation\Application;

/**
 * Laravel Version Compatibility Checker
 *
 * Ensures Porter package works correctly across supported Laravel versions.
 */
final class LaravelCompatibility
{
    /**
     * Supported Laravel major versions.
     */
    public const SUPPORTED_VERSIONS = ['11.x', '12.x'];

    /**
     * Minimum Laravel version required.
     */
    public const MINIMUM_VERSION = '11.0.0';

    /**
     * Check if current Laravel version is supported.
     */
    public static function isSupported(): bool
    {
        $version = self::getLaravelVersion();

        return version_compare($version, self::MINIMUM_VERSION, '>=');
    }

    /**
     * Get current Laravel version.
     */
    public static function getLaravelVersion(): string
    {
        return Application::VERSION;
    }

    /**
     * Get Laravel major version (e.g., "11" or "12").
     */
    public static function getMajorVersion(): string
    {
        $version = self::getLaravelVersion();

        return explode('.', $version)[0];
    }

    /**
     * Check if current Laravel version supports database check constraints.
     */
    public static function supportsCheckConstraints(): bool
    {
        // Check constraints were added in Laravel 10.x but may not be supported by all databases
        // For compatibility, we'll check if the Blueprint class has the check method
        if (! class_exists(\Illuminate\Database\Schema\Blueprint::class)) {
            return false;
        }

        return method_exists(\Illuminate\Database\Schema\Blueprint::class, 'check');
    }

    /**
     * Check if current Laravel version supports readonly properties optimization.
     */
    public static function supportsReadonlyOptimizations(): bool
    {
        // Laravel 11+ has better support for readonly classes and properties
        return version_compare(self::getLaravelVersion(), '11.0.0', '>=');
    }

    /**
     * Get compatibility report.
     */
    public static function getCompatibilityReport(): array
    {
        return [
            'laravel_version' => self::getLaravelVersion(),
            'major_version' => self::getMajorVersion(),
            'is_supported' => self::isSupported(),
            'supports_check_constraints' => self::supportsCheckConstraints(),
            'supports_readonly_optimizations' => self::supportsReadonlyOptimizations(),
            'supported_versions' => self::SUPPORTED_VERSIONS,
            'minimum_version' => self::MINIMUM_VERSION,
        ];
    }

    /**
     * Validate Laravel compatibility and throw exception if not supported.
     *
     * @throws \RuntimeException
     */
    public static function validate(): void
    {
        if (! self::isSupported()) {
            throw new \RuntimeException(sprintf(
                'Porter package requires Laravel %s or higher. Current version: %s. Supported versions: %s',
                self::MINIMUM_VERSION,
                self::getLaravelVersion(),
                implode(', ', self::SUPPORTED_VERSIONS)
            ));
        }
    }

    /**
     * Get feature availability for current Laravel version.
     */
    public static function getFeatureAvailability(): array
    {
        $majorVersion = (int) self::getMajorVersion();

        return [
            'database_check_constraints' => self::supportsCheckConstraints(),
            'readonly_class_optimizations' => self::supportsReadonlyOptimizations(),
            'enhanced_caching' => $majorVersion >= 11,
            'improved_validation' => $majorVersion >= 11,
            'better_middleware_handling' => $majorVersion >= 11,
        ];
    }
}
