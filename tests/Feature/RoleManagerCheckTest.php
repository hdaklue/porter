<?php

declare(strict_types=1);

use Hdaklue\Porter\Models\Roster;
use Hdaklue\Porter\RoleManager;
use Hdaklue\Porter\Roles\Admin;
use Hdaklue\Porter\Roles\Editor;
use Hdaklue\Porter\Roles\Manager;
use Hdaklue\Porter\Tests\Fixtures\TestAdmin;
use Hdaklue\Porter\Tests\Fixtures\TestEditor;
use Hdaklue\Porter\Tests\Fixtures\TestProject;
use Hdaklue\Porter\Tests\Fixtures\TestUser;
use Hdaklue\Porter\Tests\Fixtures\TestViewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

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

test('check method returns true when user has exact role on entity', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project', 'description' => 'A test project']);
    $adminRole = new TestAdmin();

    // Assign the role using RoleManager
    $this->roleManager->assign($user, $project, $adminRole);

    // Check method should return true for the exact role
    expect($this->roleManager->check($user, $project, $adminRole))->toBeTrue();
});

test('check method returns false when user does not have role on entity', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project', 'description' => 'A test project']);
    $adminRole = new TestAdmin();

    // No role assigned - check should return false
    expect($this->roleManager->check($user, $project, $adminRole))->toBeFalse();
});

test('check method returns false when user has different role on entity', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project', 'description' => 'A test project']);
    $adminRole = new TestAdmin();
    $editorRole = new TestEditor();

    // Assign editor role
    $this->roleManager->assign($user, $project, $editorRole);

    // Check for admin role should return false
    expect($this->roleManager->check($user, $project, $adminRole))->toBeFalse();
    
    // Check for assigned editor role should return true
    expect($this->roleManager->check($user, $project, $editorRole))->toBeTrue();
});

test('check method works with built-in role classes', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project', 'description' => 'A test project']);
    
    $adminRole = new Admin();
    $editorRole = new Editor();
    $managerRole = new Manager();

    // Test with Admin role
    $this->roleManager->assign($user, $project, $adminRole);
    expect($this->roleManager->check($user, $project, $adminRole))->toBeTrue();
    expect($this->roleManager->check($user, $project, $editorRole))->toBeFalse();
    expect($this->roleManager->check($user, $project, $managerRole))->toBeFalse();

    // Change to Editor role
    $this->roleManager->changeRoleOn($user, $project, $editorRole);
    expect($this->roleManager->check($user, $project, $adminRole))->toBeFalse();
    expect($this->roleManager->check($user, $project, $editorRole))->toBeTrue();
    expect($this->roleManager->check($user, $project, $managerRole))->toBeFalse();
});

test('check method handles multiple users with different roles on same entity', function () {
    $user1 = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $user2 = TestUser::create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
    $project = TestProject::create(['name' => 'Test Project', 'description' => 'A test project']);
    
    $adminRole = new TestAdmin();
    $editorRole = new TestEditor();

    // Assign different roles to different users
    $this->roleManager->assign($user1, $project, $adminRole);
    $this->roleManager->assign($user2, $project, $editorRole);

    // Check first user
    expect($this->roleManager->check($user1, $project, $adminRole))->toBeTrue();
    expect($this->roleManager->check($user1, $project, $editorRole))->toBeFalse();

    // Check second user
    expect($this->roleManager->check($user2, $project, $adminRole))->toBeFalse();
    expect($this->roleManager->check($user2, $project, $editorRole))->toBeTrue();
});

test('check method handles same user with roles on different entities', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project1 = TestProject::create(['name' => 'Project 1', 'description' => 'First project']);
    $project2 = TestProject::create(['name' => 'Project 2', 'description' => 'Second project']);
    
    $adminRole = new TestAdmin();
    $editorRole = new TestEditor();

    // Assign different roles on different projects
    $this->roleManager->assign($user, $project1, $adminRole);
    $this->roleManager->assign($user, $project2, $editorRole);

    // Check roles on project1
    expect($this->roleManager->check($user, $project1, $adminRole))->toBeTrue();
    expect($this->roleManager->check($user, $project1, $editorRole))->toBeFalse();

    // Check roles on project2
    expect($this->roleManager->check($user, $project2, $adminRole))->toBeFalse();
    expect($this->roleManager->check($user, $project2, $editorRole))->toBeTrue();
});

