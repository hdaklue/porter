<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Events;

use Hdaklue\Porter\Contracts\AssignableEntity;
use Hdaklue\Porter\Contracts\RoleableEntity;
use Hdaklue\Porter\Contracts\RoleInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleAssigned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AssignableEntity $assignable,
        public readonly RoleableEntity $roleable,
        public readonly RoleInterface $role
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('porter-roles'),
        ];
    }
}