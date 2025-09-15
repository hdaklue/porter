<?php

declare(strict_types=1);

namespace Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Fixtures\Models\Team;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company . ' Team',
            'description' => $this->faker->sentence,
        ];
    }
}