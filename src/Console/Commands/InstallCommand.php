<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'porter:install {--force : Overwrite existing files}';

    protected $description = 'Install Porter RBAC package - publish config, run migrations, and create default roles';

    public function handle(): int
    {
        $this->info('🚀 Installing Porter RBAC...');
        $this->newLine();

        // Step 1: Publish config
        $this->publishConfig();

        // Step 2: Publish and run migrations
        $this->publishAndRunMigrations();

        // Step 3: Create default roles directory and files
        $this->createDefaultRoles();

        $this->newLine();
        $this->info('✅ Porter RBAC installed successfully!');
        $this->info('📁 Default roles created in: app/Porter/');
        $this->info('🔧 Config published to: config/porter.php');
        $this->info('📊 Database migrations completed');
        
        $this->newLine();
        $this->info('Next steps:');
        $this->info('1. Update your User model to implement AssignableEntity');
        $this->info('2. Add CanBeAssignedToEntity trait to your User model');
        $this->info('3. Update entities to implement RoleableEntity');
        $this->info('4. Run "php artisan porter:doctor" to validate your setup');

        return Command::SUCCESS;
    }

    private function publishConfig(): void
    {
        $this->info('📄 Publishing configuration...');
        
        $force = $this->option('force');
        $params = ['--provider' => 'Hdaklue\Porter\Providers\PorterServiceProvider', '--tag' => 'porter-config'];
        
        if ($force) {
            $params['--force'] = true;
        }

        $this->call('vendor:publish', $params);
    }

    private function publishAndRunMigrations(): void
    {
        $this->info('📊 Publishing and running migrations...');
        
        // Publish migrations
        $force = $this->option('force');
        $params = ['--provider' => 'Hdaklue\Porter\Providers\PorterServiceProvider', '--tag' => 'porter-migrations'];
        
        if ($force) {
            $params['--force'] = true;
        }

        $this->call('vendor:publish', $params);

        // Run migrations
        if ($this->confirm('Run migrations now?', true)) {
            $this->call('migrate');
        }
    }

    private function createDefaultRoles(): void
    {
        $this->info('🎭 Creating default role classes...');

        $porterDir = app_path('Porter');
        
        if (!File::exists($porterDir)) {
            File::makeDirectory($porterDir, 0755, true);
        }

        $roles = $this->getDefaultRoles();
        
        foreach ($roles as $role) {
            $this->createRoleFile($role['name'], $role['level'], $role['description'], $porterDir);
        }
    }

    private function getDefaultRoles(): array
    {
        return [
            [
                'name' => 'Admin',
                'level' => 6,
                'description' => 'Full system access with all privileges'
            ],
            [
                'name' => 'Manager',
                'level' => 5,
                'description' => 'Management privileges with team oversight'
            ],
            [
                'name' => 'Editor',
                'level' => 4,
                'description' => 'Content editing and publishing privileges'
            ],
            [
                'name' => 'Contributor',
                'level' => 3,
                'description' => 'Content creation and basic editing privileges'
            ],
            [
                'name' => 'Viewer',
                'level' => 2,
                'description' => 'Read-only access to content and data'
            ],
            [
                'name' => 'Guest',
                'level' => 1,
                'description' => 'Limited access for guest users'
            ]
        ];
    }

    private function createRoleFile(string $name, int $level, string $description, string $directory): void
    {
        $filename = "{$name}.php";
        $filepath = "{$directory}/{$filename}";
        
        if (File::exists($filepath) && !$this->option('force')) {
            $this->warn("⚠️  Role {$name} already exists. Use --force to overwrite.");
            return;
        }

        $stub = $this->getRoleStub();
        $content = str_replace(
            ['{{name}}', '{{level}}', '{{description}}'],
            [$name, $level, $description],
            $stub
        );

        File::put($filepath, $content);
        $this->info("✅ Created role: {$name} (Level {$level})");
    }

    private function getRoleStub(): string
    {
        return '<?php

declare(strict_types=1);

namespace App\Porter;

use Hdaklue\Porter\Roles\BaseRole;

final class {{name}} extends BaseRole
{
    public function getName(): string
    {
        return \'{{name}}\';
    }

    public function getLevel(): int
    {
        return {{level}};
    }

    public function getLabel(): string
    {
        return \'{{description}}\';
    }

    public function getDescription(): string
    {
        return \'{{description}}\';
    }
}
';
    }
}