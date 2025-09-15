<?php

declare(strict_types=1);

namespace Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Fixtures\Models\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'current_tenant_id' => null,
        ];
    }

    /**
     * Set the user's tenant
     */
    public function withTenant(string $tenantId): self
    {
        return $this->state(function (array $attributes) use ($tenantId) {
            return [
                'current_tenant_id' => $tenantId,
            ];
        });
    }
}