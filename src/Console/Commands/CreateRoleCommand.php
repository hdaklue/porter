<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Console\Commands;

use Hdaklue\Porter\RoleFactory;
use Hdaklue\Porter\Validators\RoleValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateRoleCommand extends Command
{
    protected static string $porterDir = 'Porter';

    protected $signature = 'porter:create {name? : The role name} {--description= : The role description}';

    protected $description = 'Create a new Porter role class';

    public function handle(): int
    {
        RoleValidator::clearCache(); // Ensure a clean cache for the command execution
        RoleFactory::clearCache(); // Clear RoleFactory file existence cache
        $this->info('🎭 Creating a new Porter role...');
        $this->newLine();

        $name = $this->argument('name') ?: $this->askForRoleName();

        // Automatically convert to PascalCase using RoleValidator
        $name = RoleValidator::normalizeName($name);

        // Check for duplicate names early using RoleValidator
        $porterDir = app_path('Porter');
        if (RoleValidator::nameExists($name, $porterDir)) {
            $this->error("❌ Role name '{$name}' already exists!");

            return Command::FAILURE;
        }

        $description = $this->option('description') ?: $this->askForRoleDescription($name);

        // Ask user to select creation mode
        $creationMode = $this->askForCreationMode();

        // Handle hierarchy options that need target role selection
        $targetRole = null;
        if (in_array($creationMode, ['lower', 'higher'])) {
            $availableRoles = RoleValidator::getSelectableRoles($porterDir);
            if (empty($availableRoles)) {
                $this->error('There are no existing roles to reference.');

                return Command::FAILURE;
            }
            $targetRole = $this->choice('Which role do you want to reference?', $availableRoles);
        }

        // Calculate level and roles to update using RoleValidator
        try {
            [$level, $rolesToUpdate] = RoleValidator::calculateLevel($creationMode, $targetRole, $porterDir);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        // Validate inputs using RoleValidator
        if (! RoleValidator::isValidLevel($level)) {
            $this->error("CRITICAL ERROR: Calculated level {$level} is invalid. This should never happen.");

            return Command::FAILURE;
        }

        if (! RoleValidator::isValidDescription($description)) {
            $this->error('Role description cannot be empty.');

            return Command::FAILURE;
        }

        // Check for level conflicts BEFORE updating role files
        if (RoleValidator::levelConflicts($level, $porterDir, $rolesToUpdate)) {
            $this->error("❌ Role level '{$level}' would conflict with existing roles.");

            return Command::FAILURE;
        }

        // Update existing role files if needed
        if (! empty($rolesToUpdate)) {
            $this->info('Updating levels of existing roles...');
            $this->updateRoleLevelsInFiles($rolesToUpdate);
        }

        // Create the role file
        $this->createRoleFile($name, $level, $description);

        $this->info("✅ Role '{$name}' created successfully!");
        $this->info('📁 Location: '.app_path("Porter/{$name}.php"));
        $this->info("🔢 Level: {$level}");
        $this->info("📝 Description: {$description}");
        $this->info('🔑 Key: '.$this->generateRoleKey($name));

        $this->newLine();
        $this->info("Don't forget to:");
        $this->info('1. Add the role to your config/porter.php roles array');
        $this->info("2. Run 'php artisan porter:doctor' to validate your setup");

        return Command::SUCCESS;
    }

    private function handleHierarchyOptions(string $mode): array
    {
        $porterDir = app_path('Porter');
        $existingRoles = $this->getExistingRoles($porterDir);
        $roleNames = array_keys($existingRoles['names']);

        if (empty($roleNames)) {
            $this->error('There are no existing roles to reference.');

            return [Command::FAILURE, []]; // Return failure and empty array
        }

        $targetRoleName = $this->choice('Which role do you want to reference?', $roleNames);
        $targetRoleLevel = null;

        foreach ($existingRoles['levels'] as $l => $n) {
            if ($n === $targetRoleName) {
                $targetRoleLevel = $l;
                break;
            }
        }

        if ($targetRoleLevel === null) {
            $this->error("Could not determine the level of the selected role: {$targetRoleName}");

            return [Command::FAILURE, []]; // Return failure and empty array
        }

        $newRoleLevel = 1; // Initialize to minimum valid level
        $rolesToUpdate = [];

        if ($mode === 'lower') {
            // Create new role at the same level as selected role
            $newRoleLevel = $targetRoleLevel;

            // Push the selected role and all roles above it up by 1
            foreach ($existingRoles['levels'] as $level => $name) {
                if ($level >= $targetRoleLevel) {
                    $newLevel = $level + 1;
                    $rolesToUpdate[] = ['name' => $name, 'old_level' => $level, 'new_level' => $newLevel, 'file' => $existingRoles['names'][$name]];
                }
            }
        } elseif ($mode === 'higher') { // higher
            $newRoleLevel = $targetRoleLevel + 1;

            // Check if newRoleLevel is already taken
            if (isset($existingRoles['levels'][$newRoleLevel])) {
                // Need to shift roles up: all roles with level >= newRoleLevel get incremented
                foreach ($existingRoles['levels'] as $level => $name) {
                    if ($level >= $newRoleLevel) {
                        $newLevel = $level + 1;
                        $rolesToUpdate[] = ['name' => $name, 'old_level' => $level, 'new_level' => $newLevel, 'file' => $existingRoles['names'][$name]];
                    }
                }
            }
            // No roles need updating if the level is free
        }

        // CRITICAL: Final validation - ensure calculated level is positive
        if ($newRoleLevel < 1) {
            $this->error("CRITICAL ERROR: Calculated level {$newRoleLevel} is invalid. Level must be 1 or higher.");

            return [Command::FAILURE, []];
        }

        return [$newRoleLevel, $rolesToUpdate];
    }

    private function askForCreationMode(): string
    {
        $porterDir = app_path(self::$porterDir);
        $options = RoleValidator::getCreationModeOptions($porterDir);

        $this->info('How would you like to position this role?');
        $this->newLine();

        foreach ($options as $key => $option) {
            $this->line("  <fg=cyan>{$option}</>");
        }

        $this->newLine();

        return $this->choice('Select creation mode:', array_keys($options));
    }

    private function handleLowestOption(): array
    {
        $porterDir = app_path('Porter');
        $existingRoles = $this->getExistingRoles($porterDir);

        if (empty($existingRoles['levels'])) {
            // No existing roles, create at level 1
            return [1, []];
        }

        // Create at level 1 and push all existing roles up by 1
        $rolesToUpdate = [];
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
    }

    private function handleHighestOption(): int
    {
        $porterDir = app_path('Porter');
        $existingRoles = $this->getExistingRoles($porterDir);

        if (empty($existingRoles['levels'])) {
            // No existing roles, create at level 1
            return 1;
        }

        // Find the highest level and create one level higher
        $highestLevel = max(array_keys($existingRoles['levels']));

        return $highestLevel + 1;
    }

    private function askForRoleName(): string
    {
        do {
            $name = $this->ask('What is the role name? (e.g., Admin, Manager, Editor)');

            if (empty($name)) {
                $this->error('Role name is required.');

                continue;
            }

            break;
        } while (true);

        return RoleValidator::normalizeName($name);
    }

    private function askForRoleDescription(string $name): string
    {
        $defaultDescription = "User with {$name} role privileges";

        return $this->ask('What is the role description?', $defaultDescription);
    }

    private function validateInputs(string $name, int $level, string $description): bool
    {
        // Validate level (should always be positive since it's calculated)
        if ($level < 1) {
            $this->error("CRITICAL ERROR: Calculated level {$level} is invalid. This should never happen.");

            return false;
        }

        // Validate description
        if (empty(trim($description))) {
            $this->error('Role description cannot be empty.');

            return false;
        }

        return true;
    }

    private function isValidRoleName(string $name): bool
    {
        // Must be valid PHP class name (PascalCase, letters only, no numbers or special chars)
        return (bool) preg_match('/^[A-Z][A-Za-z]*$/', $name);
    }

    private function checkForDuplicates(string $name, int $level, array $rolesToUpdate = []): bool
    {
        $porterDir = app_path('Porter');
        $existingRoles = $this->getExistingRoles($porterDir);

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

        // Check for duplicate level against the updated levels
        if (isset($updatedLevels[$level])) {
            $this->error("❌ Role level '{$level}' is already used by role: ".$updatedLevels[$level]);
            $this->info('Each role must have a unique level. Choose a different level.');

            return false;
        }

        return true;
    }

    private function getExistingRoles(string $directory): array
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

    private function createRoleFile(string $name, int $level, string $description): void
    {
        $porterDir = app_path(self::$porterDir);

        if (! File::exists($porterDir)) {
            File::makeDirectory($porterDir, 0755, true);
        }

        $filepath = "{$porterDir}/{$name}.php";
        $stub = $this->getRoleStub();
        $namespace = config('porter.namespace');

        $content = str_replace(
            ['{{name}}', '{{level}}', '{{description}}', '{{snake_name}}', '{{namespace}}'],
            [$name, $level, $description, Str::snake($name), $namespace],
            $stub,
        );

        File::put($filepath, $content);

        // Clear cache since we've modified role files
        RoleValidator::clearCache();
        RoleFactory::clearCache();
    }

    private function getRoleStub(): string
    {
        return File::get(__DIR__.'/../../../resources/stubs/role.stub');
    }

    private function generateRoleKey(string $name): string
    {
        $plainKey = Str::snake($name);
        $storage = config('porter.security.key_storage', 'hashed');

        if ($storage === 'hashed') {
            return hash('sha256', $plainKey.config('app.key'));
        }

        return $plainKey;
    }

    private function updateRoleLevelsInFiles(array $rolesToUpdate): void
    {
        foreach ($rolesToUpdate as $role) {
            $filepath = $role['file'];
            $oldLevel = $role['old_level'];
            $newLevel = $role['new_level'];

            // CRITICAL: Final safety check before writing to file
            if ($newLevel < 1) {
                $this->error("CRITICAL: Attempted to write invalid level {$newLevel} for role {$role['name']}. Level must be 1 or higher.");

                continue;
            }

            // CRITICAL: Ensure old level is also valid (should never happen, but safety first)
            if ($oldLevel < 1) {
                $this->error("CRITICAL: Found invalid old level {$oldLevel} for role {$role['name']}. Skipping update.");

                continue;
            }

            $content = File::get($filepath);

            // Replace the old level with the new level in the file content
            // This regex is specific to the getLevel() method in the stub
            $content = preg_replace(
                "/function getLevel\(\):\s*int\s*{\s*return\s+{$oldLevel};/s",
                "function getLevel(): int\n    {\n        return {$newLevel};",
                $content,
                1, // Only replace the first occurrence
            );

            if ($content === null) {
                $this->error("Failed to update level for role: {$role['name']}. Regex replacement failed.");

                continue;
            }

            // CRITICAL: Verify the replacement actually happened
            if (! str_contains($content, "return {$newLevel};")) {
                $this->error("CRITICAL: Level replacement verification failed for role {$role['name']}. File not updated.");

                continue;
            }

            File::put($filepath, $content);
            $this->info("   - Updated {$role['name']} from level {$oldLevel} to {$newLevel}");

            // Clear cache after each role update
            RoleValidator::clearCache();
            RoleFactory::clearCache();
        }
    }
}
