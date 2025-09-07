<?php

declare(strict_types=1);

use Hdaklue\Porter\RoleManager;
use Hdaklue\Porter\Tests\Fixtures\TestAdmin;
use Hdaklue\Porter\Tests\Fixtures\TestEditor;
use Hdaklue\Porter\Tests\Fixtures\TestProject;
use Hdaklue\Porter\Tests\Fixtures\TestUser;
use Hdaklue\Porter\Tests\Fixtures\TestViewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

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
        $table->string('description')->nullable();
        $table->timestamps();
    });

    // Enable caching for all tests
    config(['porter.cache.enabled' => true]);

    // Clear any existing cache
    Cache::flush();

    $this->user = TestUser::create(['name' => 'Test User', 'email' => 'test@example.com']);
    $this->user2 = TestUser::create(['name' => 'Test User 2', 'email' => 'test2@example.com']);
    $this->project = TestProject::create(['name' => 'Test Project']);
    $this->project2 = TestProject::create(['name' => 'Test Project 2']);
    $this->roleManager = app(RoleManager::class);

    $this->adminRole = new TestAdmin();
    $this->editorRole = new TestEditor();
    $this->viewerRole = new TestViewer();
});

// Test hasRoleOn caching
it('caches hasRoleOn results and invalidates on changes', function () {
    // Test positive caching
    $this->roleManager->assign($this->user, $this->project, $this->adminRole);

    $result1 = $this->roleManager->hasRoleOn($this->user, $this->project, $this->adminRole);
    expect($result1)->toBeTrue();

    // Generate cache key
    $encryptedKey = $this->adminRole::getDbKey();
    $cacheKey = "porter:role_check_key:Hdaklue\\Porter\\Tests\\Fixtures\\TestUser:{$this->user->id}:Hdaklue\\Porter\\Tests\\Fixtures\\TestProject:{$this->project->id}:".hash('sha256', $encryptedKey);

    // Verify caching
    expect(Cache::has($cacheKey))->toBeTrue();
    expect(Cache::get($cacheKey))->toBeTrue();

    // Test cache invalidation on role removal
    $this->roleManager->remove($this->user, $this->project);
    expect(Cache::has($cacheKey))->toBeFalse();

    // Test negative caching
    $result2 = $this->roleManager->hasRoleOn($this->user, $this->project, $this->adminRole);
    expect($result2)->toBeFalse();
    expect(Cache::get($cacheKey))->toBeFalse();
});

it('caches hasRoleOn and invalidates on role changes', function () {
    $this->roleManager->assign($this->user, $this->project, $this->adminRole);

    // Cache admin role
    $this->roleManager->hasRoleOn($this->user, $this->project, $this->adminRole);

    $adminCacheKey = "porter:role_check_key:Hdaklue\\Porter\\Tests\\Fixtures\\TestUser:{$this->user->id}:Hdaklue\\Porter\\Tests\\Fixtures\\TestProject:{$this->project->id}:".hash('sha256', $this->adminRole::getDbKey());
    $editorCacheKey = "porter:role_check_key:Hdaklue\\Porter\\Tests\\Fixtures\\TestUser:{$this->user->id}:Hdaklue\\Porter\\Tests\\Fixtures\\TestProject:{$this->project->id}:".hash('sha256', $this->editorRole::getDbKey());

    expect(Cache::get($adminCacheKey))->toBeTrue();

    // Change role - should invalidate admin cache
    $this->roleManager->changeRoleOn($this->user, $this->project, $this->editorRole);

    expect(Cache::has($adminCacheKey))->toBeFalse();

    // Check new roles
    $hasAdmin = $this->roleManager->hasRoleOn($this->user, $this->project, $this->adminRole);
    $hasEditor = $this->roleManager->hasRoleOn($this->user, $this->project, $this->editorRole);

    expect($hasAdmin)->toBeFalse();
    expect($hasEditor)->toBeTrue();
    expect(Cache::get($editorCacheKey))->toBeTrue();
});

