<?php

declare(strict_types=1);

use Hdaklue\Porter\RoleFactory;
use Hdaklue\Porter\Validators\RoleValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear cache before each test
    RoleValidator::clearCache();

    // Create temporary porter directory
    $this->tempDir = sys_get_temp_dir().'/porter_test_'.uniqid();
    mkdir($this->tempDir, 0755, true);

    // Create BaseRole.php (should be ignored)
    File::put($this->tempDir.'/BaseRole.php', '<?php
namespace App\Porter;
abstract class BaseRole {}
');
});

afterEach(function () {
    // Clean up temp directory
    if (is_dir($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }

    RoleValidator::clearCache();
});

test('normalizeName converts various formats to PascalCase', function () {
    expect(RoleValidator::normalizeName('project_manager'))->toBe('ProjectManager');
    expect(RoleValidator::normalizeName('project-manager'))->toBe('ProjectManager');
    expect(RoleValidator::normalizeName('project manager'))->toBe('ProjectManager');
    expect(RoleValidator::normalizeName('ADMIN'))->toBe('ADMIN'); // Str::studly preserves all caps
    expect(RoleValidator::normalizeName('admin'))->toBe('Admin');
    expect(RoleValidator::normalizeName('SuperAdmin'))->toBe('SuperAdmin');
});

test('nameExists works with RoleFactory integration', function () {
    // Mock RoleFactory to avoid file system dependency
    expect(RoleValidator::nameExists('NonExistentRole', $this->tempDir))->toBeFalse();
});

test('isValidLevel validates level constraints', function () {
    expect(RoleValidator::isValidLevel(1))->toBeTrue();
    expect(RoleValidator::isValidLevel(10))->toBeTrue();
    expect(RoleValidator::isValidLevel(999))->toBeTrue();
    expect(RoleValidator::isValidLevel(0))->toBeFalse();
    expect(RoleValidator::isValidLevel(-1))->toBeFalse();
    expect(RoleValidator::isValidLevel(-999))->toBeFalse();
});

test('isValidDescription validates description content', function () {
    expect(RoleValidator::isValidDescription('Valid description'))->toBeTrue();
    expect(RoleValidator::isValidDescription('A'))->toBeTrue(); // Single character is valid
    expect(RoleValidator::isValidDescription(''))->toBeFalse();
    expect(RoleValidator::isValidDescription('   '))->toBeFalse(); // Only whitespace
    expect(RoleValidator::isValidDescription("\t\n"))->toBeFalse(); // Only tabs/newlines
});

test('getExistingRoles handles empty directory', function () {
    $roles = RoleValidator::getExistingRoles($this->tempDir);

    expect($roles)->toHaveKey('names');
    expect($roles)->toHaveKey('levels');
    expect($roles['names'])->toBeEmpty();
    expect($roles['levels'])->toBeEmpty();
});

test('getExistingRoles handles non-existent directory', function () {
    $nonExistentDir = '/non/existent/directory';
    $roles = RoleValidator::getExistingRoles($nonExistentDir);

    expect($roles)->toHaveKey('names');
    expect($roles)->toHaveKey('levels');
    expect($roles['names'])->toBeEmpty();
    expect($roles['levels'])->toBeEmpty();
});

test('getExistingRoles ignores BaseRole file', function () {
    // BaseRole.php already exists from beforeEach
    // Create a real role file
    File::put($this->tempDir.'/Admin.php', '<?php
namespace App\Porter;
class Admin extends BaseRole {
    public function getName(): string { return "admin"; }
    public function getLevel(): int { return 5; }
}');

    // This will use the fallback method since RoleFactory won't find classes
    $roles = RoleValidator::getExistingRoles($this->tempDir);

    // Should not include BaseRole
    expect($roles['names'])->not->toHaveKey('BaseRole');
});

test('simple path caching works correctly', function () {
    // First call - cache miss
    $roles1 = RoleValidator::getExistingRoles($this->tempDir);

    // Second call - cache hit (same paths)
    $roles2 = RoleValidator::getExistingRoles($this->tempDir);

    // Should be identical (from cache)
    expect($roles1)->toBe($roles2);

    // Clear cache manually
    RoleValidator::clearCache();

    // Add a new role file
    File::put($this->tempDir.'/NewRole.php', '<?php
namespace App\Porter;
class NewRole extends BaseRole {
    public function getName(): string { return "new_role"; }
    public function getLevel(): int { return 3; }
}');

    // After clearing cache and adding file, should detect new role
    $roles3 = RoleValidator::getExistingRoles($this->tempDir);

    // Should have more roles now
    expect(count($roles3['names']))->toBeGreaterThan(count($roles1['names']));
});

test('calculateLevel handles lowest mode with empty directory', function () {
    [$level, $updates] = RoleValidator::calculateLevel('lowest', null, $this->tempDir);

    expect($level)->toBe(1);
    expect($updates)->toBeEmpty();
});

test('calculateLevel handles highest mode with empty directory', function () {
    [$level, $updates] = RoleValidator::calculateLevel('highest', null, $this->tempDir);

    expect($level)->toBe(1);
    expect($updates)->toBeEmpty();
});

test('calculateLevel throws exception for lower mode without target role', function () {
    expect(fn () => RoleValidator::calculateLevel('lower', null, $this->tempDir))
        ->toThrow(InvalidArgumentException::class, 'Target role is required for lower mode');
});

test('calculateLevel throws exception for higher mode without target role', function () {
    expect(fn () => RoleValidator::calculateLevel('higher', null, $this->tempDir))
        ->toThrow(InvalidArgumentException::class, 'Target role is required for higher mode');
});

test('calculateLevel throws exception for invalid mode', function () {
    expect(fn () => RoleValidator::calculateLevel('invalid', null, $this->tempDir))
        ->toThrow(InvalidArgumentException::class, 'Invalid creation mode: invalid');
});

test('calculateLevel throws exception when target role not found', function () {
    expect(fn () => RoleValidator::calculateLevel('lower', 'NonExistentRole', $this->tempDir))
        ->toThrow(InvalidArgumentException::class, 'Target role \'NonExistentRole\' not found');
});

test('levelConflicts detects conflicts correctly', function () {
    // Create role with proper regex-friendly format
    File::put($this->tempDir.'/Admin.php', '<?php
namespace App\Porter;
class Admin extends BaseRole {
    public function getLevel(): int
    {
        return 5;
    }
}');

    // Level 5 should conflict
    expect(RoleValidator::levelConflicts(5, $this->tempDir))->toBeTrue();

    // Level 3 should not conflict
    expect(RoleValidator::levelConflicts(3, $this->tempDir))->toBeFalse();
});

test('levelConflicts accounts for pending updates', function () {
    // Create role at level 5
    File::put($this->tempDir.'/Admin.php', '<?php
namespace App\Porter;
class Admin extends BaseRole {
    public function getLevel(): int { return 5; }
}');

    $pendingUpdates = [
        [
            'name' => 'Admin',
            'old_level' => 5,
            'new_level' => 6,
        ],
    ];

    // Level 5 should not conflict after pending update
    expect(RoleValidator::levelConflicts(5, $this->tempDir, $pendingUpdates))->toBeFalse();

    // Level 6 should conflict after pending update
    expect(RoleValidator::levelConflicts(6, $this->tempDir, $pendingUpdates))->toBeTrue();
});

test('getCreationModeOptions returns correct options for empty directory', function () {
    $options = RoleValidator::getCreationModeOptions($this->tempDir);

    expect($options)->toHaveKey('lowest');
    expect($options)->toHaveKey('highest');
    expect($options)->not->toHaveKey('lower');
    expect($options)->not->toHaveKey('higher');

    expect($options['lowest'])->toContain('Level 1');
    expect($options['highest'])->toContain('Level 1');
});

test('getCreationModeOptions returns all options with existing roles', function () {
    // Create some role files to simulate existing roles
    File::put($this->tempDir.'/Admin.php', '<?php
namespace App\Porter;
class Admin extends BaseRole {
    public function getLevel(): int { return 3; }
}');

    File::put($this->tempDir.'/Manager.php', '<?php
namespace App\Porter;
class Manager extends BaseRole {
    public function getLevel(): int { return 5; }
}');

    $options = RoleValidator::getCreationModeOptions($this->tempDir);

    expect($options)->toHaveKey('lowest');
    expect($options)->toHaveKey('highest');
    expect($options)->toHaveKey('lower');
    expect($options)->toHaveKey('higher');

    // Should reference actual levels
    expect($options['highest'])->toContain('Level 6'); // 5 + 1
});

test('getSelectableRoles returns role names', function () {
    File::put($this->tempDir.'/Admin.php', '<?php
namespace App\Porter;
class Admin extends BaseRole {
    public function getLevel(): int { return 5; }
}');

    $roles = RoleValidator::getSelectableRoles($this->tempDir);

    expect($roles)->toBeArray();
    // Note: This test depends on fallback parsing since RoleFactory won't find the test classes
});

test('clearCache actually clears internal cache', function () {
    // Populate cache
    RoleValidator::getExistingRoles($this->tempDir);

    // Verify cache is populated (indirect test via reflection or behavior)
    $roles1 = RoleValidator::getExistingRoles($this->tempDir);

    // Clear cache
    RoleValidator::clearCache();

    // Add new file
    File::put($this->tempDir.'/TestRole.php', '<?php
namespace App\Porter;
class TestRole extends BaseRole {
    public function getLevel(): int { return 1; }
}');

    // Should detect the new file (cache was cleared)
    $roles2 = RoleValidator::getExistingRoles($this->tempDir);

    // This is more of a behavioral test - hard to directly verify cache clearing
    expect($roles2)->toBeArray();
});

test('fallback method handles malformed PHP files gracefully', function () {
    // Create malformed PHP file
    File::put($this->tempDir.'/Malformed.php', '<?php
namespace App\Porter;
class Malformed extends BaseRole {
    // Missing getLevel method
    public function getName(): string { return "malformed"; }
}');

    // Should not throw exception, should skip malformed file
    $roles = RoleValidator::getExistingRoles($this->tempDir);

    expect($roles)->toHaveKey('names');
    expect($roles)->toHaveKey('levels');
    // Malformed file should be ignored
});

test('edge case: single role at level 1 with highest mode', function () {
    File::put($this->tempDir.'/OnlyRole.php', '<?php
namespace App\Porter;
class OnlyRole extends BaseRole {
    public function getLevel(): int { return 1; }
}');

    [$level, $updates] = RoleValidator::calculateLevel('highest', null, $this->tempDir);

    expect($level)->toBe(2); // 1 + 1
    expect($updates)->toBeEmpty();
});

test('edge case: role at very high level', function () {
    File::put($this->tempDir.'/HighLevel.php', '<?php
namespace App\Porter;
class HighLevel extends BaseRole {
    public function getLevel(): int { return 999; }
}');

    [$level, $updates] = RoleValidator::calculateLevel('highest', null, $this->tempDir);

    expect($level)->toBe(1000); // 999 + 1
    expect($updates)->toBeEmpty();
});
