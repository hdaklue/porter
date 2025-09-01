<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Facades;

use Hdaklue\LaraRbac\Contracts\Role\RoleAssignmentManagerInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void assign(\Hdaklue\LaraRbac\Contracts\Role\AssignableEntity $user, \Hdaklue\LaraRbac\Contracts\Role\RoleableEntity $target, string|\Hdaklue\LaraRbac\Enums\Role\RoleEnum $role)
 * @method static void remove(\Hdaklue\LaraRbac\Contracts\Role\AssignableEntity $user, \Hdaklue\LaraRbac\Contracts\Role\RoleableEntity $target)
 * @method static \Illuminate\Support\Collection getAssignedEntitiesByType(\Hdaklue\LaraRbac\Contracts\Role\AssignableEntity $entity, string $type)
 * @method static bool hasRoleOn(\Hdaklue\LaraRbac\Contracts\Role\AssignableEntity $user, \Hdaklue\LaraRbac\Contracts\Role\RoleableEntity $target, string|\Hdaklue\LaraRbac\Enums\Role\RoleEnum $role)
 * @method static bool hasAnyRoleOn(\Hdaklue\LaraRbac\Contracts\Role\AssignableEntity $user, \Hdaklue\LaraRbac\Contracts\Role\RoleableEntity $target)
 * @method static \Hdaklue\LaraRbac\Collections\Role\ParticipantsCollection getParticipants(\Hdaklue\LaraRbac\Contracts\Role\RoleableEntity $target)
 * @method static \Hdaklue\LaraRbac\Models\Role|null getRoleOn(\Hdaklue\LaraRbac\Contracts\Role\AssignableEntity $user, \Hdaklue\LaraRbac\Contracts\Role\RoleableEntity $target)
 * @method static void changeRoleOn(\Hdaklue\LaraRbac\Contracts\Role\AssignableEntity $user, \Hdaklue\LaraRbac\Contracts\Role\RoleableEntity $target, string|\Hdaklue\LaraRbac\Enums\Role\RoleEnum $role)
 * @method static void clearCache(\Hdaklue\LaraRbac\Contracts\Role\RoleableEntity $target)
 * @method static void bulkClearCache(\Illuminate\Support\Collection $targets)
 *
 * @see \Hdaklue\LaraRbac\Services\Role\RoleAssignmentService
 */
class RoleManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RoleAssignmentManagerInterface::class;
    }
}