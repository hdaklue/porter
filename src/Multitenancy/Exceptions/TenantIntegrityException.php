<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Multitenancy\Exceptions;

use Exception;

class TenantIntegrityException extends Exception
{
    public static function mismatch(mixed $assignableTenant, mixed $roleableTenant): self
    {
        return new self(
            "Tenant integrity violation: Assignable entity belongs to tenant '{$assignableTenant}' but roleable entity belongs to tenant '{$roleableTenant}'. Both entities must belong to the same tenant."
        );
    }

    public static function assignableWithoutTenant(): self
    {
        return new self(
            'Tenant integrity violation: Assignable entity does not have a tenant context, but multitenancy is enabled.'
        );
    }

    public static function roleableWithoutTenant(): self
    {
        return new self(
            'Tenant integrity violation: Roleable entity does not have a tenant context, but multitenancy is enabled.'
        );
    }
}