// Test getParticipantsWithRoles caching
it('caches getParticipantsWithRoles and invalidates properly', function () {
    // Test empty caching first
    $participants = $this->roleManager->getParticipantsWithRoles($this->project);
    expect($participants)->toHaveCount(0);

    $participantsCacheKey = "porter:participants:Hdaklue\\Porter\\Tests\\Fixtures\\TestProject:{$this->project->id}";
    expect(Cache::has($participantsCacheKey))->toBeTrue();

    // Assign roles - should clear cache
    $this->roleManager->assign($this->user, $this->project, $this->adminRole);
    expect(Cache::has($participantsCacheKey))->toBeFalse();

    // Test caching with data
    $participantsAfter = $this->roleManager->getParticipantsWithRoles($this->project);
    expect($participantsAfter)->toHaveCount(1);
    expect(Cache::has($participantsCacheKey))->toBeTrue();

    // Add another participant
    $this->roleManager->assign($this->user2, $this->project, $this->editorRole);
    expect(Cache::has($participantsCacheKey))->toBeFalse();

    $finalParticipants = $this->roleManager->getParticipantsWithRoles($this->project);
    expect($finalParticipants)->toHaveCount(2);
});

// Test getAssignedEntitiesByType caching
it('caches getAssignedEntitiesByType and invalidates properly', function () {
    // Test empty caching
    $entities = $this->roleManager->getAssignedEntitiesByType($this->user, TestProject::class);
    expect($entities)->toHaveCount(0);

    $entitiesCacheKey = "porter:Hdaklue\\Porter\\Tests\\Fixtures\\TestUser:{$this->user->id}_".TestProject::class.'_entities';
    expect(Cache::has($entitiesCacheKey))->toBeTrue();

    // Assign role - should clear cache
    $this->roleManager->assign($this->user, $this->project, $this->adminRole);
    expect(Cache::has($entitiesCacheKey))->toBeFalse();

    // Test caching with data
    $entitiesAfter = $this->roleManager->getAssignedEntitiesByType($this->user, TestProject::class);
    expect($entitiesAfter)->toHaveCount(1);
    expect(Cache::has($entitiesCacheKey))->toBeTrue();

    // Add another entity
    $this->roleManager->assign($this->user, $this->project2, $this->editorRole);
    expect(Cache::has($entitiesCacheKey))->toBeFalse();

    $finalEntities = $this->roleManager->getAssignedEntitiesByType($this->user, TestProject::class);
    expect($finalEntities)->toHaveCount(2);
});

// Test cache configuration
it('respects cache disabled configuration', function () {
    config(['porter.cache.enabled' => false]);

    $this->roleManager->assign($this->user, $this->project, $this->adminRole);

    // Make various cached calls
    $this->roleManager->hasRoleOn($this->user, $this->project, $this->adminRole);
    $this->roleManager->getParticipantsWithRoles($this->project);
    $this->roleManager->getAssignedEntitiesByType($this->user, TestProject::class);

    // No cache should exist
    $roleCheckKey = "porter:role_check_key:Hdaklue\\Porter\\Tests\\Fixtures\\TestUser:{$this->user->id}:Hdaklue\\Porter\\Tests\\Fixtures\\TestProject:{$this->project->id}:".hash('sha256', $this->adminRole::getDbKey());
    $participantsKey = "porter:participants:Hdaklue\\Porter\\Tests\\Fixtures\\TestProject:{$this->project->id}";
    $entitiesKey = "porter:Hdaklue\\Porter\\Tests\\Fixtures\\TestUser:{$this->user->id}_".TestProject::class.'_entities';

    expect(Cache::has($roleCheckKey))->toBeFalse();
    expect(Cache::has($participantsKey))->toBeFalse();
    expect(Cache::has($entitiesKey))->toBeFalse();
});

it('uses configurable cache TTLs', function () {
    config([
        'porter.cache.role_check_ttl' => 900,
        'porter.cache.participants_ttl' => 1800,
        'porter.cache.assigned_entities_ttl' => 3600,
        'porter.cache.ttl' => 2400, // default fallback
    ]);

    expect($this->roleManager->getCacheTtl('role_check'))->toBe(900);
    expect($this->roleManager->getCacheTtl('participants'))->toBe(1800);
    expect($this->roleManager->getCacheTtl('assigned_entities'))->toBe(3600);
    expect($this->roleManager->getCacheTtl('unknown'))->toBe(2400); // should use default
});

// Test bulk operations
it('clears cache for multiple targets via bulkClearCache', function () {
    $this->roleManager->assign($this->user, $this->project, $this->adminRole);
    $this->roleManager->assign($this->user, $this->project2, $this->editorRole);

    // Cache results
    $this->roleManager->getParticipantsWithRoles($this->project);
    $this->roleManager->getParticipantsWithRoles($this->project2);

    $cache1 = "porter:participants:Hdaklue\\Porter\\Tests\\Fixtures\\TestProject:{$this->project->id}";
    $cache2 = "porter:participants:Hdaklue\\Porter\\Tests\\Fixtures\\TestProject:{$this->project2->id}";

    expect(Cache::has($cache1))->toBeTrue();
    expect(Cache::has($cache2))->toBeTrue();

    // Bulk clear
    $this->roleManager->bulkClearCache(collect([$this->project, $this->project2]));

    expect(Cache::has($cache1))->toBeFalse();
    expect(Cache::has($cache2))->toBeFalse();
});

