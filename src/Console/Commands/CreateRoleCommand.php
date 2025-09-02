<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CreateRoleCommand extends Command
{
    protected $signature = 'porter:create {name? : The role name} {--level= : The role level (1-10)} {--description= : The role description}';

    protected $description = 'Create a new Porter role class';

    public function handle(): int
    {
        $name = $this->argument('name') ?: $this->askForRoleName();
        $level = $this->option('level') ? (int) $this->option('level') : $this->askForRoleLevel();
        $description = $this->option('description') ?: $this->askForRoleDescription($name);

        // Validate inputs
        if (!$this->validateInputs($name, $level, $description)) {
            return Command::FAILURE;
        }

        // Check for duplicates
        if (!$this->checkForDuplicates($name, $level)) {
            return Command::FAILURE;
        }

        // Create the role file
        $this->createRoleFile($name, $level, $description);

        $this->info("✅ Role '{$name}' created successfully!");
        $this->info("📁 Location: " . app_path("Porter/{$name}.php"));
        $this->info("🔢 Level: {$level}");
        $this->info("📝 Description: {$description}");
        $this->info("🔑 Key: " . $this->generateRoleKey($name));
        
        $this->newLine();
        $this->info("Don't forget to:");
        $this->info("1. Add the role to your config/porter.php roles array");
        $this->info("2. Run 'php artisan porter:doctor' to validate your setup");

        return Command::SUCCESS;
    }

    private function askForRoleName(): string
    {
        do {
            $name = $this->ask('What is the role name? (e.g., Admin, Manager, Editor)');
            
            if (empty($name)) {
                $this->error('Role name is required.');
                continue;
            }

            if (!$this->isValidRoleName($name)) {
                $this->error('Role name must be a valid PHP class name (PascalCase, letters only).');
                continue;
            }

            break;
        } while (true);

        return ucfirst($name);
    }

    private function askForRoleLevel(): int
    {
        do {
            $level = $this->ask('What is the role level? (1-10, where 10 is highest privilege)');
            
            if (!is_numeric($level)) {
                $this->error('Role level must be a number.');
                continue;
            }

            $level = (int) $level;
            
            if ($level < 1 || $level > 10) {
                $this->error('Role level must be between 1 and 10.');
                continue;
            }

            break;
        } while (true);

        return $level;
    }

    private function askForRoleDescription(string $name): string
    {
        $defaultDescription = "User with {$name} role privileges";
        
        return $this->ask("What is the role description?", $defaultDescription);
    }

    private function validateInputs(string $name, int $level, string $description): bool
    {
        // Validate name
        if (!$this->isValidRoleName($name)) {
            $this->error("Invalid role name: '{$name}'. Must be a valid PHP class name.");
            return false;
        }

        // Validate level
        if ($level < 1 || $level > 10) {
            $this->error("Invalid role level: {$level}. Must be between 1 and 10.");
            return false;
        }

        // Validate description
        if (empty(trim($description))) {
            $this->error("Role description cannot be empty.");
            return false;
        }

        return true;
    }

    private function isValidRoleName(string $name): bool
    {
        // Must be valid PHP class name (PascalCase, letters only, no numbers or special chars)
        return preg_match('/^[A-Z][A-Za-z]*$/', $name);
    }

    private function checkForDuplicates(string $name, int $level): bool
    {
        $porterDir = app_path('Porter');
        $existingRoles = $this->getExistingRoles($porterDir);

        // Check for duplicate name
        if (isset($existingRoles['names'][$name])) {
            $this->error("❌ Role name '{$name}' already exists!");
            $this->info("Existing role location: " . $existingRoles['names'][$name]);
            return false;
        }

        // Check for duplicate level
        if (isset($existingRoles['levels'][$level])) {
            $this->error("❌ Role level '{$level}' is already used by role: " . $existingRoles['levels'][$level]);
            $this->info("Each role must have a unique level. Choose a different level.");
            return false;
        }

        return true;
    }

    private function getExistingRoles(string $directory): array
    {
        $roles = ['names' => [], 'levels' => []];

        if (!File::exists($directory)) {
            return $roles;
        }

        $files = File::glob("{$directory}/*.php");

        foreach ($files as $file) {
            $content = File::get($file);
            $filename = pathinfo($file, PATHINFO_FILENAME);

            // Extract level from file content
            if (preg_match('/return\s+(\d+);.*getLevel/s', $content, $matches)) {
                $level = (int) $matches[1];
                $roles['names'][$filename] = $file;
                $roles['levels'][$level] = $filename;
            }
        }

        return $roles;
    }

    private function createRoleFile(string $name, int $level, string $description): void
    {
        $porterDir = app_path('Porter');
        
        if (!File::exists($porterDir)) {
            File::makeDirectory($porterDir, 0755, true);
        }

        $filepath = "{$porterDir}/{$name}.php";
        $stub = $this->getRoleStub();
        
        $content = str_replace(
            ['{{name}}', '{{level}}', '{{description}}', '{{snake_name}}'],
            [$name, $level, $description, Str::snake($name)],
            $stub
        );

        File::put($filepath, $content);
    }

    private function getRoleStub(): string
    {
        return File::get(__DIR__ . '/../../../resources/stubs/role.stub');
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
}