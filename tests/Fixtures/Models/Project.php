<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Hdaklue\Porter\Concerns\ReceivesRoleAssignments;
use Hdaklue\Porter\Contracts\RoleableEntity;
use Hdaklue\Porter\Multitenancy\Contracts\PorterRoleableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model implements PorterRoleableContract, RoleableEntity
{
    use HasFactory;
    use ReceivesRoleAssignments;

    protected $table = 'test_projects';

    protected $fillable = ['name', 'description', 'tenant_id'];

    protected $casts = [
        'id' => 'int',
    ];

    /**
     * Get the tenant key that this project belongs to
     */
    public function getPorterTenantKey(): ?string
    {
        return $this->tenant_id;
    }

    /**
     * Factory definition
     */
    protected static function newFactory()
    {
        return \Tests\Fixtures\Factories\ProjectFactory::new();
    }
}
