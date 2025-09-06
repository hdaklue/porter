<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Concerns;

use Hdaklue\Porter\Contracts\RoleableEntity;
use Hdaklue\Porter\Contracts\RoleContract;
use Hdaklue\Porter\Facades\Porter;
use Hdaklue\Porter\Models\Roster;
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
        return $this->morphMany(config('porter.models.roster', Roster::class), 'assignable');
    }

    public function getAssignedEntitiesByType(string $type): Collection
    {
        return Porter::getAssignedEntitiesByType($this, $type);
    }

    /**
     * hasAssignmentOn.
     */
    public function hasAssignmentOn(RoleableEntity $target, RoleContract $role): bool
    {
        return Porter::hasRoleOn($this, $target, $role);

    }

    /**
     * isAssignedTo.
     */
    public function isAssignedTo(RoleableEntity $entity): bool
    {
        return Porter::hasAnyRoleOn($this, $entity);

    }

    public function isAtLeastOn(RoleContract $roleContract, RoleableEntity $roleableEntity)
    {
        return Porter::isAtLeastOn($this, $roleContract, $roleableEntity);
    }

    /**
     * getAssignmentOn.
     */
    public function getAssignmentOn(RoleableEntity $entity): ?RoleContract
    {
        return Porter::getRoleOn($this, $entity);

    }

    #[Scope]
    protected function scopeAssignedTo(Builder $builder, RoleableEntity $entity): Builder
    {
        $rosterModel = config('porter.models.roster', Roster::class);
        $rosterConnection = (new $rosterModel())->getConnectionName();
        $currentConnection = $builder->getModel()->getConnectionName();

        // If roster uses a different database connection, use direct query approach
        if ($rosterConnection !== $currentConnection) {
            $assignableIds = $rosterModel::where('roleable_type', $entity->getMorphClass())
                ->where('roleable_id', $entity->getKey())
                ->where('assignable_type', $builder->getModel()->getMorphClass())
                ->pluck('assignable_id');

            return $builder->whereIn($builder->getModel()->getKeyName(), $assignableIds);
        }

        // Use standard whereHas for same-database relationships
        return $builder->whereHas('roleAssignments', function ($query) use ($entity) {
            $query->where('roleable_type', $entity->getMorphClass())
                ->where('roleable_id', $entity->getKey());
        });
    }

    #[Scope]
    protected function scopeNotAssignedTo(Builder $builder, RoleableEntity $entity): Builder
    {
        $rosterModel = config('porter.models.roster', Roster::class);
        $rosterConnection = (new $rosterModel())->getConnectionName();
        $currentConnection = $builder->getModel()->getConnectionName();

        // If roster uses a different database connection, use direct query approach
        if ($rosterConnection !== $currentConnection) {
            $assignableIds = $rosterModel::where('roleable_type', $entity->getMorphClass())
                ->where('roleable_id', $entity->getKey())
                ->where('assignable_type', $builder->getModel()->getMorphClass())
                ->pluck('assignable_id');

            return $builder->whereNotIn($builder->getModel()->getKeyName(), $assignableIds);
        }

        // Use standard whereDoesntHave for same-database relationships
        return $builder->whereDoesntHave('roleAssignments', function ($query) use ($entity) {
            $query->where('roleable_type', $entity->getMorphClass())
                ->where('roleable_id', $entity->getKey());
        });
    }
}
