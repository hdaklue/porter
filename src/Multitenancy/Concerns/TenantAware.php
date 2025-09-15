<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Multitenancy\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait TenantAware
{
    /**
     * Scope queries to a specific tenant.
     */
    public function scopeForTenant(Builder $query, mixed $tenantId): Builder
    {
        $tenantColumn = config('porter.multitenancy.tenant_column', 'tenant_id');
        
        if ($tenantId === null) {
            return $query->whereNull($tenantColumn);
        }

        return $query->where($tenantColumn, $tenantId);
    }

    /**
     * Scope queries to exclude records with any tenant_id (show only non-tenant records).
     */
    public function scopeWithoutTenant(Builder $query): Builder
    {
        $tenantColumn = config('porter.multitenancy.tenant_column', 'tenant_id');
        
        return $query->whereNull($tenantColumn);
    }

    /**
     * Scope queries to include records from all tenants (bypass any tenant filtering).
     */
    public function scopeForAllTenants(Builder $query): Builder
    {
        // This scope doesn't add any constraints, allowing all records
        return $query;
    }

    /**
     * Get the tenant ID for this model.
     */
    public function getTenantId(): mixed
    {
        if (!config('porter.multitenancy.enabled', false)) {
            return null;
        }

        $tenantColumn = config('porter.multitenancy.tenant_column', 'tenant_id');
        
        return $this->getAttribute($tenantColumn);
    }

    /**
     * Set the tenant ID for this model.
     */
    public function setTenantId(mixed $tenantId): self
    {
        if (config('porter.multitenancy.enabled', false)) {
            $tenantColumn = config('porter.multitenancy.tenant_column', 'tenant_id');
            $this->setAttribute($tenantColumn, $tenantId);
        }

        return $this;
    }

    /**
     * Check if this model belongs to a specific tenant.
     */
    public function belongsToTenant(mixed $tenantId): bool
    {
        if (!config('porter.multitenancy.enabled', false)) {
            return true; // No multitenancy = belongs to all
        }

        return $this->getTenantId() === $tenantId;
    }
}