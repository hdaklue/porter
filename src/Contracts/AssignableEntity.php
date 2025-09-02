<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

interface AssignableEntity
{
    /**
     * All role assignments this entity holds.
     */
    public function roleAssignments(): MorphMany;

    public function getAssignedEntitiesByType(string $type): Collection;

    /**
     * Get the morph class (used in model_type).
     */
    public function getMorphClass();

    /**
     * Unique identifier of the actor (model_id).
     */
    public function getKey();

    /**
     * Just for IDE Support.
     */
    public function notify($instance);
}
