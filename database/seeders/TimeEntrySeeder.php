<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\TaskAssignment;
use App\Models\User;

class TimeEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tasks = Task::all();
        if ($tasks->isEmpty()) {
            $tasks = Task::factory()->count(5)->create();
        }

        $users = User::all();
        if ($users->isEmpty()) {
            $users = User::factory()->count(3)->create();
        }

        foreach ($tasks as $task) {
            // Prefer assigned users for this task; fallback to global users
            $assignedUserIds = TaskAssignment::where('task_id', $task->id)->pluck('user_id');
            $candidateUsers = $assignedUserIds->isNotEmpty() ? $users->whereIn('id', $assignedUserIds->all()) : $users;

            // create 3-8 entries spread across recent dates
            $entriesCount = fake()->numberBetween(3, 8);
            $dates = collect(range(0, 20))->random($entriesCount)->map(function ($offset) {
                return now()->subDays($offset)->toDateString();
            })->unique()->values();

            foreach ($dates as $date) {
                $user = $candidateUsers->random();
                $hours = fake()->randomElement([0.5, 1, 1.5, 2, 3, 4, 5, 6, 7, 8]);

                TimeEntry::updateOrCreate(
                    [
                        'task_id' => $task->id,
                        'user_id' => $user->id,
                        'date' => $date,
                    ],
                    [
                        'hours' => $hours,
                        'note' => fake()->boolean(30) ? fake()->sentence() : null,
                    ]
                );
            }
        }
    }
}

