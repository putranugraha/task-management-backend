<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectBaseline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ProjectBaseline>
 */
class ProjectBaselineFactory extends Factory
{
    protected $model = ProjectBaseline::class;

    public function definition(): array
    {
        $takenAt = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'project_id' => Project::factory(),
            'baseline_name' => 'Baseline ' . ucfirst(fake()->word()),
            'taken_at' => $takenAt->format('Y-m-d H:i:s'),
            'note' => fake()->boolean(40) ? fake()->sentence() : null,
        ];
    }
}

