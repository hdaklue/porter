<?php

namespace Hdaklue\Porter\Concerns;

use Hdaklue\Porter\Contracts\AssignableEntity;
use Hdaklue\Porter\Contracts\RoleContract;
use Hdaklue\Porter\Facades\Porter;
use Hdaklue\Porter\Models\Roster;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait ReceivesRoleAssignments
{
    /**
     * roleAssignments.
     */
    public function roleAssignments(): MorphMany
    {
        return $this->morphMany(config('porter.models.roster', Roster::class), 'roleable');
    }

    public function assign(AssignableEntity $entity, RoleContract $role): void
    {
        Porter::assign($entity, $this, $role->getName());
    }

    public function remove(AssignableEntity $entity): void
    {
        Porter::remove($entity, $this);
    }

    /**
     * Scope to find entities that have assignments from a specific assignable entity.
     */
    #[Scope]
    public function scopeWithAssignmentsFrom(Builder $query, AssignableEntity $assignable): Builder
    {
        return $query->whereHas('roleAssignments', function ($q) use ($assignable) {
            $q->where('assignable_type', $assignable->getMorphClass())
                ->where('assignable_id', $assignable->getKey());
        });
    }

    /**
     * Scope to find entities that have specific role assignments.
     */
    #[Scope]
    public function scopeWithRole(Builder $query, RoleContract $role): Builder
    {
        return $query->whereHas('roleAssignments', function ($q) use ($role) {
            $q->where('role_key', $role::getDbKey());
        });
    }

    /**
     * Scope to find entities that have assignments from a specific assignable entity with a specific role.
     */
    #[Scope]
    public function scopeWithAssignmentFromWithRole(Builder $query, AssignableEntity $assignable, RoleContract $role): Builder
    {
        return $query->whereHas('roleAssignments', function ($q) use ($assignable, $role) {
            $q->where('assignable_type', $assignable->getMorphClass())
                ->where('assignable_id', $assignable->getKey())
                ->where('role_key', $role::getDbKey());
        });
    }

    /**
     * Scope to find entities that have any role assignments.
     */
    #[Scope]
    public function scopeWithAnyAssignments(Builder $query): Builder
    {
        return $query->has('roleAssignments');
    }

    /**
     * Scope to find entities that have no role assignments.
     */
    #[Scope]
    public function scopeWithoutAssignments(Builder $query): Builder
    {
        return $query->doesntHave('roleAssignments');
    }
}
