<?php

namespace Hdaklue\LaraRbac\Events\Role;

use Hdaklue\LaraRbac\Contracts\Role\AssignableEntity;
use Hdaklue\LaraRbac\Contracts\Role\RoleableEntity;
use Hdaklue\LaraRbac\Enums\Role\RoleEnum;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntityRoleRemoved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AssignableEntity $user,
        public readonly RoleableEntity $entity,
        public readonly string|RoleEnum $role
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
