<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Multitenancy\Concerns;

trait HasPorterTenantScope
{
    /**
     * Get the tenant key that this roleable entity belongs to.
     * Override this method to customize how the tenant is resolved.
     */
    public function getPorterTenantKey(): ?string
    {
        // Default implementation - override in your model
        // Example: return $this->team_id;
        // Example: return $this->tenant_id;
        return null;
    }
}