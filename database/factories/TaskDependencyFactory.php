<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskDependency>
 */
class TaskDependencyFactory extends Factory
{
    protected $model = TaskDependency::class;

    public function definition(): array
    {
        // By default, associate two random tasks (can be overridden in seeders)
        $type = fake()->randomElement(['FS', 'SS', 'FF', 'SF']);

        return [
            'task_id' => Task::factory(),
            'depends_on_task_id' => Task::factory(),
            'type' => $type,
            'lag_days' => fake()->numberBetween(-5, 10),
        ];
    }
}

