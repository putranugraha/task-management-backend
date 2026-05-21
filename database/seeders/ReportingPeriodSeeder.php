<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ReportingPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReportingPeriodSeeder extends Seeder
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
            $count = fake()->numberBetween(3, 6);
            $start = $project->start_planned ? Carbon::parse($project->start_planned)->subWeeks(1) : Carbon::now()->subMonths(6);
            $end = $project->end_planned ? Carbon::parse($project->end_planned)->addWeeks(1) : Carbon::now()->addMonths(3);

            if ($end->lessThan($start)) {
                $end = $start->copy()->addMonths(1);
            }

            $dates = collect(range(1, $count))
                ->map(fn () => fake()->dateTimeBetween($start, $end)->format('Y-m-d'))
                ->unique()
                ->values();

            foreach ($dates as $index => $date) {
                ReportingPeriod::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'period_date' => $date,
                    ],
                    [
                        'note' => fake()->boolean(30) ? 'Reporting period #' . ($index + 1) . ' for ' . $project->name : null,
                    ]
                );
            }
        }
    }
}

