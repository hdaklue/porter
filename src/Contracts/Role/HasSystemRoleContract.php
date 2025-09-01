<?php

declare(strict_types=1);

namespace Hdaklue\MargRbac\Contracts\Role;

use Hdaklue\MargRbac\Models\Role;
use Illuminate\Support\Collection;

interface HasSystemRoleContract
{
    public function systemRoleByName(string $name): ?Role;

    public function getSystemRoles(): Collection;
}
