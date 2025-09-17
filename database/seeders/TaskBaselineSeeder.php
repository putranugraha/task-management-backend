<?php

namespace Database\Seeders;

use App\Models\ProjectBaseline;
use App\Models\Task;
use App\Models\TaskBaseline;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TaskBaselineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $baselines = ProjectBaseline::with('project')->get();

        if ($baselines->isEmpty()) {
            $this->call(ProjectBaselineSeeder::class);
            $baselines = ProjectBaseline::with('project')->get();
        }

        if (Task::count() === 0) {
            $this->call(TaskSeeder::class);
        }

        foreach ($baselines as $baseline) {
            $tasks = Task::where('project_id', $baseline->project_id)->get();

            if ($tasks->isEmpty()) {
                $tasks = Task::factory()->count(fake()->numberBetween(2, 5))->create([
                    'project_id' => $baseline->project_id,
                ]);
            }

            foreach ($tasks as $task) {
                $startBase = $task->start_planned ? Carbon::parse($task->start_planned) : ($baseline->taken_at ? Carbon::parse($baseline->taken_at)->startOfDay() : Carbon::now()->subDays(fake()->numberBetween(1, 30)));
                $duration = $task->duration_planned ?? fake()->numberBetween(1, 45);
                $endBase = $task->end_planned ? Carbon::parse($task->end_planned) : $startBase->copy()->addDays($duration);

                TaskBaseline::updateOrCreate(
                    [
                        'baseline_id' => $baseline->id,
                        'task_id' => $task->id,
                    ],
                    [
                        'start_planned_base' => $startBase->toDateString(),
                        'end_planned_base' => $endBase->toDateString(),
                        'duration_planned_base' => max(1, $duration),
                        'weight' => number_format(fake()->randomFloat(2, 0.1, 5.0), 2, '.', ''),
                    ]
                );
            }
        }
    }
}

