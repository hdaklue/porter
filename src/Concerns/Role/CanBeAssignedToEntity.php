<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Concerns\Role;

use Hdaklue\LaraRbac\Contracts\Role\RoleableEntity;
use Hdaklue\LaraRbac\Enums\Role\RoleEnum;
use Hdaklue\LaraRbac\Facades\RoleManager;
use Hdaklue\LaraRbac\Models\RoleableHasRole;
use Hdaklue\LaraRbac\Models\Role;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait CanBeAssignedToEntity
{
    /**
     * roleAssignments.
     */
    public function roleAssignments(): MorphMany
    {
        return $this->morphMany(config('lararbac.models.roleable_has_role', RoleableHasRole::class), 'model');
    }

    public function getAssignedEntitiesByType(string $type): Collection
    {
        return RoleManager::getAssignedEntitiesByType($this, $type);
    }

    /**
     * hasAssignmentOn.
     */
    public function hasAssignmentOn(RoleableEntity $target, string|RoleEnum $roleName): bool
    {
        return RoleManager::hasRoleOn($this, $target, $roleName);

    }

    /**
     * isAssignedTo.
     */
    public function isAssignedTo(RoleableEntity $entity): bool
    {
        return RoleManager::hasAnyRoleOn($this, $entity);

    }

    /**
     * getAssignmentOn.
     */
    public function getAssignmentOn(RoleableEntity $entity): ?Role
    {
        return RoleManager::getRoleOn($this, $entity);

    }

    #[Scope]
    protected function scopeAssignedTo(Builder $builder, RoleableEntity $entity): Builder
    {
        return $builder->whereHas('roleAssignments', function ($query) use ($entity) {
            $query->where('roleable_type', $entity->getMorphClass())
                ->where('roleable_id', $entity->getKey());
        });
    }

    #[Scope]
    protected function scopeNotAssignedTo(Builder $builder, RoleableEntity $entity): Builder
    {
        return $builder->whereDoesntHave('roleAssignments', function ($query) use ($entity) {
            $query->where('roleable_type', $entity->getMorphClass())
                ->where('roleable_id', $entity->getKey());
        });
    }
}
