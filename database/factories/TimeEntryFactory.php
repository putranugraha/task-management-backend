<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    public function definition(): array
    {
        // Random date within last 30 days
        $date = fake()->dateTimeBetween('-30 days', 'now');

        // Hours between 0.5 and 8.0 in 0.5 increments
        $hours = fake()->randomElement([0.5, 1, 1.5, 2, 3, 4, 5, 6, 7, 8]);

        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'date' => $date->format('Y-m-d'),
            'hours' => number_format($hours, 2, '.', ''),
            'note' => fake()->boolean(40) ? fake()->sentence() : null,
        ];
    }
}

