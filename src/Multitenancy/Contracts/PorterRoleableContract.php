<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Multitenancy\Contracts;

interface PorterRoleableContract
{
    /**
     * Get the tenant key that this roleable entity belongs to.
     * This determines which tenant this entity is scoped to.
     *
     * @return string|null The tenant key, or null if not tenant-scoped
     */
    public function getPorterTenantKey(): ?string;
}
