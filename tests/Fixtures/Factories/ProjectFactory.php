<?php

declare(strict_types=1);

namespace Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Fixtures\Models\Project;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company.' Project',
            'description' => $this->faker->sentence,
            'tenant_id' => null,
        ];
    }

    /**
     * Set the project's tenant
     */
    public function withTenant(string $tenantId): self
    {
        return $this->state(function (array $attributes) use ($tenantId) {
            return [
                'tenant_id' => $tenantId,
            ];
        });
    }
}
