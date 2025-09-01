<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Models;

use Hdaklue\LaraRbac\Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property array $constraints
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, RoleableHasRole> $assignments
 * @property-read int|null $assignments_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class Role extends Model
{
    use HasFactory,
        HasUlids;

    protected static $factory = RoleFactory::class;

    public $fillable = [
        'name',
        'description',
        'constraints',
    ];

    /**
     * Cast attributes to native types.
     */
    protected $casts = [
        'constraints' => 'array',
    ];

    /**
     * Get the database connection for the model.
     */
    public function getConnectionName()
    {
        return config('lararbac.database_connection') ?: config('database.default');
    }

    /**
     * Get the table name from configuration.
     */
    public function getTable(): string
    {
        return config('lararbac.table_names.roles', 'roles');
    }

    /**
     * All role assignments for this role.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(config('lararbac.models.roleable_has_role', RoleableHasRole::class));
    }

    /**
     * Scope to find role by name.
     */
    public function scopeByName($query, string $name)
    {
        return $query->where('name', $name);
    }
}