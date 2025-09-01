<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

use MargRbac\Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');
uses(TestCase::class)->in('Integration');
uses(TestCase::class)->in('Performance');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeValidUlid', function () {
    return $this->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
});

expect()->extend('toHaveValidTimestamps', function () {
    $model = $this->value;

    expect($model->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($model->updated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);

    return $this;
});

expect()->extend('toBeWithinMemoryLimit', function (int $maxMemoryMB = 5) {
    $memoryUsage = memory_get_peak_usage(true) / 1024 / 1024;

    return $this->toBeLessThanOrEqual($maxMemoryMB, "Memory usage: {$memoryUsage}MB exceeds limit of {$maxMemoryMB}MB");
});

expect()->extend('toBeWithinResponseTimeLimit', function (int $maxTimeMs = 226) {
    $responseTime = $this->value * 1000;

    return $this->toBeLessThanOrEqual($maxTimeMs, "Response time: {$responseTime}ms exceeds limit of {$maxTimeMs}ms");
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): string
{
    // Helper functions for testing
    return 'something';
}

/**
 * Create a user with specific tenant access
 */
function createUserWithTenantAccess(array $tenants = []): \MargRbac\Models\User
{
    $user = \MargRbac\Models\User::factory()->create();

    foreach ($tenants as $tenant) {
        $user->tenants()->attach($tenant);
    }

    return $user;
}

/**
 * Create a tenant with members
 */
function createTenantWithMembers(int $memberCount = 3): \MargRbac\Models\Tenant
{
    $tenant = \MargRbac\Models\Tenant::factory()->create();
    $users = \MargRbac\Models\User::factory()->count($memberCount)->create();

    foreach ($users as $user) {
        $tenant->users()->attach($user);
    }

    return $tenant;
}

/**
 * Assert that caching is working properly
 */
function assertCacheHit(string $key, mixed $expectedValue = null): void
{
    expect(\Illuminate\Support\Facades\Cache::has($key))->toBeTrue();

    if ($expectedValue !== null) {
        expect(\Illuminate\Support\Facades\Cache::get($key))->toEqual($expectedValue);
    }
}

/**
 * Assert that cache was cleared
 */
function assertCacheMiss(string $key): void
{
    expect(\Illuminate\Support\Facades\Cache::has($key))->toBeFalse();
}

/**
 * Measure execution time of a closure
 */
function measureExecutionTime(callable $callback): float
{
    $start = microtime(true);
    $callback();

    return microtime(true) - $start;
}

/**
 * Assert multi-tenant isolation
 */
function assertTenantIsolation(\MargRbac\Models\User $user, \MargRbac\Models\Tenant $allowedTenant, \MargRbac\Models\Tenant $restrictedTenant): void
{
    // User should have access to allowed tenant
    expect($user->canAccessTenant($allowedTenant))->toBeTrue();

    // User should not have access to restricted tenant
    expect($user->canAccessTenant($restrictedTenant))->toBeFalse();
}
