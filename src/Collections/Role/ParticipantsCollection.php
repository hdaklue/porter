<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Collections\Role;

use Hdaklue\Porter\Contracts\AssignableEntity;
use Illuminate\Support\Collection;

final class ParticipantsCollection
{
    public function __construct(private Collection $participants)
    {
    }
    /**
     * Get the underlying collection.
     */
    public function toCollection(): Collection
    {
        return $this->participants;
    }

    /**
     * Convert the collection to a collection of basic participant data.
     */
    public function asBasicArray(): Collection
    {
        return $this->participants->map(fn ($item) => [
            'participant_id' => $item->assignable->getKey(),
            'participant_name' => $item->assignable->getAttribute('name'),
            'role_key' => $item->role_key,
            'role_name' => $item->role()?->getName(),
            'role_label' => $item->role()?->getLabel(),
            'role_description' => $item->role()?->getDescription(),
        ]);
    }

    public function getParticipantIds(): Collection
    {
        return $this->participants->pluck('assignable.id');
    }

    public function getParticipantsAsSelectArray(): array
    {
        return $this->participants->pluck('assignable')->mapWithKeys(fn ($model) => 
            [$model->getKey() => $model->getAttribute('name')]
        )->toArray();
    }

    public function exceptAssignable(AssignableEntity|string|array $userId): Collection
    {
        if ($userId instanceof AssignableEntity) {
            $userId = [$userId->getKey()];
        }

        if (is_string($userId)) {
            $userId = [$userId];
        }

        return $this->participants->reject(fn ($item): bool => 
            in_array($item->assignable->getKey(), $userId)
        );
    }

    /**
     * Filter participants by role key.
     */
    public function filter(callable $callback): Collection
    {
        return $this->participants->filter($callback);
    }
}
