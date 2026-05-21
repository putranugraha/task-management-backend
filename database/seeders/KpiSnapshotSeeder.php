<?php

namespace Database\Seeders;

use App\Models\KpiSnapshot;
use App\Models\Project;
use App\Models\ReportingPeriod;
use App\Models\Task;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class KpiSnapshotSeeder extends Seeder
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
            if (!Task::where('project_id', $project->id)->exists()) {
                Task::factory()->count(fake()->numberBetween(5, 10))->create([
                    'project_id' => $project->id,
                ]);
            }

            if (!ReportingPeriod::where('project_id', $project->id)->exists()) {
                ReportingPeriod::factory()
                    ->count(fake()->numberBetween(3, 6))
                    ->create([
                        'project_id' => $project->id,
                    ]);
            }
        }

        $tasksByProject = Task::all()->groupBy('project_id');
        $periodsByProject = ReportingPeriod::all()->groupBy('project_id');

        foreach ($periodsByProject as $projectId => $periods) {
            $tasks = $tasksByProject->get($projectId, collect());

            foreach ($periods as $period) {
                $periodDate = Carbon::parse($period->period_date);
                $tasksTotal = $tasks->count();
                $tasksDone = $tasks->where('status', 'Done')->count();

                $overdueCount = $tasks
                    ->filter(function ($task) use ($periodDate) {
                        if (empty($task->end_planned)) {
                            return false;
                        }

                        $plannedEnd = Carbon::parse($task->end_planned);

                        return $plannedEnd->lessThan($periodDate) && $task->status !== 'Done';
                    })
                    ->count();

                $cycleTimes = $tasks
                    ->filter(fn ($task) => $task->start_actual && $task->end_actual)
                    ->map(function ($task) {
                        $start = Carbon::parse($task->start_actual);
                        $end = Carbon::parse($task->end_actual);

                        return max(0, $start->diffInDays($end));
                    });

                $avgCycle = $cycleTimes->isNotEmpty() ? round($cycleTimes->avg(), 2) : 0;

                KpiSnapshot::updateOrCreate(
                    [
                        'project_id' => $projectId,
                        'period_id' => $period->id,
                    ],
                    [
                        'tasks_total' => $tasksTotal,
                        'tasks_done' => $tasksDone,
                        'overdue_count' => $overdueCount,
                        'avg_cycle_time_days' => $avgCycle,
                    ]
                );
            }
        }
    }
}
