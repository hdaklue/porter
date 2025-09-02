<?php

declare(strict_types=1);

use Hdaklue\Porter\RoleFactory;
use Illuminate\Support\Facades\File;

afterEach(function () {
    // Clean up created role files
    $porterDir = config('porter.directory');
    if (File::exists($porterDir)) {
        File::deleteDirectory($porterDir);
    }
});

test('it demonstrates the new dynamic role factory usage', function () {
    // Arrange: Create some roles using the porter command
    $this->artisan('porter:create', [
        'name' => 'Admin',
        '--description' => 'Admin role',  // Simplified expectation
    ])
        ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
        ->assertSuccessful();

    $this->artisan('porter:create', [
        'name' => 'ProjectManager',
        '--description' => 'ProjectManager role',  // Simplified expectation
    ])
        ->expectsChoice('Select creation mode:', 'highest', ['lowest', 'highest', 'lower', 'higher'])
        ->assertSuccessful();

    $this->artisan('porter:create', [
        'name' => 'TeamLead',
        '--description' => 'TeamLead role',  // Simplified expectation
    ])
        ->expectsChoice('Select creation mode:', 'lower', ['lowest', 'highest', 'lower', 'higher'])
        ->expectsChoice('Which role do you want to reference?', 'ProjectManager', ['Admin', 'ProjectManager'])
        ->assertSuccessful();

    // Act: Use the dynamic factory methods - these work like magic!
    $admin = RoleFactory::admin();                    // Creates Admin role
    $projectManager = RoleFactory::projectManager();  // Creates ProjectManager role
    $teamLead = RoleFactory::teamLead();             // Creates TeamLead role

    // Assert: All roles are properly instantiated
    expect($admin)->toBeInstanceOf(\App\Porter\Admin::class);
    expect($projectManager)->toBeInstanceOf(\App\Porter\ProjectManager::class);
    expect($teamLead)->toBeInstanceOf(\App\Porter\TeamLead::class);

    // Assert: Role properties are correct
    expect($admin->getName())->toBe('admin');
    expect($admin->getLevel())->toBe(1);
    expect($admin->getDescription())->toBe('Admin role');  // Uses the name + "role" pattern

    expect($projectManager->getName())->toBe('project_manager');
    expect($projectManager->getLevel())->toBe(3);  // Pushed up by TeamLead creation
    expect($projectManager->getDescription())->toBe('ProjectManager role');  // Uses the name + "role" pattern

    expect($teamLead->getName())->toBe('team_lead');
    expect($teamLead->getLevel())->toBe(2);  // Takes ProjectManager's original level
    expect($teamLead->getDescription())->toBe('TeamLead role');  // Uses the name + "role" pattern

    // Act: Test role comparison methods
    expect($projectManager->isHigherThan($teamLead))->toBeTrue();
    expect($teamLead->isHigherThan($admin))->toBeTrue();
    expect($admin->isLowerThan($projectManager))->toBeTrue();

    // Act: Get all roles at once
    $allRoles = RoleFactory::allFromPorterDirectory();
    expect($allRoles)->toHaveCount(3);
    expect($allRoles)->toHaveKeys(['Admin', 'ProjectManager', 'TeamLead']);

    // Demonstrate usage with concern traits (type-safe!)
    // Note: In a real app, you'd use these roles with your entities like:
    // $user->assign($project, RoleFactory::projectManager());
    // $user->hasAssignmentOn($project, RoleFactory::admin());
});

test('it allows configurable namespace and directory', function () {
    // This test demonstrates that the factory reads from config
    $porterDir = config('porter.directory');
    $namespace = config('porter.namespace');

    expect($porterDir)->toBe(app_path('Porter'));  // Default config
    expect($namespace)->toBe('App\Porter');        // Default config

    // The factory uses these config values internally
    expect(RoleFactory::existsInPorterDirectory('NonExistent'))->toBeFalse();
});
