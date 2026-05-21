<?php

namespace Database\Factories;

use App\Models\StatusHistory;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\StatusHistory>
 */
class StatusHistoryFactory extends Factory
{
    protected $model = StatusHistory::class;

    public function definition(): array
    {
        $statuses = ['To Do', 'In Progress', 'Done', 'On Hold', 'Cancelled'];
        $from = fake()->randomElement($statuses);
        $to = fake()->randomElement(array_values(array_diff($statuses, [$from])));

        return [
            'task_id' => Task::factory(),
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => User::factory(),
            'note' => fake()->boolean(40) ? fake()->sentence() : null,
        ];
    }
}

