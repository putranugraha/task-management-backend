<?php

namespace Database\Factories;

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Milestone>
 */
class MilestoneFactory extends Factory
{
    protected $model = Milestone::class;

    public function definition(): array
    {
        $planned = fake()->dateTimeBetween('now', '+3 months');
        // 50% chance actual date exists and around planned
        $actual = fake()->boolean(50) ? (clone $planned)->modify((string) fake()->numberBetween(-10, 20) . ' days') : null;

        return [
            'project_id' => Project::factory(),
            'name' => 'Milestone ' . ucfirst(fake()->word()),
            'due_planned' => $planned->format('Y-m-d'),
            'due_actual' => $actual ? $actual->format('Y-m-d') : null,
            'status' => fake()->randomElement(['Planned', 'In Progress', 'Completed', 'On Hold', 'Cancelled']),
        ];
    }
}

