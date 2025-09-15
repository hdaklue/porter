<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use Hdaklue\Porter\Concerns\IsPorterTenant;
use Hdaklue\Porter\Concerns\ReceivesRoleAssignments;
use Hdaklue\Porter\Contracts\PorterTenantContract;
use Hdaklue\Porter\Contracts\RoleableEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model implements RoleableEntity, PorterTenantContract
{
    use ReceivesRoleAssignments;
    use IsPorterTenant;
    use HasFactory;

    protected $table = 'test_teams';

    protected $fillable = ['name', 'description'];

    protected $casts = [
        'id' => 'int',
    ];

    /**
     * Factory definition
     */
    protected static function newFactory()
    {
        return \Tests\Fixtures\Factories\TeamFactory::new();
    }
}