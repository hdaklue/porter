<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ListCommand extends Command
{
    protected $signature = 'porter:list {--detailed : Show detailed information} {--sort=level : Sort by name, level, or file} {--filter= : Filter roles by name pattern}';

    protected $description = 'List all Porter roles with their information';

    public function handle(): int
    {
        $this->info('🎭 Porter Roles Overview');
        $this->newLine();

        $roles = $this->getAllRoles();

        if (empty($roles)) {
            $this->warn('No roles found!');
            $this->info('💡 Create roles with: php artisan porter:create RoleName');
            $this->info('💡 Install default roles with: php artisan porter:install');

            return Command::SUCCESS;
        }

        // Apply filter if specified
        $filter = $this->option('filter');
        if ($filter) {
            $roles = array_filter($roles, fn ($role) => str_contains(strtolower($role['name']), strtolower($filter)) ||
                str_contains(strtolower($role['file']), strtolower($filter))
            );

            if (empty($roles)) {
                $this->warn("No roles match filter: '{$filter}'");

                return Command::SUCCESS;
            }
        }

        // Sort roles
        $this->sortRoles($roles);

        // Display roles
        if ($this->option('detailed')) {
            $this->displayDetailedRoles($roles);
        } else {
            $this->displaySimpleRoles($roles);
        }

        // Display summary
        $this->displaySummary($roles);

        return Command::SUCCESS;
    }

    private function getAllRoles(): array
    {
        $roles = [];
        $porterDir = app_path('Porter');

        if (! File::exists($porterDir)) {
            return [];
        }

        $files = File::glob("{$porterDir}/*.php");

        foreach ($files as $file) {
            $roleData = $this->extractRoleData($file);
            if ($roleData) {
                $roles[] = $roleData;
            }
        }

        return $roles;
    }

    private function extractRoleData(string $file): ?array
    {
        $content = File::get($file);
        $filename = pathinfo($file, PATHINFO_FILENAME);
        $className = "App\\Porter\\{$filename}";

        // Extract name from getName() method
        $name = null;
        if (preg_match("/function getName\(\)[^{]*{\s*return\s+['\"]([^'\"]+)['\"];/s", $content, $matches)) {
            $name = $matches[1];
        }

        // Extract level from getLevel() method
        $level = null;
        if (preg_match("/function getLevel\(\)[^{]*{\s*return\s+(\d+);/s", $content, $matches)) {
            $level = (int) $matches[1];
        }

        // Extract description from getDescription() method
        $description = null;
        if (preg_match("/function getDescription\(\)[^{]*{\s*return\s+['\"]([^'\"]*)['\"];/s", $content, $matches)) {
            $description = $matches[1];
        }

        // Extract label from getLabel() method
        $label = null;
        if (preg_match("/function getLabel\(\)[^{]*{\s*return\s+['\"]([^'\"]*)['\"];/s", $content, $matches)) {
            $label = $matches[1];
        }

        // Check if properly extends BaseRole
        $extendsBase = str_contains($content, 'extends BaseRole');

        // Check if configured in config/porter.php
        $configuredRoles = config('porter.roles', []);
        $isConfigured = in_array($className, $configuredRoles);

        // Validate file structure
        $hasRequiredMethods = str_contains($content, 'function getName(') &&
                             str_contains($content, 'function getLevel(');

        $filesize = File::size($file);
        $lastModified = File::lastModified($file);

        return [
            'file' => $filename.'.php',
            'path' => $file,
            'class' => $className,
            'name' => $name ?: $filename,
            'level' => $level,
            'description' => $description ?: 'No description provided',
            'label' => $label ?: ($description ?: 'No label provided'),
            'extends_base' => $extendsBase,
            'configured' => $isConfigured,
            'valid' => $hasRequiredMethods && $extendsBase,
            'filesize' => $filesize,
            'modified' => date('Y-m-d H:i:s', $lastModified),
        ];
    }

    private function sortRoles(array &$roles): void
    {
        $sortBy = $this->option('sort');

        switch ($sortBy) {
            case 'name':
                usort($roles, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
                break;
            case 'file':
                usort($roles, fn ($a, $b) => strcasecmp($a['file'], $b['file']));
                break;
            case 'level':
            default:
                usort($roles, function ($a, $b) {
                    if ($a['level'] === null && $b['level'] === null) {
                        return 0;
                    }
                    if ($a['level'] === null) {
                        return 1;
                    }
                    if ($b['level'] === null) {
                        return -1;
                    }

                    return $b['level'] <=> $a['level']; // Descending order (highest first)
                });
                break;
        }
    }

    private function displaySimpleRoles(array $roles): void
    {
        $headers = ['Role', 'Level', 'Status', 'Description'];
        $rows = [];

        foreach ($roles as $role) {
            $status = $this->getRoleStatus($role);
            $levelDisplay = $role['level'] !== null ? (string) $role['level'] : '?';

            $rows[] = [
                $role['name'],
                $levelDisplay,
                $status,
                $this->truncateText($role['description'], 50),
            ];
        }

        $this->table($headers, $rows);
    }

    private function displayDetailedRoles(array $roles): void
    {
        foreach ($roles as $index => $role) {
            if ($index > 0) {
                $this->newLine();
            }

            $this->displaySingleRole($role);
        }
    }

    private function displaySingleRole(array $role): void
    {
        $status = $this->getRoleStatus($role);
        $levelDisplay = $role['level'] !== null ? (string) $role['level'] : 'Not set';

        $this->info("🎭 <fg=cyan>{$role['name']}</fg=cyan>");
        $this->line("   Class: {$role['class']}");
        $this->line("   File: {$role['file']}");
        $this->line("   Level: {$levelDisplay}");
        $this->line("   Status: {$status}");
        $this->line("   Description: {$role['description']}");

        if ($role['label'] !== $role['description']) {
            $this->line("   Label: {$role['label']}");
        }

        $this->line('   File Size: '.$this->formatBytes($role['filesize']));
        $this->line("   Modified: {$role['modified']}");

        if (! $role['valid']) {
            $this->line("   <fg=red>⚠️  Issues found - run 'porter:doctor' for details</fg=red>");
        }

        if (! $role['configured']) {
            $this->line('   <fg=yellow>💡 Add to config/porter.php roles array</fg=yellow>');
        }
    }

    private function getRoleStatus(array $role): string
    {
        if (! $role['valid']) {
            return '<fg=red>Invalid</fg=red>';
        }

        if (! $role['configured']) {
            return '<fg=yellow>Not Configured</fg=yellow>';
        }

        return '<fg=green>Active</fg=green>';
    }

    private function displaySummary(array $roles): void
    {
        $this->newLine();
        $this->info('📊 Summary:');

        $total = count($roles);
        $valid = count(array_filter($roles, fn ($r) => $r['valid']));
        $configured = count(array_filter($roles, fn ($r) => $r['configured']));
        $active = count(array_filter($roles, fn ($r) => $r['valid'] && $r['configured']));

        $levels = array_filter(array_column($roles, 'level'));
        $minLevel = ! empty($levels) ? min($levels) : 'N/A';
        $maxLevel = ! empty($levels) ? max($levels) : 'N/A';
        $duplicateLevels = $this->findDuplicateLevels($roles);

        $this->line("  Total Roles: {$total}");
        $this->line("  Valid Files: {$valid}");
        $this->line("  Configured: {$configured}");
        $this->line("  Active: <fg=green>{$active}</fg=green>");
        $this->line("  Level Range: {$minLevel} - {$maxLevel}");

        if (! empty($duplicateLevels)) {
            $this->line('  <fg=red>Duplicate Levels: '.implode(', ', $duplicateLevels).'</fg=red>');
        }

        if ($total > 0 && $active < $total) {
            $this->newLine();
            $this->warn('💡 Run "php artisan porter:doctor" to identify and fix issues');
        }
    }

    private function findDuplicateLevels(array $roles): array
    {
        $levelCounts = [];
        foreach ($roles as $role) {
            if ($role['level'] !== null) {
                $levelCounts[$role['level']] = ($levelCounts[$role['level']] ?? 0) + 1;
            }
        }

        return array_keys(array_filter($levelCounts, fn ($count) => $count > 1));
    }

    private function truncateText(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length - 3).'...' : $text;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        } else {
            return round($bytes / 1048576, 1).' MB';
        }
    }
}
