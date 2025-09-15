<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Hdaklue\Porter\Concerns\CanBeAssignedToEntity;
use Hdaklue\Porter\Contracts\AssignableEntity;
use Hdaklue\Porter\Multitenancy\Contracts\PorterAssignableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements AssignableEntity, PorterAssignableContract
{
    use CanBeAssignedToEntity;
    use HasFactory;

    protected $table = 'test_users';

    protected $fillable = ['name', 'email', 'current_tenant_id'];

    protected $casts = [
        'id' => 'int',
    ];

    /**
     * Current tenant for this user (for testing multitenancy)
     */
    protected ?string $currentTenant = null;

    public function notify($instance)
    {
        return true;
    }

    /**
     * Get current tenant key for Porter role scoping
     */
    public function getCurrentTenantKey(): ?string
    {
        return $this->currentTenant ?? $this->current_tenant_id;
    }

    /**
     * Get the current tenant key for this assignable entity.
     */
    public function getPorterCurrentTenantKey(): ?string
    {
        return $this->currentTenant ?? $this->current_tenant_id;
    }

    /**
     * Set current tenant (for testing)
     */
    public function setCurrentTenant(?string $tenantId): self
    {
        $this->currentTenant = $tenantId;
        return $this;
    }

    /**
     * Helper method for tests - checks if user has role on entity
     */
    public function hasRoleOn($entity, $roleName): bool
    {
        try {
            $roleManager = app(\Hdaklue\Porter\RoleManager::class);
            return $roleManager->check($this, $entity, $roleName);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Factory definition
     */
    protected static function newFactory()
    {
        return \Tests\Fixtures\Factories\UserFactory::new();
    }
}