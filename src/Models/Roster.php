<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Models;

use Eloquent;
use Exception;
use Hdaklue\Porter\Contracts\RoleContract;
use Hdaklue\Porter\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Porter role assignment model - tracks which entities have which roles on which targets.
 *
 * @property int $id
 * @property string $assignable_type
 * @property string $assignable_id
 * @property string $roleable_type
 * @property string $roleable_id
 * @property string $role_key
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Model|\Eloquent $assignable The entity that has the role (e.g., User)
 * @property-read RoleContract $role The role instance
 * @property-read Model|\Eloquent $roleable The entity the role is assigned to (e.g., Project)
 * @property-read string $description Human-readable description of the assignment
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster whereAssignableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster whereAssignableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster whereRoleKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster whereRoleableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster whereRoleableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster forAssignable(string $type, mixed $id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster forRoleable(string $type, mixed $id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster withRole(\Hdaklue\Porter\Contracts\RoleContract $role)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Roster withRoleName(string $roleName)
 *
 * @mixin Eloquent
 */
final class Roster extends Model
{
    public $timestamps = true;

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

    protected function role(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => RoleFactory::tryMake($this->getRoleDBKey()),
        );
    }

    public function getTable(): string
    {
        return config('porter.table_names.roster', 'roster');
    }

    public function getRoleDBKey(): string
    {
        $roleKeyColumn = config('porter.column_names.role_key', 'role_key');
        
        if (isset($this->attributes[$roleKeyColumn])) {
            return $this->getAttribute($roleKeyColumn);
        }
        
        throw new Exception('Unable to resolve role_key');
    }

    /**
     * Scope to find role assignments for a specific assignable entity.
     */
    #[Scope]
    public function scopeForAssignable(Builder $query, string $type, mixed $id): Builder
    {
        return $query->where('assignable_type', $type)->where('assignable_id', $id);
    }

    /**
     * Scope to find role assignments on a specific roleable entity.
     */
    #[Scope]
    public function scopeForRoleable(Builder $query, string $type, mixed $id): Builder
    {
        return $query->where('roleable_type', $type)->where('roleable_id', $id);
    }

    /**
     * Scope to find assignments with a specific role.
     */
    #[Scope]
    public function scopeWithRole(Builder $query, RoleContract $role): Builder
    {
        return $query->where('role_key', $role::getDbKey());
    }

    /**
     * Scope to find assignments with role name.
     */
    #[Scope]
    public function scopeWithRoleName(Builder $query, string $roleName): Builder
    {
        $role = RoleFactory::make($roleName);

        return $query->where('role_key', $role::getDbKey());
    }

    /**
     * Get a human-readable description of this assignment.
     */
    public function getDescriptionAttribute(): string
    {
        $assignableName = class_basename($this->assignable_type);
        $roleableName = class_basename($this->roleable_type);
        $role = $this->role;

        return "{$assignableName} #{$this->assignable_id} has role '{$role->getName()}' on {$roleableName} #{$this->roleable_id}";
    }
}
