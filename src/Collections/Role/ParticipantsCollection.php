<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Collections\Role;

use Hdaklue\LaraRbac\Contracts\Role\AssignableEntity;
use Hdaklue\LaraRbac\Enums\Role\RoleEnum;
use Illuminate\Support\Collection;

final class ParticipantsCollection extends Collection
{
    /**
     * Convert the collection to a collection of basic participant data.
     *
     * @return self<array{participant_id: int, participant_name: string, role_id: int, role_name: string, role_label: string, role_description: string}>
     */
    public function asBasicArray(): self
    {
        return $this->map(fn ($item) => [
            'participant_id' => $item->model->getKey(),
            'participant_name' => $item->model->getAttribute('name'),
            'role_id' => $item->role->getKey(),
            'role_name' => $item->role->getAttribute('name'),
            'role_label' => RoleEnum::from($item->role->getAttribute('name'))->getLabel(),
            'role_description' => RoleEnum::from($item->role->getAttribute('name'))->getDescription(),
        ]);
    }

    public function getParticipantIds(): Collection
    {
        return collect($this->pluck('model.id'));
    }

    public function getParticipantsAsSelectArray(): array
    {
        return $this->pluck('model')->mapWithKeys(fn ($model) => [$model->getKey() => $model->getAttribute('name')])->toArray();
    }

    public function exceptAssignable(AssignableEntity|string|array $userId): self
    {
        if ($userId instanceof AssignableEntity) {
            $userId = [$userId->getKey()];
        }

        if (is_string($userId)) {
            $userId = [$userId];
        }

        return $this->reject(fn ($item): bool => in_array($item->model->getKey(), $userId));
    }
}
