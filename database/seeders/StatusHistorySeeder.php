<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;
use App\Models\StatusHistory;
use App\Models\User;

class StatusHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        if ($users->isEmpty()) {
            $users = User::factory()->count(3)->create();
        }

        $tasks = Task::all();
        if ($tasks->isEmpty()) {
            $tasks = Task::factory()->count(5)->create();
        }

        foreach ($tasks as $task) {
            $timeline = $this->buildTimeline($task->status);
            $now = now();
            foreach ($timeline as $i => $chunk) {
                StatusHistory::create([
                    'task_id' => $task->id,
                    'from_status' => $chunk['from'],
                    'to_status' => $chunk['to'],
                    'changed_by' => $users->random()->id,
                    'note' => fake()->boolean(30) ? fake()->sentence() : null,
                    'created_at' => $now->copy()->subDays(10 - $i),
                    'updated_at' => $now->copy()->subDays(10 - $i),
                ]);
            }
        }
    }

    private function buildTimeline(string $final): array
    {
        $flow = ['To Do', 'In Progress', 'Done'];
        if ($final === 'On Hold') $flow = ['To Do', 'On Hold'];
        if ($final === 'Cancelled') $flow = ['To Do', 'Cancelled'];

        $result = [];
        $prev = null;
        foreach ($flow as $status) {
            if ($prev !== null) {
                $result[] = ['from' => $prev, 'to' => $status];
            } else {
                $result[] = ['from' => null, 'to' => $status];
            }
            $prev = $status;
        }
        return $result;
    }
}

