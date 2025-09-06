<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests\Fixtures;

use Hdaklue\Porter\Concerns\ReceivesRoleAssignments;
use Hdaklue\Porter\Contracts\RoleableEntity;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

class TestProject extends Model implements Arrayable, RoleableEntity
{
    use ReceivesRoleAssignments;

    protected $table = 'test_projects';

    protected $fillable = ['name', 'description'];

    protected $casts = [
        'id' => 'int',
    ];

    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
