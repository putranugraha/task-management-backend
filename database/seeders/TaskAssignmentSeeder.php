<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tasks = Task::all();
        if ($tasks->isEmpty()) {
            // Ensure there are tasks to assign
            $tasks = Task::factory()->count(5)->create();
        }

        $users = User::with('roles')->get();
        if ($users->isEmpty()) {
            $users = User::factory()->count(3)->create();
        }

        foreach ($tasks as $task) {
            // Pick 1-3 users to assign to this task
            $pickCount = min($users->count(), fake()->numberBetween(1, 3));
            $pickedUsers = $users->shuffle()->take($pickCount);

            foreach ($pickedUsers as $user) {
                $roleNames = $user->getRoleNames();
                $roleName = $roleNames->isNotEmpty() ? $roleNames->random() : 'Member';

                TaskAssignment::updateOrCreate(
                    [
                        'task_id' => $task->id,
                        'user_id' => $user->id,
                        'role_on_task' => $roleName,
                    ],
                    [
                        'estimated_effort_hours' => fake()->numberBetween(4, 80),
                        'assigned_at' => now()->subDays(fake()->numberBetween(0, 60))->startOfHour(),
                    ]
                );
            }
        }
    }
}

