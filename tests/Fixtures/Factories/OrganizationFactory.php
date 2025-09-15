<?php

declare(strict_types=1);

namespace Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Fixtures\Models\Organization;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'slug' => $this->faker->slug,
        ];
    }
}
