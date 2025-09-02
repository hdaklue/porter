<?php

declare(strict_types=1);

use Hdaklue\Porter\Models\Roster;
use Hdaklue\Porter\RoleManager;
use Hdaklue\Porter\Tests\Fixtures\TestProject;
use Hdaklue\Porter\Tests\Fixtures\TestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test tables
    Schema::create('test_users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamps();
    });

    Schema::create('test_projects', function ($table) {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->timestamps();
    });

    $this->roleManager = app(RoleManager::class);
});

test('RoleManager can assign and check roles with database', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project', 'description' => 'A test project']);

    // Initially should not have role
    expect($this->roleManager->hasRoleOn($user, $project, 'TestAdmin'))->toBeFalse();
    expect($this->roleManager->hasAnyRoleOn($user, $project))->toBeFalse();

    // Assign role
    $this->roleManager->assign($user, $project, 'TestAdmin');

    // Should now have the role
    expect($this->roleManager->hasRoleOn($user, $project, 'TestAdmin'))->toBeTrue();
    expect($this->roleManager->hasAnyRoleOn($user, $project))->toBeTrue();

    // Should have one assignment in database
    expect(Roster::count())->toBe(1);

    $assignment = Roster::first();
    expect($assignment->assignable_type)->toBe(TestUser::class);
    expect($assignment->assignable_id)->toBe((string) $user->id);
    expect($assignment->roleable_type)->toBe(TestProject::class);
    expect($assignment->roleable_id)->toBe((string) $project->id);
});

test('RoleManager prevents duplicate assignments', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);

    // Assign the same role twice
    $this->roleManager->assign($user, $project, 'test_admin');
    $this->roleManager->assign($user, $project, 'test_admin');

    // Should only have one assignment
    expect(Roster::count())->toBe(1);
});

test('RoleManager can remove role assignments', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);

    // Assign and then remove
    $this->roleManager->assign($user, $project, 'test_admin');
    expect(Roster::count())->toBe(1);
    expect($this->roleManager->hasRoleOn($user, $project, 'test_admin'))->toBeTrue();

    $this->roleManager->remove($user, $project);
    expect(Roster::count())->toBe(0);
    expect($this->roleManager->hasRoleOn($user, $project, 'test_admin'))->toBeFalse();
});

test('RoleManager can change user roles', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);

    // Assign initial role
    $this->roleManager->assign($user, $project, 'test_editor');
    expect($this->roleManager->hasRoleOn($user, $project, 'test_editor'))->toBeTrue();
    expect($this->roleManager->hasRoleOn($user, $project, 'test_admin'))->toBeFalse();

    // Change to different role
    $this->roleManager->changeRoleOn($user, $project, 'test_admin');

    expect($this->roleManager->hasRoleOn($user, $project, 'test_editor'))->toBeFalse();
    expect($this->roleManager->hasRoleOn($user, $project, 'test_admin'))->toBeTrue();

    // Should still only have one assignment record
    expect(Roster::count())->toBe(1);
});

test('RoleManager fails when assigning non-existent role', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);

    expect(fn () => $this->roleManager->assign($user, $project, 'nonexistent_role'))
        ->toThrow(DomainException::class, "Role 'nonexistent_role' does not exist.");
});

test('RoleManager assign method uses replace strategy by default', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);

    // Assign an initial role
    $this->roleManager->assign($user, $project, 'test_editor');
    expect(Roster::count())->toBe(1);
    expect($this->roleManager->hasRoleOn($user, $project, 'test_editor'))->toBeTrue();

    // Assign a new role, which should replace the old one (default strategy)
    $this->roleManager->assign($user, $project, 'test_admin');

    // Only the new role should exist
    expect(Roster::count())->toBe(1);
    expect($this->roleManager->hasRoleOn($user, $project, 'test_admin'))->toBeTrue();
    expect($this->roleManager->hasRoleOn($user, $project, 'test_editor'))->toBeFalse();
});

test('RoleManager assign method uses add strategy when configured', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);

    // Set assignment strategy to 'add'
    config(['porter.security.assignment_strategy' => 'add']);

    // Assign first role
    $this->roleManager->assign($user, $project, 'test_editor');
    expect(Roster::count())->toBe(1);
    expect($this->roleManager->hasRoleOn($user, $project, 'test_editor'))->toBeTrue();

    // Assign second role
    $this->roleManager->assign($user, $project, 'test_admin');

    // Both roles should now exist
    expect(Roster::count())->toBe(2);
    expect($this->roleManager->hasRoleOn($user, $project, 'test_editor'))->toBeTrue();
    expect($this->roleManager->hasRoleOn($user, $project, 'test_admin'))->toBeTrue();
});
