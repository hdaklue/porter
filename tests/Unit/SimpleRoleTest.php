<?php

declare(strict_types=1);

// Simple unit tests that don't require Laravel application setup
use Hdaklue\Porter\Tests\Fixtures\TestAdmin;
use Hdaklue\Porter\Tests\Fixtures\TestEditor;
use Hdaklue\Porter\Tests\Fixtures\TestViewer;

// Don't use TestCase for these simple unit tests
test('roles can be instantiated independently', function () {
    $admin = new TestAdmin();
    $editor = new TestEditor();
    $viewer = new TestViewer();

    expect($admin->getName())->toBe('TestAdmin');
    expect($admin->getLevel())->toBe(10);
    expect($admin->getLabel())->toBe('Test Administrator');
    expect($admin->getDescription())->toBe('Test role with full administrative privileges');
    
    expect($editor->getName())->toBe('TestEditor');
    expect($editor->getLevel())->toBe(5);
    
    expect($viewer->getName())->toBe('TestViewer');
    expect($viewer->getLevel())->toBe(1);
});

test('roles can compare levels correctly', function () {
    $admin = new TestAdmin();
    $editor = new TestEditor();
    $viewer = new TestViewer();

    // Admin (10) > Editor (5) > Viewer (1)
    expect($admin->isHigherThan($editor))->toBeTrue();
    expect($admin->isHigherThan($viewer))->toBeTrue();
    expect($editor->isHigherThan($viewer))->toBeTrue();
    
    expect($viewer->isLowerThan($editor))->toBeTrue();
    expect($viewer->isLowerThan($admin))->toBeTrue();
    expect($editor->isLowerThan($admin))->toBeTrue();
    
    expect($admin->isEqualTo($admin))->toBeTrue();
    expect($admin->isEqualTo($editor))->toBeFalse();
});

test('roles generate keys properly', function () {
    $admin = new TestAdmin();
    
    expect($admin::getPlainKey())->toBe('test_admin');
    expect($admin::getDbKey())->not()->toBeEmpty();
    expect($admin::getDbKey())->not()->toBe('test_admin'); // Should be different when not in test mode
});

test('role hierarchy methods work', function () {
    $admin = new TestAdmin();  // Level 10
    $editor = new TestEditor(); // Level 5
    $viewer = new TestViewer(); // Level 1

    expect($admin->isAtLeast($editor))->toBeTrue();  // 10 >= 5
    expect($admin->isAtLeast($admin))->toBeTrue();   // 10 >= 10
    expect($editor->isAtLeast($admin))->toBeFalse(); // 5 >= 10 is false
    
    expect($viewer->isLowerThanOrEqual($editor))->toBeTrue(); // 1 <= 5
    expect($admin->isLowerThanOrEqual($viewer))->toBeFalse(); // 10 <= 1 is false
});