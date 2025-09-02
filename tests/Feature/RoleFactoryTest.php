<?php

declare(strict_types=1);

use Hdaklue\Porter\RoleFactory;
use Illuminate\Support\Facades\File;

afterEach(function () {
    // Clean up created role files
    $porterDir = app_path('Porter');
    if (File::exists($porterDir)) {
        File::deleteDirectory($porterDir);
    }
});

test('it creates roles dynamically using magic __callStatic method', function () {
    // Arrange: Create some roles first using the command
    $this->artisan('porter:create', [
        'name' => 'Admin',
        '--description' => 'Admin role',
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
        ->assertSuccessful();

    $this->artisan('porter:create', [
        'name' => 'ProjectManager',
        '--description' => 'ProjectManager role',
    ])
        ->expectsChoice('Select creation mode:', 'highest', ['lowest', 'highest', 'lower', 'higher'])
        ->assertSuccessful();

    // Act & Assert: Use dynamic factory methods
    $adminRole = RoleFactory::admin();
    $projectManagerRole = RoleFactory::projectManager();

    expect($adminRole)->toBeInstanceOf(\App\Porter\Admin::class);
    expect($adminRole->getName())->toBe('admin');
    expect($adminRole->getDescription())->toBe('Admin role');

    expect($projectManagerRole)->toBeInstanceOf(\App\Porter\ProjectManager::class);
    expect($projectManagerRole->getName())->toBe('project_manager');
    expect($projectManagerRole->getDescription())->toBe('ProjectManager role');
});

test('it throws exception for non-existent role', function () {
    expect(fn () => RoleFactory::nonExistentRole())
        ->toThrow(InvalidArgumentException::class);
});

test('it gets all roles from Porter directory', function () {
    // Arrange: Create some roles
    $this->artisan('porter:create', [
        'name' => 'Viewer',
        '--description' => 'Viewer role',
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
        ->assertSuccessful();

    $this->artisan('porter:create', [
        'name' => 'Editor',
        '--description' => 'Editor role',
    ])
        ->expectsChoice('Select creation mode:', 'highest', ['lowest', 'highest', 'lower', 'higher'])
        ->assertSuccessful();

    // Act: Get all roles
    $allRoles = RoleFactory::allFromPorterDirectory();

    // Assert: Check we have both roles
    expect($allRoles)->toHaveCount(2);
    expect($allRoles)->toHaveKeys(['Viewer', 'Editor']);
    expect($allRoles['Viewer'])->toBeInstanceOf(\App\Porter\Viewer::class);
    expect($allRoles['Editor'])->toBeInstanceOf(\App\Porter\Editor::class);
});

test('it checks if role exists in Porter directory', function () {
    // Arrange: Create a role
    $this->artisan('porter:create', [
        'name' => 'TestRole',
        '--description' => 'Test role',
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
        ->assertSuccessful();

    // Act & Assert
    expect(RoleFactory::existsInPorterDirectory('TestRole'))->toBeTrue();
    expect(RoleFactory::existsInPorterDirectory('NonExistentRole'))->toBeFalse();
});