test('check method returns false after role removal', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project', 'description' => 'A test project']);
    $adminRole = new TestAdmin();

    // Assign role
    $this->roleManager->assign($user, $project, $adminRole);
    expect($this->roleManager->check($user, $project, $adminRole))->toBeTrue();

    // Remove role
    $this->roleManager->remove($user, $project);
    expect($this->roleManager->check($user, $project, $adminRole))->toBeFalse();
});

test('check method returns correct result after role change', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project', 'description' => 'A test project']);
    $adminRole = new TestAdmin();
    $editorRole = new TestEditor();

    // Initial assignment
    $this->roleManager->assign($user, $project, $adminRole);
    expect($this->roleManager->check($user, $project, $adminRole))->toBeTrue();
    expect($this->roleManager->check($user, $project, $editorRole))->toBeFalse();

    // Change role
    $this->roleManager->changeRoleOn($user, $project, $editorRole);
    expect($this->roleManager->check($user, $project, $adminRole))->toBeFalse();
    expect($this->roleManager->check($user, $project, $editorRole))->toBeTrue();
});

test('check method works with polymorphic entities correctly', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);
    $adminRole = new TestAdmin();

    // Assign role
    $this->roleManager->assign($user, $project, $adminRole);

    // Verify the database record has correct polymorphic types
    $roster = Roster::first();
    expect($roster->assignable_type)->toBe(TestUser::class);
    expect($roster->roleable_type)->toBe(TestProject::class);

    // Check method should work correctly with polymorphic data
    expect($this->roleManager->check($user, $project, $adminRole))->toBeTrue();
});

test('check method handles encrypted role keys correctly', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);
    $adminRole = new TestAdmin();

    // Assign role
    $this->roleManager->assign($user, $project, $adminRole);

    // Verify role key is stored as encrypted/hashed in database
    $roster = Roster::first();
    expect($roster->getRoleDBKey())->toBe($adminRole::getDbKey());
    expect($roster->role_key)->toBeInstanceOf($adminRole::class);
    
    // The check method should work correctly with encrypted keys
    expect($this->roleManager->check($user, $project, $adminRole))->toBeTrue();
});

test('check method handles non-existent entity combinations', function () {
    $user1 = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $user2 = TestUser::create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
    $project1 = TestProject::create(['name' => 'Project 1']);
    $project2 = TestProject::create(['name' => 'Project 2']);
    $adminRole = new TestAdmin();

    // Only assign role to user1 on project1
    $this->roleManager->assign($user1, $project1, $adminRole);

    // Test valid combination
    expect($this->roleManager->check($user1, $project1, $adminRole))->toBeTrue();

    // Test invalid combinations
    expect($this->roleManager->check($user1, $project2, $adminRole))->toBeFalse(); // Wrong project
    expect($this->roleManager->check($user2, $project1, $adminRole))->toBeFalse(); // Wrong user
    expect($this->roleManager->check($user2, $project2, $adminRole))->toBeFalse(); // Wrong user and project
});

