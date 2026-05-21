<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Database\Seeder;

class TaskDependencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = Project::all();

        if ($projects->isEmpty()) {
            // Ensure we have some projects and tasks to link
            $projects = Project::factory()->count(2)->create();
            foreach ($projects as $project) {
                Task::factory()->count(fake()->numberBetween(4, 8))->create([
                    'project_id' => $project->id,
                ]);
            }
            $projects = Project::all();
        }

        foreach ($projects as $project) {
            $tasks = Task::where('project_id', $project->id)->orderBy('id')->get()->values();
            if ($tasks->count() < 2) {
                continue;
            }

            // For each task (except the first few), create 0-2 dependencies to earlier tasks
            foreach ($tasks as $i => $task) {
                if ($i === 0) {
                    continue;
                }

                $depsCount = fake()->numberBetween(0, 2);
                $choices = $tasks->slice(0, $i)->pluck('id')->all();
                if (empty($choices)) {
                    continue;
                }

                $picked = collect($choices)->shuffle()->take($depsCount);

                foreach ($picked as $depTaskId) {
                    // Avoid duplicates by unique constraint; use updateOrCreate
                    TaskDependency::updateOrCreate(
                        [
                            'task_id' => $task->id,
                            'depends_on_task_id' => $depTaskId,
                            'type' => fake()->randomElement(['FS', 'SS', 'FF', 'SF']),
                        ],
                        [
                            'lag_days' => fake()->numberBetween(-5, 10),
                        ]
                    );
                }
            }
        }
    }
}
