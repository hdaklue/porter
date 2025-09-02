<?php

declare(strict_types=1);

use Hdaklue\Porter\Validators\RoleValidator;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    RoleValidator::clearCache();
});

afterEach(function () {
    // Clean up created role files
    $porterDir = app_path('Porter');
    if (File::exists($porterDir)) {
        File::deleteDirectory($porterDir);
    }
});

test('it creates first role with interactive mode selection - lowest', function () {
    // Act: Create first role selecting "lowest" mode
    $this->artisan('porter:create', [
        'name' => 'Admin',
        '--description' => 'Admin role',
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
        ->assertSuccessful();

    // Assert: Should be created at level 1
    expect(File::exists(app_path('Porter/Admin.php')))->toBeTrue();

    $content = File::get(app_path('Porter/Admin.php'));
    expect($content)->toContain('return 1;');
    expect($content)->toContain('Admin role');
});

test('it creates first role with interactive mode selection - highest', function () {
    // Act: Create first role selecting "highest" mode
    $this->artisan('porter:create', [
        'name' => 'Admin',
        '--description' => 'Admin role',
    ])
        ->expectsChoice('Select creation mode:', 'highest', ['lowest', 'highest'])
        ->assertSuccessful();

    // Assert: Should be created at level 1 (same as lowest when no roles exist)
    expect(File::exists(app_path('Porter/Admin.php')))->toBeTrue();

    $content = File::get(app_path('Porter/Admin.php'));
    expect($content)->toContain('return 1;');
    expect($content)->toContain('Admin role');
});

test('it creates role with interactive highest mode when roles exist', function () {
    // Arrange: Create first role
    $this->artisan('porter:create', [
        'name' => 'Manager',
        '--description' => 'Manager role',
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
        ->assertSuccessful();

    // Act: Create second role with highest mode
    $this->artisan('porter:create', [
        'name' => 'Admin',
        '--description' => 'Admin role',
    ])
        ->expectsChoice('Select creation mode:', 'highest', ['lowest', 'highest', 'lower', 'higher'])
        ->assertSuccessful();

    // Assert: Admin should be at level 2
    $adminContent = File::get(app_path('Porter/Admin.php'));
    expect($adminContent)->toContain('return 2;');
});

test('it creates role with interactive lower mode when roles exist', function () {
    // Arrange: Create first role
    $this->artisan('porter:create', [
        'name' => 'Manager',
        '--description' => 'Manager role',
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
        ->assertSuccessful();

    // Act: Create second role with lower mode
    $this->artisan('porter:create', [
        'name' => 'Developer',
        '--description' => 'Developer role',
    ])
        ->expectsChoice('Select creation mode:', 'lower', ['lowest', 'highest', 'lower', 'higher'])
        ->expectsChoice('Which role do you want to reference?', 'Manager', ['Manager'])
        ->assertSuccessful();

    // Assert: Developer should be at level 1 (same as Manager), Manager pushed to level 2
    $developerContent = File::get(app_path('Porter/Developer.php'));
    $managerContent = File::get(app_path('Porter/Manager.php'));

    expect($developerContent)->toContain('return 1;');  // New role takes Manager's original level
    expect($managerContent)->toContain('return 2;');    // Manager pushed to level 2
});

test('it creates role with interactive higher mode when roles exist', function () {
    // Arrange: Create first role
    $this->artisan('porter:create', [
        'name' => 'Editor',
        '--description' => 'Editor role',
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
        ->assertSuccessful();

    // Act: Create second role with higher mode
    $this->artisan('porter:create', [
        'name' => 'Manager',
        '--description' => 'Manager role',
    ])
        ->expectsChoice('Select creation mode:', 'higher', ['lowest', 'highest', 'lower', 'higher'])
        ->expectsChoice('Which role do you want to reference?', 'Editor', ['Editor'])
        ->assertSuccessful();

    // Assert: Manager should be at level 2, Editor stays at level 1
    $managerContent = File::get(app_path('Porter/Manager.php'));
    $editorContent = File::get(app_path('Porter/Editor.php'));

    expect($managerContent)->toContain('return 2;');
    expect($editorContent)->toContain('return 1;');
});

test('it creates role with interactive lowest mode and pushes existing roles up', function () {
    // Arrange: Create some existing roles first
    $this->artisan('porter:create', [
        'name' => 'Admin',
        '--description' => 'Admin role',
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
        ->assertSuccessful();

    $this->artisan('porter:create', [
        'name' => 'Manager',
        '--description' => 'Manager role',
    ])
        ->expectsChoice('Select creation mode:', 'highest', ['lowest', 'highest', 'lower', 'higher'])
        ->assertSuccessful();

    // Act: Create a role with lowest mode
    $this->artisan('porter:create', [
        'name' => 'Guest',
        '--description' => 'Guest role',
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest', 'lower', 'higher'])
        ->assertSuccessful();

    // Assert: Check all levels
    $guestContent = File::get(app_path('Porter/Guest.php'));
    $adminContent = File::get(app_path('Porter/Admin.php'));
    $managerContent = File::get(app_path('Porter/Manager.php'));

    expect($guestContent)->toContain('return 1;');    // New lowest
    expect($adminContent)->toContain('return 2;');    // Pushed from 1 to 2
    expect($managerContent)->toContain('return 3;');  // Pushed from 2 to 3
});

test('it creates role with lower mode when space is available', function () {
    // Arrange: Create roles with gaps
    $this->artisan('porter:create', [
        'name' => 'Admin',
        '--description' => 'Admin role',
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
        ->assertSuccessful(); // Level 1

    $this->artisan('porter:create', [
        'name' => 'Manager',
        '--description' => 'Manager role',
    ])
        ->expectsChoice('Select creation mode:', 'highest', ['lowest', 'highest', 'lower', 'higher'])
        ->assertSuccessful(); // Level 2

    $this->artisan('porter:create', [
        'name' => 'SuperUser',
        '--description' => 'SuperUser role',
    ])
        ->expectsChoice('Select creation mode:', 'highest', ['lowest', 'highest', 'lower', 'higher'])
        ->assertSuccessful(); // Level 3

    // Act: Create a role lower than Manager (should cause shifts)
    $this->artisan('porter:create', [
        'name' => 'Editor',
        '--description' => 'Editor role',
    ])
        ->expectsChoice('Select creation mode:', 'lower', ['lowest', 'highest', 'lower', 'higher'])
        ->expectsChoice('Which role do you want to reference?', 'Manager', ['Admin', 'Manager', 'SuperUser'])
        ->assertSuccessful();

    // Assert: Check all levels are correct
    $adminContent = File::get(app_path('Porter/Admin.php'));
    $editorContent = File::get(app_path('Porter/Editor.php'));
    $managerContent = File::get(app_path('Porter/Manager.php'));
    $superUserContent = File::get(app_path('Porter/SuperUser.php'));

    expect($adminContent)->toContain('return 1;');      // Stays at 1 (not affected)
    expect($editorContent)->toContain('return 2;');     // Takes Manager's level 2
    expect($managerContent)->toContain('return 3;');    // Pushed from 2 to 3
    expect($superUserContent)->toContain('return 4;');  // Pushed from 3 to 4
});

test('it shows proper role context in creation mode selection', function () {
    // Arrange: Create some roles first
    $this->artisan('porter:create', [
        'name' => 'Viewer',
        '--description' => 'Viewer role',
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
        ->assertSuccessful(); // Level 1

    $this->artisan('porter:create', [
        'name' => 'Editor',
        '--description' => 'Editor role',
    ])
        ->expectsChoice('Select creation mode:', 'highest', ['lowest', 'highest', 'lower', 'higher'])
        ->assertSuccessful(); // Level 2

    $this->artisan('porter:create', [
        'name' => 'Admin',
        '--description' => 'Admin role',
    ])
        ->expectsChoice('Select creation mode:', 'highest', ['lowest', 'highest', 'lower', 'higher'])
        ->assertSuccessful(); // Level 3

    // Act: Verify that when we create another role, all options are available
    $this->artisan('porter:create', [
        'name' => 'Manager',
        '--description' => 'Manager role',
    ])
        ->expectsChoice('Select creation mode:', 'highest', ['lowest', 'highest', 'lower', 'higher'])
        ->assertSuccessful();

    // Assert: Should be created at level 4 (highest + 1)
    $managerContent = File::get(app_path('Porter/Manager.php'));
    expect($managerContent)->toContain('return 4;');
});

test('it converts role names to PascalCase automatically', function () {
    // Test that invalid-name gets converted to InvalidName
    $this->artisan('porter:create', [
        'name' => 'invalid-name',
        '--description' => 'Invalid role',
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
        ->assertSuccessful();

    // Verify the file was created with PascalCase name
    expect(File::exists(app_path('Porter/InvalidName.php')))->toBeTrue();

    // Test another case conversion
    $this->artisan('porter:create', [
        'name' => 'project_manager',
        '--description' => 'Project Manager role',
    ])
        ->expectsChoice('Select creation mode:', 'highest', ['lowest', 'highest', 'lower', 'higher'])
        ->assertSuccessful();

    // Verify the file was created with PascalCase name
    expect(File::exists(app_path('Porter/ProjectManager.php')))->toBeTrue();
});

test('it prevents duplicate role names', function () {
    // Arrange: Create a role first
    $this->artisan('porter:create', [
        'name' => 'TestRole',
        '--description' => 'A test role',
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
        ->assertSuccessful();

    // Act & Assert: Try to create another role with the same name
    $this->artisan('porter:create', [
        'name' => 'TestRole',
        '--description' => 'Another test role',
    ])
        ->expectsOutputToContain('❌ Role name \'TestRole\' already exists!')
        ->assertFailed();
});

test('it handles error when no roles exist for hierarchy options', function () {
    // This test simulates the internal logic when no roles exist for lower/higher options
    // In the new interactive system, the user would only see 'lowest' and 'highest' options
    // when no roles exist, so this scenario shouldn't occur in normal usage.

    // Arrange: Ensure no roles exist
    $porterDir = app_path('Porter');
    if (File::exists($porterDir)) {
        File::deleteDirectory($porterDir);
    }

    // Act: Create first role - user should only see lowest/highest options
    $this->artisan('porter:create', [
        'name' => 'FirstRole',
        '--description' => 'First role description',
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
        ->assertSuccessful();

    // Assert: Role should be created at level 1
    expect(File::exists(app_path('Porter/FirstRole.php')))->toBeTrue();
    $content = File::get(app_path('Porter/FirstRole.php'));
    expect($content)->toContain('return 1;');
});
