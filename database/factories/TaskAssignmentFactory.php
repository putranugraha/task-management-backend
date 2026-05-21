<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskAssignment>
 */
class TaskAssignmentFactory extends Factory
{
    protected $model = TaskAssignment::class;

    public function definition(): array
    {
        $assignedAt = fake()->dateTimeBetween('-2 months', 'now');
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'role_on_task' => fake()->randomElement(['Admin', 'Manager', 'Member']),
            'estimated_effort_hours' => fake()->numberBetween(2, 120),
            'assigned_at' => $assignedAt->format('Y-m-d H:i:s'),
        ];
    }
}

