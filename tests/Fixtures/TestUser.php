<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests\Fixtures;

use Hdaklue\Porter\Concerns\CanBeAssignedToEntity;
use Hdaklue\Porter\Contracts\AssignableEntity;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable implements AssignableEntity
{
    use CanBeAssignedToEntity;

    protected $table = 'test_users';

    protected $fillable = ['name', 'email'];

    protected $casts = [
        'id' => 'int',
    ];

    public function notify($instance)
    {
        // Test implementation - no action needed
        return true;
    }
}
