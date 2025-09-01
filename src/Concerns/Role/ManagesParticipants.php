<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Concerns\Role;

use Hdaklue\LaraRbac\Collections\Role\ParticipantsCollection;
use Hdaklue\LaraRbac\Contracts\Role\AssignableEntity;
use Hdaklue\LaraRbac\Enums\Role\RoleEnum;
use Hdaklue\LaraRbac\Facades\RoleManager;
use Hdaklue\LaraRbac\Models\RoleableHasRole;
use Hdaklue\LaraRbac\Models\Role;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

trait ManagesParticipants
{
    public function roleAssignments(): MorphMany
    {
        return $this->morphMany(config('lararbac.models.roleable_has_role', RoleableHasRole::class), 'roleable');
    }

    public function assignedRoles(): MorphToMany
    {

        return $this->morphToMany(config('lararbac.models.role', Role::class), 'roleable', config('lararbac.table_names.roleable_has_roles', 'roleable_has_roles'));
    }

    public function getForPerticipant(AssignableEntity $assignable): ?Collection
    {

        return RoleManager::getAssignedEntitiesByType($assignable, $this->getMorphClass());
    }

    public function isAdmin(AssignableEntity $entity): bool
    {
        return RoleManager::hasRoleOn($entity, $this, RoleEnum::ADMIN);
    }

    public function participants(): MorphMany
    {
        return $this->roleAssignments()
            ->with(['model', 'role']);
    }

    public function getParticipant(AssignableEntity|string|int $entity): ?AssignableEntity
    {
        if ($entity instanceof AssignableEntity) {
            $entity = $entity->getKey();
        }
        $roleableHasRole = $this->getParticipants()->filter(fn (RoleableHasRole $participant) => $participant->getModel()->getKey() === $entity)->first();

        if ($roleableHasRole) {
            return $roleableHasRole->getModel();
        }

        return null;
    }

    public function getParticipants(): ParticipantsCollection
    {
        return RoleManager::getParticipants($this);
    }

    public function addParticipant(AssignableEntity $user, RoleEnum|string $role, bool $silently = false): void
    {

        RoleManager::assign($user, $this, $role);
    }

    public function removeParticipant(AssignableEntity $user, ?bool $silently = false): void
    {
        RoleManager::remove($user, $this);

        if (! $silently) {
            // fire event
        }
    }

    public function isParticipant(AssignableEntity $entity): bool
    {
        return RoleManager::hasAnyRoleOn($entity, $this);
    }

    // public function changeParticipantRole(AssignableEntity $user, Role $newRole): void
    // {
    //     $this->removeParticipant($user, true);
    //     $this->addParticipant($user, $newRole, true);
    // }

    public function getParticipantRole(AssignableEntity $entity): ?Role
    {
        return RoleManager::getRoleOn($entity, $this);
    }

    #[Scope]
    protected function forParticipant(Builder $query, AssignableEntity $member): Builder
    {
        return $query->whereHas('roleAssignments', function ($q) use ($member) {
            $q->where('model_type', $member->getMorphClass())
                ->where('model_id', $member->getKey());
        });
    }
}
