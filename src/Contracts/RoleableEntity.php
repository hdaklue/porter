<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Contracts;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Interface for entities that can have roles assigned to participants.
 */
interface RoleableEntity extends Arrayable
{
    /**
     * All role assignments attached to this entity.
     */
    public function roleAssignments(): MorphMany;

    /**
     * Get the morph class (used in roleable_type).
     */
    public function getMorphClass();

    /**
     * Unique identifier of the entity (roleable_id).
     */
    public function getKey();
}
