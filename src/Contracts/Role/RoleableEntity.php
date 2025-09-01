<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Contracts\Role;

use Hdaklue\LaraRbac\Collections\Role\ParticipantsCollection;
use Hdaklue\LaraRbac\Enums\Role\RoleEnum;
use Hdaklue\LaraRbac\Models\Role;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Interface for entities that can have roles assigned to participants.
 */
interface RoleableEntity extends Arrayable
{
    public function addParticipant(AssignableEntity $target, string|RoleEnum $role): void;

    /**
     * All role assignments attached to this entity.
     */
    public function roleAssignments(): MorphMany;

    public function assignedRoles(): MorphToMany;

    public function getParticipants(): ParticipantsCollection;

    public function getParticipantRole(AssignableEntity $participant): ?Role;

    /**
     * Just to enhance IDE support.
     */
    public function loadMissing($relations);

    public function participants(): MorphMany;

    public function getParticipant(AssignableEntity|string|int $entity): ?AssignableEntity;

    /**
     * Remove a participant's specific role(s).
     */
    public function removeParticipant(AssignableEntity $user, ?bool $silently = false);

    /**
     * Get the morph class (used in roleable_type).
     */
    public function getMorphClass();

    /**
     * Unique identifier of the entity (roleable_id).
     */
    public function getKey();
}