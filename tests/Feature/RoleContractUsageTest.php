<?php

declare(strict_types=1);

use Hdaklue\Porter\Facades\Porter;
use Hdaklue\Porter\Tests\Fixtures\TestAdmin;
use Hdaklue\Porter\Tests\Fixtures\TestProject;
use Hdaklue\Porter\Tests\Fixtures\TestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
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
});

test('RoleManager accepts RoleContract objects for type safety', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);
    $adminRole = new TestAdmin();

    // Test assign with RoleContract object
    Porter::assign($user, $project, $adminRole);

    // Test hasRoleOn with RoleContract object
    expect(Porter::hasRoleOn($user, $project, $adminRole))->toBeTrue();

    // Test changeRoleOn with RoleContract object
    $anotherAdminRole = new TestAdmin();
    Porter::changeRoleOn($user, $project, $anotherAdminRole);

    expect(Porter::hasRoleOn($user, $project, $anotherAdminRole))->toBeTrue();
});

test('RoleManager union types work correctly', function () {
    $user = TestUser::create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $project = TestProject::create(['name' => 'Another Project']);
    $adminRole = new TestAdmin();

    // Test with RoleContract object
    Porter::assign($user, $project, $adminRole);
    expect(Porter::hasRoleOn($user, $project, $adminRole))->toBeTrue();

    // Test that different instances of same role work
    $anotherAdminInstance = new TestAdmin();
    expect(Porter::hasRoleOn($user, $project, $anotherAdminInstance))->toBeTrue();
});