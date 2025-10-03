<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Cache;

use Hdaklue\Porter\Contracts\AssignableEntity;
use Hdaklue\Porter\Contracts\RoleableEntity;
use Hdaklue\Porter\Multitenancy\Contracts\PorterAssignableContract;
use Hdaklue\Porter\RoleFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class RoleCacheManager
{
    /**
     * Check if caching is enabled in configuration.
     */
    public static function isEnabled(): bool
    {
        return config('porter.cache.enabled', true);
    }

    /**
     * Get the cache TTL for a specific cache type.
     */
    public static function getTtl(string $type): int
    {
        return match ($type) {
            'role_check' => (int) config('porter.cache.role_check_ttl', config('porter.cache.ttl', 1800)),
            'participants' => (int) config('porter.cache.participants_ttl', config('porter.cache.ttl', 3600)),
            'assigned_entities' => (int) config('porter.cache.assigned_entities_ttl', config('porter.cache.ttl', 3600)),
            default => (int) config('porter.cache.ttl', 3600)
        };
    }

    /**
     * Generate a cache key for participants of a target entity.
     */
    public static function generateParticipantsCacheKey(RoleableEntity $target): string
    {
        $prefix = config('porter.cache.key_prefix', 'porter');

        return "{$prefix}:participants:{$target->getMorphClass()}:{$target->getKey()}";
    }

    /**
     * Generate a cache key for assigned entities.
     */
    public static function generateAssignedEntitiesCacheKey(AssignableEntity $target, string $type): string
    {
        $prefix = config('porter.cache.key_prefix', 'porter');

        return "{$prefix}:{$target->getMorphClass()}:{$target->getKey()}_{$type}_entities";
    }

    /**
     * Generate a cache key for role check using an encrypted key.
     * Uses SHA-256 hash of an encrypted key for cache key stability.
     */
    public static function generateRoleCheckCacheKeyByEncryptedKey(AssignableEntity $user, RoleableEntity $target, string $encryptedKey): string
    {
        $prefix = config('porter.cache.key_prefix', 'porter');
        $tenantKey = self::getTenantCacheKey($user, $target);

        return "{$prefix}:role_check_key{$tenantKey}:{$user->getMorphClass()}:{$user->getKey()}:{$target->getMorphClass()}:{$target->getKey()}:".hash('sha256', $encryptedKey);
    }

    /**
     * Clear all caches related to a target entity.
     * Optionally clear caches for a specific user-target combination.
     */
    public static function clearCache(RoleableEntity $target, ?AssignableEntity $user = null): void
    {
        // Forget participants cache
        Cache::forget(self::generateParticipantsCacheKey($target));

        if ($user) {
            // Forget assignable entity cache for this specific type
            Cache::forget(self::generateAssignedEntitiesCacheKey($user, $target->getMorphClass()));

            // Clear all role check caches for this user-target combination
            self::clearRoleCheckCaches($user, $target);

            // Clear assigned entities cache for all types for this user
            self::clearAllAssignedEntitiesCaches($user);
        } else {
            // If no user specified, clear all participant-related caches
            self::clearAllParticipantRelatedCaches($target);
        }
    }

    /**
     * Clear caches for multiple target entities in bulk.
     */
    public static function bulkClearCache(Collection $targets): void
    {
        $targets->each(fn (RoleableEntity $target) => self::clearCache($target));
    }

    /**
     * Clear role check caches for all registered roles for a user-target combination.
     * Ensures both old and new role caches are cleared during role changes.
     */
    public static function clearRoleCheckCaches(AssignableEntity $user, RoleableEntity $target): void
    {
        // Clear cache for ALL registered roles for this user-target combination
        // This ensures both old and new role caches are cleared during role changes
        foreach (RoleFactory::getAllWithKeys() as $roleKey => $roleClass) {
            try {
                $role = new $roleClass();
                $encryptedKey = $role::getDbKey();
                $cacheKey = self::generateRoleCheckCacheKeyByEncryptedKey($user, $target, $encryptedKey);
                Cache::forget($cacheKey);
            } catch (\Exception) {
                // Skip invalid roles
            }
        }
    }

    /**
     * Clear cache for a specific role check.
     */
    public static function clearRoleCheckCache(AssignableEntity $user, RoleableEntity $target, string $encryptedKey): void
    {
        $cacheKey = self::generateRoleCheckCacheKeyByEncryptedKey($user, $target, $encryptedKey);
        Cache::forget($cacheKey);
    }

    /**
     * Clear assigned entities caches for all common entity types for a user.
     */
    public static function clearAllAssignedEntitiesCaches(AssignableEntity $user): void
    {
        $prefix = config('porter.cache.key_prefix', 'porter');

        // Clear assigned entities caches for common entity types
        $commonTypes = ['App\\Models\\User', 'App\\Models\\Project', 'App\\Models\\Organization', 'App\\Models\\Team'];
        foreach ($commonTypes as $type) {
            $cacheKey = "{$prefix}:{$user->getMorphClass()}:{$user->getKey()}_{$type}_entities";
            Cache::forget($cacheKey);
        }
    }

    /**
     * Clear all participant-related caches for a target entity.
     */
    public static function clearAllParticipantRelatedCaches(RoleableEntity $target): void
    {
        // For now, just clear the participants cache
        // In a more comprehensive implementation, we might clear related caches
        Cache::forget(self::generateParticipantsCacheKey($target));
    }

    /**
     * Remember a value in cache or return cached value.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Get tenant cache key segment for cache scoping.
     */
    private static function getTenantCacheKey(AssignableEntity $user, RoleableEntity $target): string
    {
        if (! config('porter.multitenancy.enabled', false) || ! config('porter.multitenancy.cache_per_tenant', true)) {
            return '';
        }

        $tenantKey = $user instanceof PorterAssignableContract ? $user->getPorterCurrentTenantKey() : null;

        return $tenantKey ? ":t:{$tenantKey}" : '';
    }
}
