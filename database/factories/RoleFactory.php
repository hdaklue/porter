<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Database\Factories;

use Hdaklue\LaraRbac\Models\Role;
use Hdaklue\LaraRbac\Roles\BaseRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Role>
 */
final class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roles = BaseRole::all();
        $randomRole = fake()->randomElement($roles);
        
        return [
            'name' => $randomRole->getName(),
            'description' => $randomRole->getDescription(),
        ];
    }

    /**
     * Create admin role
     */
    public function admin(): static
    {
        $adminRole = BaseRole::make('admin');
        return $this->state(fn (array $attributes) => [
            'name' => $adminRole->getName(),
            'description' => $adminRole->getDescription(),
        ]);
    }

    /**
     * Create manager role
     */
    public function manager(): static
    {
        $managerRole = BaseRole::make('manager');
        return $this->state(fn (array $attributes) => [
            'name' => $managerRole->getName(),
            'description' => $managerRole->getDescription(),
        ]);
    }

    /**
     * Create editor role
     */
    public function editor(): static
    {
        $editorRole = BaseRole::make('editor');
        return $this->state(fn (array $attributes) => [
            'name' => $editorRole->getName(),
            'description' => $editorRole->getDescription(),
        ]);
    }

    /**
     * Create contributor role
     */
    public function contributor(): static
    {
        $contributorRole = BaseRole::make('contributor');
        return $this->state(fn (array $attributes) => [
            'name' => $contributorRole->getName(),
            'description' => $contributorRole->getDescription(),
        ]);
    }

    /**
     * Create viewer role
     */
    public function viewer(): static
    {
        $viewerRole = BaseRole::make('viewer');
        return $this->state(fn (array $attributes) => [
            'name' => $viewerRole->getName(),
            'description' => $viewerRole->getDescription(),
        ]);
    }

    /**
     * Create guest role
     */
    public function guest(): static
    {
        $guestRole = BaseRole::make('guest');
        return $this->state(fn (array $attributes) => [
            'name' => $guestRole->getName(),
            'description' => $guestRole->getDescription(),
        ]);
    }

    /**
     * Create role with specific name
     */
    public function withName(string $name): static
    {
        try {
            $role = BaseRole::make($name);
            return $this->state(fn (array $attributes) => [
                'name' => $role->getName(),
                'description' => $role->getDescription(),
            ]);
        } catch (\InvalidArgumentException) {
            return $this->state(fn (array $attributes) => [
                'name' => $name,
                'description' => "Custom role: {$name}",
            ]);
        }
    }
}
