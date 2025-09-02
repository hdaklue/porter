<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Validators;

use Hdaklue\Porter\RoleFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RoleValidator
{
    private static array $cache = [];
    private static array $contentHashes = [];

    /**
     * Validate and normalize a role name to PascalCase
     */
    public static function normalizeName(string $name): string
    {
        return Str::studly($name);
    }

    /**
     * Check if a role name already exists - OPTIMIZED
     */
    public static function nameExists(string $name, string $porterDir): bool
    {
        // Use RoleFactory instead of file scanning
        return RoleFactory::existsInPorterDirectory($name);
    }

    /**
     * Check if a role level would conflict after pending updates - OPTIMIZED
     */
    public static function levelConflicts(int $level, string $porterDir, array $rolesToUpdate = []): bool
    {
        $existingRoles = self::getExistingRoles($porterDir);

        // Apply pending updates to get accurate level information
        $updatedLevels = $existingRoles['levels'];
        foreach ($rolesToUpdate as $update) {
            // Remove old level
            if (isset($updatedLevels[$update['old_level']])) {
                unset($updatedLevels[$update['old_level']]);
            }
            // Add new level
            $updatedLevels[$update['new_level']] = $update['name'];
        }

        return isset($updatedLevels[$level]);
    }

    /**
     * Validate that a level is within acceptable range
     */
    public static function isValidLevel(int $level): bool
    {
        return $level >= 1;
    }

    /**
     * Validate a role description
     */
    public static function isValidDescription(string $description): bool
    {
        return !empty(trim($description));
    }

    /**
     * Get all existing roles from the Porter directory - CONTENT-AWARE CACHED
     */
    public static function getExistingRoles(string $directory): array
    {
        // Bypass cache in testing environment to ensure fresh file reads
        if (app()->environment('testing')) {
            return self::getExistingRolesFallback($directory);
        }

        $cacheKey = "porter_roles_" . md5($directory);

        // Get roles directly using file system to avoid circular dependency
        $roles = self::getExistingRolesFallback($directory);

        // Generate content hash for cache invalidation
        $contentHash = md5(serialize($roles));
        
        // Check if cache is still valid (content hasn't changed)
        if (
            isset(self::$cache[$cacheKey]) && 
            isset(self::$contentHashes[$cacheKey]) &&
            self::$contentHashes[$cacheKey] === $contentHash
        ) {
            return self::$cache[$cacheKey];
        }

        // Cache the result with content hash
        self::$cache[$cacheKey] = $roles;
        self::$contentHashes[$cacheKey] = $contentHash;

        return $roles;
    }

    /**
     * Original method kept as fallback - but optimized
     */
    private static function getExistingRolesFallback(string $directory): array
    {
        $roles = ['names' => [], 'levels' => []];

        if (!File::exists($directory)) {
            return $roles;
        }

        // Get all PHP files at once
        $files = File::glob("{$directory}/*.php");
        
        // Filter out BaseRole to avoid processing it
        $files = array_filter($files, fn($file) => basename($file) !== 'BaseRole.php');

        // Process all files in batch
        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            
            // Try to instantiate the role class for accurate level
            try {
                $namespace = config('porter.namespace', 'App\\Porter');
                $className = $namespace . '\\' . $filename;
                
                if (class_exists($className)) {
                    $roleInstance = new $className();
                    $level = $roleInstance->getLevel();
                    
                    $roles['names'][$filename] = $file;
                    $roles['levels'][$level] = $filename;
                    continue;
                }
            } catch (\Exception $e) {
                // Fall back to file parsing
            }

            // Fallback: parse file content
            $content = File::get($file);
            if (preg_match("/function getLevel\(\):\s*int\s*{\s*return\s+(\d+);/s", $content, $matches)) {
                $level = (int) $matches[1];
                $roles['names'][$filename] = $file;
                $roles['levels'][$level] = $filename;
            }
        }

        return $roles;
    }

    /**
     * Calculate the new level for a role based on creation mode - OPTIMIZED
     */
    public static function calculateLevel(string $mode, ?string $targetRole, string $porterDir): array
    {
        $existingRoles = self::getExistingRoles($porterDir);
        $rolesToUpdate = [];

        switch ($mode) {
            case 'lowest':
                if (empty($existingRoles['levels'])) {
                    return [1, []];
                }

                // Create at level 1 and push all existing roles up by 1
                foreach ($existingRoles['levels'] as $level => $name) {
                    $newLevel = $level + 1;
                    $rolesToUpdate[] = [
                        'name' => $name,
                        'old_level' => $level,
                        'new_level' => $newLevel,
                        'file' => $existingRoles['names'][$name],
                    ];
                }

                return [1, $rolesToUpdate];

            case 'highest':
                if (empty($existingRoles['levels'])) {
                    return [1, []];
                }

                $highestLevel = max(array_keys($existingRoles['levels']));
                return [$highestLevel + 1, []];

            case 'lower':
            case 'higher':
                if (!$targetRole) {
                    throw new \InvalidArgumentException("Target role is required for {$mode} mode");
                }

                $targetLevel = null;
                foreach ($existingRoles['levels'] as $level => $name) {
                    if ($name === $targetRole) {
                        $targetLevel = $level;
                        break;
                    }
                }

                if ($targetLevel === null) {
                    throw new \InvalidArgumentException("Target role '{$targetRole}' not found");
                }

                if ($mode === 'lower') {
                    $newRoleLevel = $targetLevel;
                    
                    foreach ($existingRoles['levels'] as $level => $name) {
                        if ($level >= $targetLevel) {
                            $newLevel = $level + 1;
                            $rolesToUpdate[] = [
                                'name' => $name,
                                'old_level' => $level,
                                'new_level' => $newLevel,
                                'file' => $existingRoles['names'][$name],
                            ];
                        }
                    }

                    return [$newRoleLevel, $rolesToUpdate];
                } else { // higher
                    $newRoleLevel = $targetLevel + 1;

                    if (isset($existingRoles['levels'][$newRoleLevel])) {
                        foreach ($existingRoles['levels'] as $level => $name) {
                            if ($level >= $newRoleLevel) {
                                $newLevel = $level + 1;
                                $rolesToUpdate[] = [
                                    'name' => $name,
                                    'old_level' => $level,
                                    'new_level' => $newLevel,
                                    'file' => $existingRoles['names'][$name],
                                ];
                            }
                        }
                    }

                    return [$newRoleLevel, $rolesToUpdate];
                }

            default:
                throw new \InvalidArgumentException("Invalid creation mode: {$mode}");
        }
    }

    /**
     * Get available creation mode options based on existing roles - OPTIMIZED
     */
    public static function getCreationModeOptions(string $porterDir): array
    {
        $existingRoles = self::getExistingRoles($porterDir);

        if (empty($existingRoles['levels'])) {
            return [
                'lowest' => 'Create the first role (Level 1)',
                'highest' => 'Create the first role (Level 1)',
            ];
        }

        $lowestLevel = min(array_keys($existingRoles['levels']));
        $highestLevel = max(array_keys($existingRoles['levels']));

        return [
            'lowest' => 'Create at lowest level (Level 1, push all roles up)',
            'highest' => 'Create at highest level (Level ' . ($highestLevel + 1) . ')',
            'lower' => 'Create below an existing role',
            'higher' => 'Create above an existing role',
        ];
    }

    /**
     * Get role names for selection in lower/higher modes - OPTIMIZED
     */
    public static function getSelectableRoles(string $porterDir): array
    {
        $existingRoles = self::getExistingRoles($porterDir);
        return array_keys($existingRoles['names']);
    }

    /**
     * Clear cache - useful for testing and after role modifications
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::$contentHashes = [];
    }
}