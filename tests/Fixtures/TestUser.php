<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests\Fixtures;

use Hdaklue\Porter\Concerns\CanBeAssignedToEntity;
use Hdaklue\Porter\Contracts\AssignableEntity;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable implements AssignableEntity
{
    use CanBeAssignedToEntity;

    protected $table = 'test_users';

    protected $fillable = ['name', 'email'];

    protected $casts = [
        'id' => 'int',
    ];

    public function notify($instance)
    {
        // Test implementation - no action needed
        return true;
    }

    /**
     * Helper method for tests - checks if user has role on entity
     */
    public function hasRoleOn($entity, $roleName): bool
    {
        try {
            $roleManager = app(\Hdaklue\Porter\RoleManager::class);

            // Handle string role names by converting to role objects
            if (is_string($roleName)) {
                $roleClass = match ($roleName) {
                    'TestAdmin' => \Hdaklue\Porter\Tests\Fixtures\TestAdmin::class,
                    'TestEditor' => \Hdaklue\Porter\Tests\Fixtures\TestEditor::class,
                    'TestViewer' => \Hdaklue\Porter\Tests\Fixtures\TestViewer::class,
                    default => null
                };

                if (! $roleClass) {
                    return false;
                }

                $role = new $roleClass();
            } else {
                $role = $roleName;
            }

            // Additional null check to prevent type errors
            if (! $role) {
                return false;
            }

            return $roleManager->check($this, $entity, $role);
        } catch (\Exception $e) {
            return false;
        }
    }
}
