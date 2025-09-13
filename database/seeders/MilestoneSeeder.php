<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\Milestone;

class MilestoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // For each existing project, create some milestones
        $projects = Project::all();

        if ($projects->isEmpty()) {
            // If no projects, create a few with milestones to ensure data
            $projects = Project::factory()->count(3)->create();
        }

        foreach ($projects as $project) {
            Milestone::factory()->count(3)->create([
                'project_id' => $project->id,
            ]);
        }
    }
}

