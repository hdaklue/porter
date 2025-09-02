<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Facades;

use Hdaklue\Porter\RoleManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void assign(\Hdaklue\Porter\Contracts\AssignableEntity $user, \Hdaklue\Porter\Contracts\RoleableEntity $target, string|\Hdaklue\Porter\Contracts\RoleContract $role)
 * @method static void remove(\Hdaklue\Porter\Contracts\AssignableEntity $user, \Hdaklue\Porter\Contracts\RoleableEntity $target)
 * @method static \Illuminate\Support\Collection getAssignedEntitiesByType(\Hdaklue\Porter\Contracts\AssignableEntity $entity, string $type)
 * @method static bool hasRoleOn(\Hdaklue\Porter\Contracts\AssignableEntity $user, \Hdaklue\Porter\Contracts\RoleableEntity $target, string|\Hdaklue\Porter\Contracts\RoleContract $role)
 * @method static bool hasAnyRoleOn(\Hdaklue\Porter\Contracts\AssignableEntity $user, \Hdaklue\Porter\Contracts\RoleableEntity $target)
 * @method static \Illuminate\Support\Collection getParticipantsHasRole(\Hdaklue\Porter\Contracts\RoleableEntity $target, string|\Hdaklue\Porter\Contracts\RoleContract $role)
 * @method static string|null getRoleOn(\Hdaklue\Porter\Contracts\AssignableEntity $user, \Hdaklue\Porter\Contracts\RoleableEntity $target)
 * @method static void changeRoleOn(\Hdaklue\Porter\Contracts\AssignableEntity $user, \Hdaklue\Porter\Contracts\RoleableEntity $target, string|\Hdaklue\Porter\Contracts\RoleContract $role)
 * @method static void clearCache(\Hdaklue\Porter\Contracts\RoleableEntity $target)
 * @method static void bulkClearCache(\Illuminate\Support\Collection $targets)
 *
 * @see \Hdaklue\Porter\RoleManager
 */
class Porter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RoleManager::class;
    }
}
