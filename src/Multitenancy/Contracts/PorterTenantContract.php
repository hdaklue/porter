<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Multitenancy\Contracts;

interface PorterTenantContract
{
    /**
     * Get the tenant key for Porter role scoping.
     * For tenant entities, this should return the entity's own key.
     */
    public function getPorterTenantKey(): ?string;
}