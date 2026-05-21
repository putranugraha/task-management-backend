<?php

namespace Database\Factories;

use App\Models\ProjectBaseline;
use App\Models\Task;
use App\Models\TaskBaseline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TaskBaseline>
 */
class TaskBaselineFactory extends Factory
{
    protected $model = TaskBaseline::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-3 months', '+3 months');
        $duration = fake()->numberBetween(1, 60);
        $end = (clone $start)->modify('+' . $duration . ' days');

        return [
            'baseline_id' => ProjectBaseline::factory(),
            'task_id' => Task::factory(),
            'start_planned_base' => $start->format('Y-m-d'),
            'end_planned_base' => $end->format('Y-m-d'),
            'duration_planned_base' => $duration,
            'weight' => number_format(fake()->randomFloat(2, 0.10, 5.00), 2, '.', ''),
        ];
    }
}

