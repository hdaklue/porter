<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property-read Role $role
 * @property-read Model|\Eloquent $roleable
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleableHasRole newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleableHasRole newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleableHasRole query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleableHasRole whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleableHasRole whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleableHasRole whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleableHasRole whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleableHasRole whereRoleableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleableHasRole whereRoleableType($value)
 *
 * @mixin Eloquent
 */
final class RoleableHasRole extends MorphPivot
{
    public $timestamps = false;

    protected $fillable = [
        'model_type',
        'model_id',
        'roleable_type',
        'roleable_id',
        'role_id',
    ];

    /**
     * Get the database connection for the model.
     */
    public function getConnectionName()
    {
        return config('lararbac.database_connection') ?: config('database.default');
    }

    /**
     * The model that has the role (e.g., User).
     */
    public function model(): MorphTo
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

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(config('lararbac.models.role', Role::class));
    }

    public function getTable(): string
    {
        return config('lararbac.table_names.roleable_has_roles', 'roleable_has_roles');
    }

    public function getRole(): Role
    {
        return $this->role()->firstOrFail();
    }

    public function getModel()
    {
        return $this->model;
    }
}