test('check method performance with multiple assignments', function () {
    $users = collect(range(1, 10))->map(function ($i) {
        return TestUser::create(['name' => "User {$i}", 'email' => "user{$i}@example.com"]);
    });
    
    $projects = collect(range(1, 5))->map(function ($i) {
        return TestProject::create(['name' => "Project {$i}"]);
    });

    $adminRole = new TestAdmin();
    $editorRole = new TestEditor();

    // Create multiple role assignments
    foreach ($users as $user) {
        foreach ($projects as $index => $project) {
            $role = $index % 2 === 0 ? $adminRole : $editorRole;
            $this->roleManager->assign($user, $project, $role);
        }
    }

    // Verify total assignments
    expect(Roster::count())->toBe(50); // 10 users × 5 projects

    // Test check method performance and accuracy
    $startTime = microtime(true);
    
    // Perform multiple checks
    foreach ($users->take(3) as $user) {
        foreach ($projects->take(3) as $index => $project) {
            $expectedRole = $index % 2 === 0 ? $adminRole : $editorRole;
            $unexpectedRole = $index % 2 === 0 ? $editorRole : $adminRole;
            
            expect($this->roleManager->check($user, $project, $expectedRole))->toBeTrue();
            expect($this->roleManager->check($user, $project, $unexpectedRole))->toBeFalse();
        }
    }
    
    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;
    
    // Performance should be reasonable (less than 1 second for this test)
    expect($executionTime)->toBeLessThan(1.0);
});

test('check method works with caching enabled', function () {
    // Enable caching for this test
    config(['porter.should_cache' => true]);
    
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);
    $adminRole = new TestAdmin();

    // Assign role
    $this->roleManager->assign($user, $project, $adminRole);

    // First check should query database and cache result
    expect($this->roleManager->check($user, $project, $adminRole))->toBeTrue();

    // Verify cache is working by checking hasRoleOn (which uses same cache mechanism)
    expect($this->roleManager->hasRoleOn($user, $project, $adminRole))->toBeTrue();
});

test('check method works with caching disabled', function () {
    // Disable caching for this test
    config(['porter.should_cache' => false]);
    
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);
    $adminRole = new TestAdmin();

    // Assign role
    $this->roleManager->assign($user, $project, $adminRole);

    // Check should work without caching
    expect($this->roleManager->check($user, $project, $adminRole))->toBeTrue();
});

test('check method consistency with hasRoleOn method', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);
    $adminRole = new TestAdmin();
    $editorRole = new TestEditor();

    // Test without any role assignments
    expect($this->roleManager->check($user, $project, $adminRole))->toBe(
        $this->roleManager->hasRoleOn($user, $project, $adminRole)
    );
    expect($this->roleManager->check($user, $project, $editorRole))->toBe(
        $this->roleManager->hasRoleOn($user, $project, $editorRole)
    );

    // Assign admin role
    $this->roleManager->assign($user, $project, $adminRole);

    // Both methods should return consistent results
    expect($this->roleManager->check($user, $project, $adminRole))->toBe(
        $this->roleManager->hasRoleOn($user, $project, $adminRole)
    );
    expect($this->roleManager->check($user, $project, $editorRole))->toBe(
        $this->roleManager->hasRoleOn($user, $project, $editorRole)
    );
});

test('check method handles edge case with same role instance', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);
    
    // Create two instances of the same role class
    $adminRole1 = new TestAdmin();
    $adminRole2 = new TestAdmin();

    // Assign with first instance
    $this->roleManager->assign($user, $project, $adminRole1);

    // Check with both instances should return true (same role class)
    expect($this->roleManager->check($user, $project, $adminRole1))->toBeTrue();
    expect($this->roleManager->check($user, $project, $adminRole2))->toBeTrue();
    
    // Both instances should have same encrypted key
    expect($adminRole1::getDbKey())->toBe($adminRole2::getDbKey());
});

test('check method database query optimization', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);
    $adminRole = new TestAdmin();

    // Assign role
    $this->roleManager->assign($user, $project, $adminRole);

    // Monitor database queries
    DB::enableQueryLog();
    DB::flushQueryLog();

    // Perform check
    $result = $this->roleManager->check($user, $project, $adminRole);

    $queries = DB::getQueryLog();

    // Should execute exactly one query for the check
    expect(count($queries))->toBe(1);
    expect($result)->toBeTrue();

    // The query should be an EXISTS query (efficient)
    $query = $queries[0]['query'];
    expect($query)->toContain('exists');
    expect($query)->toContain('roster');
});