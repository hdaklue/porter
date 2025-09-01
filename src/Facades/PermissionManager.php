<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Facades;

use Hdaklue\LaraRbac\Contracts\Permission\PermissionManagerInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool can($user, string $action, $entity)
 * @method static bool canByRoles(array $roles, string $action, string $entityType)
 * @method static array getPermissions(string $entityType)
 * @method static void reloadPermissions(?string $entityType = null)
 * @method static bool validatePermissionFile(string $entityType)
 *
 * @see \Hdaklue\LaraRbac\Services\Permission\JsonPermissionManager
 */
class PermissionManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PermissionManagerInterface::class;
    }
}