// Test cache key consistency
it('generates consistent cache keys for same parameters', function () {
    $key1 = "porter:role_check_key:Hdaklue\\Porter\\Tests\\Fixtures\\TestUser:{$this->user->id}:Hdaklue\\Porter\\Tests\\Fixtures\\TestProject:{$this->project->id}:".hash('sha256', $this->adminRole::getDbKey());
    $key2 = "porter:role_check_key:Hdaklue\\Porter\\Tests\\Fixtures\\TestUser:{$this->user->id}:Hdaklue\\Porter\\Tests\\Fixtures\\TestProject:{$this->project->id}:".hash('sha256', $this->adminRole::getDbKey());

    expect($key1)->toBe($key2);
});

it('uses cache prefix from configuration', function () {
    config(['porter.cache.key_prefix' => 'test_prefix']);

    $this->roleManager->assign($this->user, $this->project, $this->adminRole);
    $this->roleManager->hasRoleOn($this->user, $this->project, $this->adminRole);

    $customKey = "test_prefix:role_check_key:Hdaklue\\Porter\\Tests\\Fixtures\\TestUser:{$this->user->id}:Hdaklue\\Porter\\Tests\\Fixtures\\TestProject:{$this->project->id}:".hash('sha256', $this->adminRole::getDbKey());

    expect(Cache::has($customKey))->toBeTrue();
});

// Test comprehensive cache invalidation scenarios
it('handles complex role assignment scenarios with proper cache invalidation', function () {
    // Multiple users, multiple projects, different roles
    $this->roleManager->assign($this->user, $this->project, $this->adminRole);
    $this->roleManager->assign($this->user2, $this->project, $this->editorRole);
    $this->roleManager->assign($this->user, $this->project2, $this->viewerRole);

    // Cache various operations
    $this->roleManager->hasRoleOn($this->user, $this->project, $this->adminRole);
    $this->roleManager->hasRoleOn($this->user2, $this->project, $this->editorRole);
    $this->roleManager->getParticipantsWithRoles($this->project);
    $this->roleManager->getAssignedEntitiesByType($this->user, TestProject::class);

    $adminKey = "porter:role_check_key:Hdaklue\\Porter\\Tests\\Fixtures\\TestUser:{$this->user->id}:Hdaklue\\Porter\\Tests\\Fixtures\\TestProject:{$this->project->id}:".hash('sha256', $this->adminRole::getDbKey());
    $editorKey = "porter:role_check_key:Hdaklue\\Porter\\Tests\\Fixtures\\TestUser:{$this->user2->id}:Hdaklue\\Porter\\Tests\\Fixtures\\TestProject:{$this->project->id}:".hash('sha256', $this->editorRole::getDbKey());
    $participantsKey = "porter:participants:Hdaklue\\Porter\\Tests\\Fixtures\\TestProject:{$this->project->id}";
    $entitiesKey = "porter:Hdaklue\\Porter\\Tests\\Fixtures\\TestUser:{$this->user->id}_".TestProject::class.'_entities';

    // All should be cached
    expect(Cache::has($adminKey))->toBeTrue();
    expect(Cache::has($editorKey))->toBeTrue();
    expect(Cache::has($participantsKey))->toBeTrue();
    expect(Cache::has($entitiesKey))->toBeTrue();

    // Change user1's role on project1 - should affect related caches but not user2's
    $this->roleManager->changeRoleOn($this->user, $this->project, $this->viewerRole);

    // User1's admin cache should be cleared, participants cache should be cleared
    expect(Cache::has($adminKey))->toBeFalse();
    expect(Cache::has($participantsKey))->toBeFalse();
    expect(Cache::has($entitiesKey))->toBeFalse();

    // User2's editor cache should still exist (unaffected)
    expect(Cache::has($editorKey))->toBeTrue();

    // Verify final state
    expect($this->roleManager->hasRoleOn($this->user, $this->project, $this->adminRole))->toBeFalse();
    expect($this->roleManager->hasRoleOn($this->user, $this->project, $this->viewerRole))->toBeTrue();
    expect($this->roleManager->hasRoleOn($this->user2, $this->project, $this->editorRole))->toBeTrue();
});
