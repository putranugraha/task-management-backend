<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-3 months', '+1 month');
        $end = (clone $start)->modify('+' . fake()->numberBetween(15, 120) . ' days');

        return [
            'name' => ucfirst(fake()->words(3, true)),
            'client_name' => fake()->company(),
            'value_amount' => fake()->randomFloat(2, 1_000_000, 50_000_000),
            'scope' => fake()->paragraph(),
            'objective' => fake()->sentence(12),
            // By default create a user if none exists; seeders can override to use existing users
            'division_owner_id' => User::factory(),
            'start_planned' => $start->format('Y-m-d'),
            'end_planned' => $end->format('Y-m-d'),
            'status' => fake()->randomElement(['Planned', 'In Progress', 'Completed', 'On Hold', 'Cancelled']),
        ];
    }
}
