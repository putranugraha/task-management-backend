<?php

namespace Database\Factories;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Division>
 */
class DivisionFactory extends Factory
{
    protected $model = Division::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('DIV-###')),
            'name' => ucwords(fake()->unique()->words(2, true)),
            'description' => fake()->boolean(60) ? fake()->paragraph() : null,
        ];
    }
}

