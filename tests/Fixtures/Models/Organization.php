<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Hdaklue\Porter\Concerns\IsPorterTenant;
use Hdaklue\Porter\Concerns\ReceivesRoleAssignments;
use Hdaklue\Porter\Contracts\PorterTenantContract;
use Hdaklue\Porter\Contracts\RoleableEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model implements PorterTenantContract, RoleableEntity
{
    use HasFactory;
    use IsPorterTenant;
    use ReceivesRoleAssignments;

    protected $table = 'test_organizations';

    protected $fillable = ['name', 'slug'];

    protected $casts = [
        'id' => 'int',
    ];

    /**
     * Factory definition
     */
    protected static function newFactory()
    {
        return \Tests\Fixtures\Factories\OrganizationFactory::new();
    }
}
