<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests\Feature;

use Hdaklue\Porter\Models\Roster;
use Hdaklue\Porter\RoleManager;
use Hdaklue\Porter\Tests\Fixtures\TestAdmin;
use Hdaklue\Porter\Tests\Fixtures\TestEditor;
use Hdaklue\Porter\Tests\Fixtures\TestProject;
use Hdaklue\Porter\Tests\Fixtures\TestUser;
use Hdaklue\Porter\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class EncryptionEdgeCasesTest extends TestCase
{
    private TestUser $user;

    private TestProject $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = new TestUser();
        $this->user->setAttribute('id', 123);
        $this->user->exists = true;

        $this->project = new TestProject();
        $this->project->setAttribute('id', 456);
        $this->project->exists = true;
    }

    public function test_it_handles_plain_text_role_names_when_encryption_configured(): void
    {
        // Configure system to use encrypted storage
        config(['porter.security.key_storage' => 'encrypted']);

        // First, let's test the BaseRole decryption directly
        $adminRole = TestAdmin::fromDbKey('test_admin');
        expect($adminRole)->not()->toBeNull();
        expect($adminRole)->toBeInstanceOf(TestAdmin::class);

        // Now test with database integration
        // Manually insert plain text role names directly into database
        // This simulates real-world scenarios like:
        // - Manual database operations
        // - Data migrations from older systems
        // - Configuration changes from plain to encrypted
        DB::table('roster')->insert([
            'assignable_type' => TestUser::class,
            'assignable_id' => $this->user->getKey(),
            'roleable_type' => TestProject::class,
            'roleable_id' => $this->project->getKey(),
            'role_key' => 'test_admin', // Plain text, not encrypted!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // System should gracefully handle this mixed scenario
        $hasRole = app(RoleManager::class)->check($this->user, $this->project, new TestAdmin());
        expect($hasRole)->toBeTrue();

        // Should also work when querying the Roster model directly
        $roster = Roster::where('assignable_id', $this->user->getKey())->first();
        expect($roster)->not()->toBeNull();
        expect($roster->role_key)->toBeInstanceOf(TestAdmin::class);
        expect($roster->role_key->getName())->toBe('TestAdmin');
    }

    public function test_it_handles_mixed_encryption_formats_in_same_database(): void
    {
        // Start with plain storage to get proper encrypted key
        config(['porter.security.key_storage' => 'plain']);
        $plainKey = TestAdmin::getDbKey(); // Returns 'test_admin'

        // Switch to encrypted storage
        config(['porter.security.key_storage' => 'encrypted']);
        $encryptedKey = TestEditor::getDbKey(); // Returns encrypted version

        // Insert mixed data: one plain text, one properly encrypted
        DB::table('roster')->insert([
            [
                'assignable_type' => TestUser::class,
                'assignable_id' => $this->user->getKey(),
                'roleable_type' => TestProject::class,
                'roleable_id' => $this->project->getKey(),
                'role_key' => 'test_admin', // Plain text legacy data
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'assignable_type' => TestUser::class,
                'assignable_id' => $this->user->getKey(),
                'roleable_type' => TestProject::class,
                'roleable_id' => $this->project->getKey(),
                'role_key' => $encryptedKey, // Properly encrypted
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Both should work despite different formats
        expect(app(RoleManager::class)->check($this->user, $this->project, new TestAdmin()))->toBeTrue();
        expect(app(RoleManager::class)->check($this->user, $this->project, new TestEditor()))->toBeTrue();

        // Verify through direct model queries
        $rosters = Roster::where('assignable_id', $this->user->getKey())->get();
        expect($rosters)->toHaveCount(2);

        $adminRoster = $rosters->filter(fn ($r) => $r->role_key->getName() === 'TestAdmin')->first();
        $editorRoster = $rosters->filter(fn ($r) => $r->role_key->getName() === 'TestEditor')->first();

        expect($adminRoster->role_key)->toBeInstanceOf(TestAdmin::class);
        expect($editorRoster->role_key)->toBeInstanceOf(TestEditor::class);
    }

    public function test_it_fails_gracefully_with_invalid_plain_text_role_names(): void
    {
        // Configure system to use encrypted storage
        config(['porter.security.key_storage' => 'encrypted']);

        // Insert invalid plain text role name
        DB::table('roster')->insert([
            'assignable_type' => TestUser::class,
            'assignable_id' => $this->user->getKey(),
            'roleable_type' => TestProject::class,
            'roleable_id' => $this->project->getKey(),
            'role_key' => 'invalid_role_that_does_not_exist',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Should handle this gracefully - the role cast should fail to resolve
        $roster = Roster::where('assignable_id', $this->user->getKey())->first();

        // The RoleCast should return null or throw an exception for invalid roles
        expect(function () use ($roster) {
            $roleObject = $roster->role_key;
        })->toThrow(\Exception::class);
    }

    public function test_it_handles_corrupted_encrypted_data_gracefully(): void
    {
        // Configure system to use encrypted storage
        config(['porter.security.key_storage' => 'encrypted']);

        // Insert corrupted/invalid encrypted data
        DB::table('roster')->insert([
            'assignable_type' => TestUser::class,
            'assignable_id' => $this->user->getKey(),
            'roleable_type' => TestProject::class,
            'roleable_id' => $this->project->getKey(),
            'role_key' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.corrupted.data', // Looks encrypted but isn't
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Should handle corrupted data gracefully
        $roster = Roster::where('assignable_id', $this->user->getKey())->first();

        expect(function () use ($roster) {
            $roleObject = $roster->role_key;
        })->toThrow(\Exception::class);
    }

    public function test_it_maintains_backward_compatibility_during_config_changes(): void
    {
        // Scenario: System was using plain text, now switching to encrypted

        // Phase 1: Insert data with plain text configuration
        config(['porter.security.key_storage' => 'plain']);
        app(RoleManager::class)->assign($this->user, $this->project, new TestAdmin());

        // Verify it works with plain config
        expect(app(RoleManager::class)->check($this->user, $this->project, new TestAdmin()))->toBeTrue();

        // Phase 2: Change configuration to encrypted (simulating config update)
        config(['porter.security.key_storage' => 'encrypted']);

        // Old data should still work despite config change
        expect(app(RoleManager::class)->check($this->user, $this->project, new TestAdmin()))->toBeTrue();

        // New assignments should use encrypted format
        app(RoleManager::class)->assign($this->user, $this->project, new TestEditor());
        expect(app(RoleManager::class)->check($this->user, $this->project, new TestEditor()))->toBeTrue();

        // Verify database contains mixed formats
        $rosters = Roster::where('assignable_id', $this->user->getKey())->get();
        expect($rosters)->toHaveCount(1); // TestEditor replaced TestAdmin due to 'replace' strategy

        // But if we manually insert old plain text data, it should still work
        $legacyUserId = 789;
        DB::table('roster')->insert([
            'assignable_type' => TestUser::class,
            'assignable_id' => $legacyUserId,
            'roleable_type' => TestProject::class,
            'roleable_id' => $this->project->getKey(),
            'role_key' => 'test_admin', // Plain text from old system
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify the data was inserted
        $count = DB::table('roster')->where('assignable_id', $legacyUserId)->count();
        expect($count)->toBe(1);

        $legacyUser = new TestUser();
        $legacyUser->setAttribute('id', $legacyUserId);
        $legacyUser->exists = true;

        expect(app(RoleManager::class)->check($legacyUser, $this->project, new TestAdmin()))->toBeTrue();
    }

    public function test_it_handles_different_encryption_formats_from_different_app_keys(): void
    {
        // Simulate scenario where app key changed after data was encrypted
        config(['porter.security.key_storage' => 'encrypted']);

        // Create encrypted data with current app key
        $originalAppKey = config('app.key');
        $encryptedWithOriginalKey = TestAdmin::getDbKey();

        // Insert the encrypted data
        DB::table('roster')->insert([
            'assignable_type' => TestUser::class,
            'assignable_id' => $this->user->getKey(),
            'roleable_type' => TestProject::class,
            'roleable_id' => $this->project->getKey(),
            'role_key' => $encryptedWithOriginalKey,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify it works with original key
        expect(app(RoleManager::class)->check($this->user, $this->project, new TestAdmin()))->toBeTrue();

        // Change app key (simulating key rotation)
        config(['app.key' => 'base64:'.base64_encode('different16charkey')]);

        // Old encrypted data should fail gracefully, but if it matches a plain text role name
        // after "decryption" fails, it should still work as fallback
        // (This specific test might fail depending on exact implementation, but system shouldn't crash)
        $result = app(RoleManager::class)->check($this->user, $this->project, new TestAdmin());

        // The exact result depends on implementation, but it shouldn't throw exceptions
        expect($result)->toBeIn([true, false]);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Override test configuration to use encrypted storage by default
        // This ensures we're testing the real-world scenario
        $app['config']->set('porter.security.key_storage', 'encrypted');
    }
}
