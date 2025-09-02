<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Validators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RoleValidator
{
    /**
     * Validate and normalize a role name to PascalCase
     */
    public static function normalizeName(string $name): string
    {
        return Str::studly($name);
    }

    /**
     * Check if a role name already exists
     */
    public static function nameExists(string $name, string $porterDir): bool
    {
        $existingRoles = self::getExistingRoles($porterDir);

        return isset($existingRoles['names'][$name]);
    }

    /**
     * Check if a role level would conflict after pending updates
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
        return ! empty(trim($description));
    }

    /**
     * Get all existing roles from the Porter directory
     */
    public static function getExistingRoles(string $directory): array
    {
        $roles = ['names' => [], 'levels' => []];

        if (! File::exists($directory)) {
            return $roles;
        }

        $files = File::glob("{$directory}/*.php");

        foreach ($files as $file) {
            $content = File::get($file);
            $filename = pathinfo($file, PATHINFO_FILENAME);

            // Extract level from file content
            if (preg_match("/function getLevel\(\)[^{]*{\s*return\s+(\d+);/s", $content, $matches)) {
                $level = (int) $matches[1];
                $roles['names'][$filename] = $file;
                $roles['levels'][$level] = $filename;
            }
        }

        return $roles;
    }

    /**
     * Calculate the new level for a role based on creation mode
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
                if (! $targetRole) {
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
                    // Create new role at the same level as selected role
                    $newRoleLevel = $targetLevel;

                    // Push the selected role and all roles above it up by 1
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

                    // Check if newRoleLevel is already taken
                    if (isset($existingRoles['levels'][$newRoleLevel])) {
                        // Need to shift roles up: all roles with level >= newRoleLevel get incremented
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
     * Get available creation mode options based on existing roles
     */
    public static function getCreationModeOptions(string $porterDir): array
    {
        $existingRoles = self::getExistingRoles($porterDir);

        if (empty($existingRoles['levels'])) {
            // No existing roles - only show simple options
            return [
                'lowest' => 'Create the first role (Level 1)',
                'highest' => 'Create the first role (Level 1)',
            ];
        }

        // Show all options with current role context
        $lowestLevel = min(array_keys($existingRoles['levels']));
        $highestLevel = max(array_keys($existingRoles['levels']));

        return [
            'lowest' => 'Create at lowest level (Level 1, push all roles up)',
            'highest' => 'Create at highest level (Level '.($highestLevel + 1).')',
            'lower' => 'Create below an existing role',
            'higher' => 'Create above an existing role',
        ];
    }

    /**
     * Get role names for selection in lower/higher modes
     */
    public static function getSelectableRoles(string $porterDir): array
    {
        $existingRoles = self::getExistingRoles($porterDir);

        return array_keys($existingRoles['names']);
    }
}
