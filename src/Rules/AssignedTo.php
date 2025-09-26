<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Rules;

use Hdaklue\Porter\Contracts\AssignableEntity;
use Hdaklue\Porter\Contracts\RoleableEntity;
use Hdaklue\Porter\RoleManager;
use Illuminate\Contracts\Validation\ValidationRule;

final class AssignedTo implements ValidationRule
{
    public function __construct(
        private readonly AssignableEntity $assignable,
        private readonly RoleableEntity $entity
    ) {}

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $roleManager = app(RoleManager::class);

        if (! $roleManager->hasAnyRoleOn($this->assignable, $this->entity)) {
            $fail('The :attribute is not assigned to this entity.');
        }
    }
}
