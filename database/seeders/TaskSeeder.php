<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = Project::all();

        if ($projects->isEmpty()) {
            $projects = Project::factory()->count(3)->create();
        }

        foreach ($projects as $project) {
            Task::factory()->count(fake()->numberBetween(5, 10))->create([
                'project_id' => $project->id,
            ]);
        }
    }
}

