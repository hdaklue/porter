<?php

declare(strict_types=1);

use Hdaklue\Porter\RoleFactory;
use Hdaklue\Porter\Tests\Fixtures\TestAdmin;
use Hdaklue\Porter\Tests\Fixtures\TestEditor;
use Hdaklue\Porter\Tests\Fixtures\TestViewer;

test('can create role instance from plain key', function () {
    $role = RoleFactory::make('test_admin');

    expect($role)->toBeInstanceOf(TestAdmin::class);
    expect($role->getName())->toBe('TestAdmin');
    expect($role->getLevel())->toBe(10);
});

test('can create role instance from different plain keys', function () {
    $admin = RoleFactory::make('test_admin');
    $editor = RoleFactory::make('test_editor');
    $viewer = RoleFactory::make('test_viewer');

    expect($admin)->toBeInstanceOf(TestAdmin::class);
    expect($editor)->toBeInstanceOf(TestEditor::class);
    expect($viewer)->toBeInstanceOf(TestViewer::class);
});

test('fails to create role with invalid key', function () {
    expect(fn() => RoleFactory::make('invalid_role_key'))
        ->toThrow(InvalidArgumentException::class, "Role 'invalid_role_key' does not exist.");
});

test('returns null for tryMake with invalid key', function () {
    $role = RoleFactory::tryMake('invalid_role_key');

    expect($role)->toBeNull();
});

test('returns role for tryMake with valid key', function () {
    $role = RoleFactory::tryMake('test_admin');

    expect($role)->toBeInstanceOf(TestAdmin::class);
    expect($role->getName())->toBe('TestAdmin');
});

test('checks role existence correctly', function () {
    expect(RoleFactory::exists('test_admin'))->toBeTrue();
    expect(RoleFactory::exists('test_editor'))->toBeTrue();
    expect(RoleFactory::exists('nonexistent_role'))->toBeFalse();
});

test('can create role from encrypted database key', function () {
    // Get encrypted key from a role instance
    $admin = new TestAdmin();
    $encryptedKey = $admin::getDbKey();
    
    $role = RoleFactory::tryMake($encryptedKey);

    expect($role)->toBeInstanceOf(TestAdmin::class);
    expect($role->getName())->toBe('TestAdmin');
});

test('gets all available roles with keys', function () {
    $rolesWithKeys = RoleFactory::getAllWithKeys();

    expect($rolesWithKeys)->toBeArray();
    expect($rolesWithKeys)->toHaveKey('test_admin');
    expect($rolesWithKeys)->toHaveKey('test_editor');
    expect($rolesWithKeys)->toHaveKey('test_viewer');
    
    expect($rolesWithKeys['test_admin'])->toBeInstanceOf(TestAdmin::class);
    expect($rolesWithKeys['test_editor'])->toBeInstanceOf(TestEditor::class);
    expect($rolesWithKeys['test_viewer'])->toBeInstanceOf(TestViewer::class);
});