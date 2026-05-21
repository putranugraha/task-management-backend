<?php

namespace Database\Factories;

use App\Models\KpiSnapshot;
use App\Models\Project;
use App\Models\ReportingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\KpiSnapshot>
 */
class KpiSnapshotFactory extends Factory
{
    protected $model = KpiSnapshot::class;

    public function definition(): array
    {
        $tasksTotal = fake()->numberBetween(0, 60);
        $tasksDone = $tasksTotal > 0 ? fake()->numberBetween(0, $tasksTotal) : 0;
        $overdueMax = max(0, $tasksTotal - $tasksDone);
        $overdueCount = $overdueMax > 0 ? fake()->numberBetween(0, $overdueMax) : 0;
        $avgCycle = $tasksDone > 0 ? fake()->randomFloat(2, 1, 45) : 0;

        return [
            'project_id' => Project::factory(),
            'period_id' => ReportingPeriod::factory(),
            'tasks_total' => $tasksTotal,
            'tasks_done' => $tasksDone,
            'overdue_count' => $overdueCount,
            'avg_cycle_time_days' => $avgCycle,
        ];
    }

    public function forProject(Project $project): self
    {
        return $this->state(fn () => [
            'project_id' => $project->id,
        ]);
    }

    public function forReportingPeriod(ReportingPeriod $period): self
    {
        return $this->state(fn () => [
            'project_id' => $period->project_id,
            'period_id' => $period->id,
        ]);
    }
}
