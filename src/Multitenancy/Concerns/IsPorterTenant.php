<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Multitenancy\Concerns;

trait IsPorterTenant
{
    /**
     * Get the tenant key for Porter role scoping.
     * For tenant entities, this returns the entity's own key.
     */
    public function getPorterTenantKey(): ?string
    {
        return (string) $this->getKey();
    }
}