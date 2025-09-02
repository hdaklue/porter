<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Models;

use Eloquent;
use Exception;
use Hdaklue\Porter\Contracts\RoleInterface;
use Hdaklue\Porter\RoleFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $model_type
 * @property string $model_id
 * @property string $roleable_type
 * @property string $roleable_id
 * @property string $role_id
 * @property-read Model|\Eloquent $model
 * @property-read RoleInterface $role
 * @property-read Model|\Eloquent $roleable
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster whereRoleableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster whereRoleableType($value)
 *
 * @mixin Eloquent
 */
final class Roster extends MorphPivot
{
    public $timestamps = false;

    protected $fillable = [
        'assignable_type',
        'assignable_id',
        'roleable_type',
        'roleable_id',
        'role_key',
    ];

    /**
     * Get the database connection for the model.
     */
    public function getConnectionName()
    {
        return config('porter.database_connection') ?: config('database.default');
    }

    /**
     * The model that has the role (e.g., User).
     */
    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The entity this role is assigned to (e.g., Project, Team).
     */
    public function roleable(): MorphTo
    {
        return $this->morphTo();
    }

    public function role(): RoleInterface
    {
        return RoleFactory::tryMake($this->getRoleDBKey());
    }

    public function getTable(): string
    {
        return config('porter.table_names.roaster', 'roaster');
    }

    public function getRoleDBKey(): string
    {
        if ($this->hasAttribute(config('porter.column_names.role_key'))) {
            return $this->getAttribute(config('porter.column_names.role_key'));
        }
        throw new Exception('Unable to resolve role_key');
    }

    public function getModel()
    {
        return $this->model;
    }
}
