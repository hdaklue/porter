<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Multitenancy\Contracts;

interface PorterAssignableContract
{
    /**
     * Get the current tenant key for this assignable entity.
     * This determines which tenant context this entity operates in.
     *
     * @return string|null The tenant key, or null if not in a tenant context
     */
    public function getPorterCurrentTenantKey(): ?string;
}
