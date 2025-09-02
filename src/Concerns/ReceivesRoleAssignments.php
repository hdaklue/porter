<?php

namespace Hdaklue\Porter\Concerns;

use Hdaklue\Porter\Contracts\AssignableEntity;
use Hdaklue\Porter\Contracts\RoleInterface;
use Hdaklue\Porter\Facades\Porter;

trait ReceivesRoleAssignments
{
    public function assign(AssignableEntity $entity, string|RoleInterface $role): void
    {
        $roleKey = $role instanceof RoleInterface ? $role->getName() : $role;
        Porter::assign($entity, $this, $roleKey);
    }
}
