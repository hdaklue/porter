<?php

namespace Hdaklue\LaraRbac\Events\Role;

use Hdaklue\LaraRbac\Contracts\Role\AssignableEntity;
use Hdaklue\LaraRbac\Contracts\Role\RoleableEntity;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntityAllRolesRemoved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly AssignableEntity $user,
        public readonly RoleableEntity $entity
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
