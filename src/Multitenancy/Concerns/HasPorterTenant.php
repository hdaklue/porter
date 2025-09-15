<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Multitenancy\Concerns;

trait HasPorterTenant
{
    /**
     * Get the current tenant key for this assignable entity.
     * Override this method to customize how the current tenant is resolved.
     */
    public function getCurrentTenantKey(): ?string
    {
        // Default implementation - override in your model
        // Example: return auth()->user()->current_team_id;
        // Example: return session('current_tenant_id');
        return null;
    }
}