<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectBaseline;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ProjectBaselineSeeder extends Seeder
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
            $count = fake()->numberBetween(1, 3);

            $startWindow = $project->start_planned
                ? Carbon::parse($project->start_planned)->subWeeks(2)
                : Carbon::now()->subMonths(6);
            $endWindow = $project->end_planned
                ? Carbon::parse($project->end_planned)->addWeeks(2)
                : Carbon::now();

            if ($endWindow->lessThan($startWindow)) {
                $endWindow = $startWindow->copy()->addWeeks(2);
            }

            for ($i = 1; $i <= $count; $i++) {
                $takenAt = fake()->dateTimeBetween($startWindow, $endWindow);

                ProjectBaseline::factory()->create([
                    'project_id' => $project->id,
                    'baseline_name' => sprintf('%s Baseline %d', $project->name, $i),
                    'taken_at' => $takenAt->format('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}

