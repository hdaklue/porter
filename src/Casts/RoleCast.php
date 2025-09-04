<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Casts;

use Hdaklue\Porter\Contracts\RoleContract;
use Hdaklue\Porter\RoleFactory;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Cast for automatically converting encrypted role_key to Role instances.
 *
 * Usage in models:
 * protected $casts = [
 *     'role_key' => RoleCast::class,
 * ];
 */
final class RoleCast implements CastsAttributes
{
    /**
     * Cast the given value to a Role instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): RoleContract
    {
        if (empty($value)) {
            throw new InvalidArgumentException('Role key cannot be empty');
        }

        $role = RoleFactory::tryMake($value);

        if (! $role) {
            throw new InvalidArgumentException("Invalid role key: {$value}");
        }

        return $role;
    }

    /**
     * Prepare the given value for storage (convert Role to encrypted key).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value === null) {
            throw new InvalidArgumentException('Role key cannot be null');
        }

        if (is_string($value)) {
            // If it's already a string (encrypted key), return as-is
            return $value;
        }

        if ($value instanceof RoleContract) {
            // If it's a Role instance, get its encrypted key
            return $value::getDbKey();
        }

        throw new InvalidArgumentException(
            'Role cast expects a RoleContract instance or string (role name/key). Got: '.gettype($value)
        );
    }
}
