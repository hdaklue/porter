<?php

declare(strict_types=1);

use Hdaklue\Porter\Models\Roster;
use Hdaklue\Porter\Tests\Fixtures\TestUser;
use Hdaklue\Porter\Tests\Fixtures\TestProject;
use Hdaklue\Porter\Tests\Fixtures\TestAdmin;
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
});

test('Roster model establishes correct assignable relationship', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);
    
    $roster = Roster::create([
        'assignable_type' => TestUser::class,
        'assignable_id' => $user->id,
        'roleable_type' => TestProject::class,
        'roleable_id' => $project->id,
        'role_key' => 'test_key',
    ]);

    $assignable = $roster->assignable;
    expect($assignable)->toBeInstanceOf(TestUser::class);
    expect($assignable->id)->toBe($user->id);
    expect($assignable->name)->toBe('John Doe');
});

test('Roster model establishes correct roleable relationship', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project', 'description' => 'A test project']);
    
    $roster = Roster::create([
        'assignable_type' => TestUser::class,
        'assignable_id' => $user->id,
        'roleable_type' => TestProject::class,
        'roleable_id' => $project->id,
        'role_key' => 'test_key',
    ]);

    $roleable = $roster->roleable;
    expect($roleable)->toBeInstanceOf(TestProject::class);
    expect($roleable->id)->toBe($project->id);
    expect($roleable->name)->toBe('Test Project');
});

test('Roster model retrieves role instance from role_key', function () {
    $user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $project = TestProject::create(['name' => 'Test Project']);
    
    $admin = new TestAdmin();
    $encryptedKey = $admin::getDbKey();
    
    $roster = Roster::create([
        'assignable_type' => TestUser::class,
        'assignable_id' => $user->id,
        'roleable_type' => TestProject::class,
        'roleable_id' => $project->id,
        'role_key' => $encryptedKey,
    ]);

    $role = $roster->role;
    expect($role)->toBeInstanceOf(TestAdmin::class);
    expect($role->getName())->toBe('TestAdmin');
});

test('Roster model uses correct table name from config', function () {
    $roster = new Roster();
    expect($roster->getTable())->toBe('roaster');
});

test('Roster model retrieves role database key correctly', function () {
    $roster = new Roster([
        'assignable_type' => TestUser::class,
        'assignable_id' => 1,
        'roleable_type' => TestProject::class,
        'roleable_id' => 1,
        'role_key' => 'test_role_key',
    ]);

    expect($roster->getRoleDBKey())->toBe('test_role_key');
});