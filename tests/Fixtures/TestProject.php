<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests\Fixtures;

use Hdaklue\Porter\Contracts\RoleableEntity;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TestProject extends Model implements RoleableEntity, Arrayable
{
    protected $table = 'test_projects';
    protected $fillable = ['name', 'description'];

    protected $casts = [
        'id' => 'int',
    ];

    public function roleAssignments(): MorphMany
    {
        return $this->morphMany(config('porter.models.roster'), 'roleable');
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}