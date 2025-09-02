<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Clean up any created Porter directory
    $porterDir = app_path('Porter');
    if (File::exists($porterDir)) {
        File::deleteDirectory($porterDir);
    }
});

afterEach(function () {
    // Clean up created Porter directory
    $porterDir = app_path('Porter');
    if (File::exists($porterDir)) {
        File::deleteDirectory($porterDir);
    }
});

test('install command prevents execution in production environment', function () {
    // Temporarily set environment to production
    $originalEnv = app()->environment();
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('porter:install')
        ->expectsOutput('❌ Porter installation is not allowed in production environment!')
        ->expectsOutput('💡 Please run this command in development or staging environment only.')
        ->assertFailed();

    // Restore original environment
    app()->detectEnvironment(fn () => $originalEnv);
});

test('install command works without --roles flag', function () {
    // Set environment to testing (not production)
    app()->detectEnvironment(fn () => 'testing');

    $this->artisan('porter:install')
        ->expectsOutput('🚀 Installing Porter RBAC...')
        ->expectsOutput('📄 Publishing configuration...')
        ->expectsOutput('📊 Publishing and running migrations...')
        ->expectsOutput('📁 Creating Porter directory...')
        ->expectsOutput('✅ Porter RBAC installed successfully!')
        ->expectsConfirmation('Run migrations now?', 'no')
        ->assertSuccessful();

    // Check that Porter directory was created
    $porterDir = app_path('Porter');
    expect(File::exists($porterDir))->toBeTrue();

    // Check that BaseRole.php was created
    $baseRoleFile = "{$porterDir}/BaseRole.php";
    expect(File::exists($baseRoleFile))->toBeTrue();

    // Check that default roles were NOT created
    expect(File::exists("{$porterDir}/Admin.php"))->toBeFalse();
    expect(File::exists("{$porterDir}/Manager.php"))->toBeFalse();
});

test('install command creates default roles with --roles flag', function () {
    // Set environment to testing (not production)
    app()->detectEnvironment(fn () => 'testing');

    $this->artisan('porter:install', ['--roles' => true])
        ->expectsOutput('🚀 Installing Porter RBAC...')
        ->expectsOutput('🎭 Creating default role classes...')
        ->expectsOutput('🎭 Default roles created in Porter directory')
        ->expectsConfirmation('Run migrations now?', 'no')
        ->assertSuccessful();

    // Check that Porter directory was created
    $porterDir = app_path('Porter');
    expect(File::exists($porterDir))->toBeTrue();

    // Check that BaseRole.php was created
    $baseRoleFile = "{$porterDir}/BaseRole.php";
    expect(File::exists($baseRoleFile))->toBeTrue();

    // Check that default roles were created
    $defaultRoles = ['Admin', 'Manager', 'Editor', 'Contributor', 'Viewer', 'Guest'];
    foreach ($defaultRoles as $role) {
        expect(File::exists("{$porterDir}/{$role}.php"))->toBeTrue();
    }
});

test('install command respects --force flag for existing files', function () {
    // Set environment to testing
    app()->detectEnvironment(fn () => 'testing');

    // Create Porter directory and a role file first
    $porterDir = app_path('Porter');
    File::makeDirectory($porterDir, 0755, true);
    File::put("{$porterDir}/Admin.php", 'existing content');

    // Run install without force - should warn about existing file
    $this->artisan('porter:install', ['--roles' => true])
        ->expectsOutput('⚠️  Role Admin already exists. Use --force to overwrite.')
        ->expectsConfirmation('Run migrations now?', 'no')
        ->assertSuccessful();

    // Content should remain unchanged
    expect(File::get("{$porterDir}/Admin.php"))->toBe('existing content');

    // Run with --force flag - should overwrite
    $this->artisan('porter:install', ['--roles' => true, '--force' => true])
        ->expectsConfirmation('Run migrations now?', 'no')
        ->assertSuccessful();

    // Content should be updated
    expect(File::get("{$porterDir}/Admin.php"))->not()->toBe('existing content');
    expect(File::get("{$porterDir}/Admin.php"))->toContain('class Admin extends BaseRole');
});

test('install command creates BaseRole with correct namespace', function () {
    // Set environment to testing
    app()->detectEnvironment(fn () => 'testing');

    $this->artisan('porter:install')
        ->expectsConfirmation('Run migrations now?', 'no')
        ->assertSuccessful();

    $porterDir = app_path('Porter');
    $baseRoleFile = "{$porterDir}/BaseRole.php";
    $content = File::get($baseRoleFile);

    expect($content)
        ->toContain('namespace App\\Porter;')
        ->toContain('use Hdaklue\\Porter\\Roles\\BaseRole as PorterBaseRole;')
        ->toContain('abstract class BaseRole extends PorterBaseRole');
});

test('install command shows different help based on --roles flag', function () {
    // Set environment to testing
    app()->detectEnvironment(fn () => 'testing');

    // Without --roles flag
    $this->artisan('porter:install')
        ->expectsOutput('4. Create your custom roles using "php artisan porter:create"')
        ->expectsOutput('5. Run "php artisan porter:doctor" to validate your setup')
        ->expectsConfirmation('Run migrations now?', 'no')
        ->assertSuccessful();

    // Clean up
    File::deleteDirectory(app_path('Porter'));

    // With --roles flag
    $this->artisan('porter:install', ['--roles' => true])
        ->expectsOutput('4. Run "php artisan porter:doctor" to validate your setup')
        ->expectsConfirmation('Run migrations now?', 'no')
        ->assertSuccessful();
});
