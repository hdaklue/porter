<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class DoctorCommand extends Command
{
    protected $signature = 'porter:doctor {--fix : Attempt to fix issues automatically}';

    protected $description = 'Validate Porter RBAC setup and check for configuration issues';

    private array $errors = [];

    private array $warnings = [];

    private array $suggestions = [];

    public function handle(): int
    {
        $this->info('🩺 Running Porter RBAC health check...');
        $this->newLine();

        // Run all checks
        $this->checkConfig();
        $this->checkMigrations();
        $this->checkRoles();
        $this->checkRoleDuplicates();
        $this->checkRoleConfiguration();

        // Display results
        $this->displayResults();

        return empty($this->errors) ? Command::SUCCESS : Command::FAILURE;
    }

    private function checkConfig(): void
    {
        $this->info('📄 Checking configuration...');

        $configPath = config_path('porter.php');
        if (! File::exists($configPath)) {
            $this->errors[] = 'Porter config file not found. Run "php artisan porter:install" first.';

            return;
        }

        $config = config('porter');
        if (! $config) {
            $this->errors[] = 'Porter configuration not loaded properly.';

            return;
        }

        // Check required config keys
        $requiredKeys = ['table_names', 'cache', 'security', 'directory', 'namespace'];
        foreach ($requiredKeys as $key) {
            if (! isset($config[$key])) {
                $this->warnings[] = "Missing config key: porter.{$key}";
            }
        }

        // Check if Porter directory exists and has roles
        $porterDir = config('porter.directory');
        if (! is_dir($porterDir)) {
            $this->warnings[] = "Porter directory does not exist: {$porterDir}";
        } else {
            $roleFiles = glob("{$porterDir}/*.php");
            $roleFiles = array_filter($roleFiles, function ($file) {
                return basename($file) !== 'BaseRole.php';
            });
            
            if (empty($roleFiles)) {
                $this->warnings[] = 'No role files found in Porter directory. Run "php artisan porter:install --roles" or "php artisan porter:create" to create roles.';
            }
        }

        $this->info('✅ Configuration checked');
    }

    private function checkMigrations(): void
    {
        $this->info('📊 Checking database migrations...');

        if (! Schema::hasTable('roaster')) {
            $this->errors[] = 'Required table "roaster" not found. Run migrations: "php artisan migrate"';

            return;
        }

        $requiredColumns = ['assignable_type', 'assignable_id', 'roleable_type', 'roleable_id', 'role_key'];
        $missingColumns = [];

        foreach ($requiredColumns as $column) {
            if (! Schema::hasColumn('roaster', $column)) {
                $missingColumns[] = $column;
            }
        }

        if (! empty($missingColumns)) {
            $this->errors[] = 'Missing columns in roaster table: '.implode(', ', $missingColumns);
        } else {
            $this->info('✅ Database structure verified');
        }
    }

    private function checkRoles(): void
    {
        $this->info('🎭 Checking role classes...');

        $porterDir = app_path('Porter');
        if (! File::exists($porterDir)) {
            $this->warnings[] = 'Porter roles directory not found: '.$porterDir;
            $this->suggestions[] = 'Run "php artisan porter:install" to create default roles';

            return;
        }

        $roleFiles = File::glob("{$porterDir}/*.php");
        if (empty($roleFiles)) {
            $this->warnings[] = 'No role classes found in '.$porterDir;
            $this->suggestions[] = 'Create roles with "php artisan porter:create RoleName"';

            return;
        }

        $validRoles = 0;
        foreach ($roleFiles as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            if ($this->validateRoleFile($file, $filename)) {
                $validRoles++;
            }
        }

        $this->info("✅ Found {$validRoles} valid role classes");
    }

    private function validateRoleFile(string $file, string $filename): bool
    {
        $content = File::get($file);

        // Check basic structure
        if (! str_contains($content, 'extends BaseRole')) {
            $this->errors[] = "Role {$filename} does not extend BaseRole";

            return false;
        }

        // Check required methods
        $requiredMethods = ['getName', 'getLevel'];
        foreach ($requiredMethods as $method) {
            if (! str_contains($content, "function {$method}()")) {
                $this->errors[] = "Role {$filename} missing required method: {$method}()";

                return false;
            }
        }

        return true;
    }

    private function checkRoleDuplicates(): void
    {
        $this->info('🔍 Checking for role duplicates...');

        $porterDir = app_path('Porter');
        if (! File::exists($porterDir)) {
            return;
        }

        $roles = $this->extractRoleData($porterDir);

        // Check for duplicate names
        $nameGroups = [];
        foreach ($roles as $role) {
            $nameGroups[$role['name']][] = $role['file'];
        }

        foreach ($nameGroups as $name => $files) {
            if (count($files) > 1) {
                $this->errors[] = "Duplicate role name '{$name}' found in: ".implode(', ', $files);
            }
        }

        // Check for duplicate levels
        $levelGroups = [];
        foreach ($roles as $role) {
            if ($role['level'] !== null) {
                $levelGroups[$role['level']][] = [
                    'name' => $role['name'],
                    'file' => $role['file'],
                ];
            }
        }

        foreach ($levelGroups as $level => $roleData) {
            if (count($roleData) > 1) {
                $names = array_column($roleData, 'name');
                $this->errors[] = "Duplicate role level '{$level}' used by: ".implode(', ', $names);
            }
        }

        if (empty($this->errors)) {
            $this->info('✅ No role duplicates found');
        }
    }

    private function extractRoleData(string $directory): array
    {
        $roles = [];
        $files = File::glob("{$directory}/*.php");

        foreach ($files as $file) {
            $content = File::get($file);
            $filename = pathinfo($file, PATHINFO_FILENAME);

            // Extract name
            $name = null;
            if (preg_match("/return\s+['\"]([^'\"]+)['\"];.*getName/s", $content, $matches)) {
                $name = $matches[1];
            }

            // Extract level
            $level = null;
            if (preg_match('/return\s+(\d+);.*getLevel/s', $content, $matches)) {
                $level = (int) $matches[1];
            }

            $roles[] = [
                'file' => $filename.'.php',
                'name' => $name ?: $filename,
                'level' => $level,
            ];
        }

        return $roles;
    }

    private function checkRoleConfiguration(): void
    {
        $this->info('⚙️ Checking role files...');

        $porterDir = config('porter.directory', app_path('Porter'));

        if (! File::exists($porterDir)) {
            $this->warnings[] = "Porter directory does not exist: {$porterDir}";
            return;
        }

        $fileRoles = File::glob("{$porterDir}/*.php");
        $fileRoles = array_filter($fileRoles, function ($file) {
            return basename($file) !== 'BaseRole.php';
        });

        if (empty($fileRoles)) {
            $this->warnings[] = 'No role files found. Create roles with "php artisan porter:create".';
            return;
        }

        // Validate role files
        foreach ($fileRoles as $roleFile) {
            $content = File::get($roleFile);
            $className = basename($roleFile, '.php');
            
            if (!str_contains($content, 'extends BaseRole')) {
                $this->warnings[] = "Role {$className} does not extend BaseRole";
            }
            
            if (!str_contains($content, 'function getName(') || !str_contains($content, 'function getLevel(')) {
                $this->warnings[] = "Role {$className} missing required getName() or getLevel() methods";
            }
        }

        $this->info('✅ Role files checked');
    }

    private function displayResults(): void
    {
        $this->newLine();

        if (empty($this->errors) && empty($this->warnings)) {
            $this->info('🎉 Perfect! Porter RBAC is properly configured.');

            return;
        }

        if (! empty($this->errors)) {
            $this->error('❌ ERRORS FOUND:');
            foreach ($this->errors as $error) {
                $this->error("  • {$error}");
            }
            $this->newLine();
        }

        if (! empty($this->warnings)) {
            $this->warn('⚠️  WARNINGS:');
            foreach ($this->warnings as $warning) {
                $this->warn("  • {$warning}");
            }
            $this->newLine();
        }

        if (! empty($this->suggestions)) {
            $this->info('💡 SUGGESTIONS:');
            foreach ($this->suggestions as $suggestion) {
                $this->info("  • {$suggestion}");
            }
            $this->newLine();
        }

        if (! empty($this->errors)) {
            $this->error('🚨 Please fix the errors above before using Porter RBAC.');
        } else {
            $this->info('✅ No critical issues found. Porter RBAC should work properly.');
        }
    }
}
