<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests\Fixtures;

use Hdaklue\Porter\Concerns\ReceivesRoleAssignments;
use Hdaklue\Porter\Contracts\RoleableEntity;
use Hdaklue\Porter\Multitenancy\Contracts\PorterRoleableContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

class TestProject extends Model implements Arrayable, PorterRoleableContract, RoleableEntity
{
    use ReceivesRoleAssignments;

    protected $table = 'test_projects';

    protected $fillable = ['name', 'description', 'tenant_id'];

    protected $casts = [
        'id' => 'int',
    ];

    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'tenant_id' => $this->tenant_id,
        ];
    }

    /**
     * Get the tenant key that this project belongs to
     */
    public function getPorterTenantKey(): ?string
    {
        return $this->tenant_id;
    }
}
