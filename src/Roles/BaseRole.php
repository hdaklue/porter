<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Roles;

use Hdaklue\LaraRbac\Concerns\Role\HasRoleHierarchy;
use Hdaklue\LaraRbac\Contracts\Role\RoleInterface;
use Illuminate\Support\Collection;

abstract class BaseRole implements RoleInterface
{
    use HasRoleHierarchy;

    /**
     * Get the role name.
     */
    abstract public function getName(): string;

    /**
     * Get the hierarchical level of this role.
     */
    abstract public function getLevel(): int;

    /**
     * Get human-readable label for this role.
     */
    public function getLabel(): string
    {
        return __(sprintf('lararbac::roles.%s.label', $this->getName()));
    }

    /**
     * Get description of role capabilities.
     */
    public function getDescription(): string
    {
        return __(sprintf('lararbac::roles.%s.description', $this->getName()));
    }

    /**
     * Create role instance from name.
     */
    public static function make(string $name): static
    {
        $roles = static::all();
        
        foreach ($roles as $role) {
            if ($role->getName() === $name) {
                return $role;
            }
        }

        throw new \InvalidArgumentException("Role '{$name}' does not exist.");
    }

    /**
     * Try to create role instance from name, returns null if not found.
     */
    public static function tryMake(string $name): ?static
    {
        try {
            return static::make($name);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Get roles lower than or equal to given role.
     */
    public static function whereLowerThanOrEqual(RoleInterface $role): Collection
    {
        return collect(static::all())
            ->filter(fn ($r) => $r->isLowerThanOrEqual($role))
            ->mapWithKeys(fn ($item) => [$item->getName() => $item->getLabel()]);
    }

    /**
     * Get roles lower than or equal to given role as array with value/label structure.
     */
    public static function getRolesLowerThanOrEqual(RoleInterface $role): Collection
    {
        return collect(static::all())
            ->reject(fn ($r) => $r->getLevel() > $role->getLevel())
            ->map(fn ($item) => ['value' => $item->getName(), 'label' => $item->getLabel()]);
    }

    /**
     * Get all available role instances.
     * This discovers all role classes automatically.
     */
    public static function all(): array
    {
        $roleClasses = config('lararbac.roles', [
            \Hdaklue\LaraRbac\Roles\Admin::class,
            \Hdaklue\LaraRbac\Roles\Manager::class,
            \Hdaklue\LaraRbac\Roles\Editor::class,
            \Hdaklue\LaraRbac\Roles\Contributor::class,
            \Hdaklue\LaraRbac\Roles\Viewer::class,
            \Hdaklue\LaraRbac\Roles\Guest::class,
        ]);

        return array_map(fn($class) => new $class(), $roleClasses);
    }
}