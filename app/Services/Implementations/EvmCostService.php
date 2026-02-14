<?php

namespace App\Services\Implementations;

use App\Models\Project;
use App\Models\ProjectBaseline;
use App\Models\Task;
use App\Models\TaskBaseline;
use App\Models\TaskCostEntry;
use App\Services\Contracts\EvmCostServiceInterface;
use Carbon\Carbon;

class EvmCostService implements EvmCostServiceInterface
{
    public function computeForProjectDate(int $projectId, $date, ?int $baselineId = null): array
    {
        $asOf = $date instanceof \DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date);
        $asOfDate = $asOf->toDateString();

        $project = Project::findOrFail($projectId);

        // Validate that baseline belongs to the project (if provided)
        if ($baselineId) {
            $baselineOk = ProjectBaseline::where('project_id', $projectId)
                ->where('id', $baselineId)
                ->exists();
            if (! $baselineOk) {
                // Treat as invalid request - the caller validated "exists", but it can belong to another project.
                abort(422, 'Invalid baseline_id for this project.');
            }
        }

        $tasks = Task::query()
            ->where('project_id', $projectId)
            ->get([
                'id',
                'project_id',
                'start_planned',
                'duration_planned',
                'percent_complete',
                'budget_cost',
            ]);

        $taskIds = $tasks->pluck('id')->all();

        // Baseline schedule map: task_id -> [start_planned_base, duration_planned_base]
        $taskBaselineMap = [];
        if ($baselineId && ! empty($taskIds)) {
            $taskBaselineMap = TaskBaseline::query()
                ->where('baseline_id', $baselineId)
                ->whereIn('task_id', $taskIds)
                ->get(['task_id', 'start_planned_base', 'duration_planned_base'])
                ->keyBy('task_id')
                ->toArray();
        }

        // Actual cost sums up to date, grouped by task (non-N+1)
        $acSums = [];
        if (! empty($taskIds)) {
            $acSums = TaskCostEntry::query()
                ->whereIn('task_id', $taskIds)
                ->whereDate('incurred_on', '<=', $asOfDate)
                ->selectRaw('task_id, COALESCE(SUM(amount),0) as sum_amount')
                ->groupBy('task_id')
                ->pluck('sum_amount', 'task_id')
                ->toArray();
        }

        $sumBudgetCost = 0.0;
        $totalPV = 0.0;
        $totalEV = 0.0;
        $totalAC = 0.0;

        foreach ($tasks as $task) {
            $taskId = $task->id;

            $budgetCost = (float) ($task->budget_cost ?? 0);
            if ($budgetCost < 0) $budgetCost = 0;
            $sumBudgetCost += $budgetCost;

            $baseRow = $taskBaselineMap[$taskId] ?? null;
            $startPlanned = $baseRow['start_planned_base'] ?? $task->start_planned;
            $durationPlanned = (int) ($baseRow['duration_planned_base'] ?? $task->duration_planned ?? 0);

            $fraction = $this->plannedFractionInclusiveDays($asOf, $startPlanned, $durationPlanned);

            $pct = (int) ($task->percent_complete ?? 0);
            if ($pct < 0) $pct = 0;
            if ($pct > 100) $pct = 100;

            $pv = $budgetCost * $fraction;
            $ev = $budgetCost * ($pct / 100);
            $ac = (float) ($acSums[$taskId] ?? 0.0);

            $totalPV += $pv;
            $totalEV += $ev;
            $totalAC += $ac;
        }

        $sv = $totalEV - $totalPV;
        $spi = ($totalPV > 0.0) ? ($totalEV / $totalPV) : null;
        $cv = $totalEV - $totalAC;
        $cpi = ($totalAC > 0.0) ? ($totalEV / $totalAC) : null;

        // BAC rule: prefer project value_amount when set; otherwise sum task budgets
        $projectValue = (float) ($project->value_amount ?? 0);
        $bac = $projectValue > 0 ? $projectValue : $sumBudgetCost;
        $bacSource = $projectValue > 0 ? 'projects.value_amount' : 'sum(tasks.budget_cost)';

        $eac = null;
        $etc = null;
        if ($cpi !== null && $cpi > 0.0) {
            $eac = $bac / $cpi;
            $etc = $eac - $totalAC;
        }

        return [
            'project_id' => $projectId,
            'date' => $asOfDate,
            'baseline_id' => $baselineId,
            'unit' => 'IDR',
            'bac' => round((float) $bac, 2),
            'pv' => round((float) $totalPV, 2),
            'ev' => round((float) $totalEV, 2),
            'ac' => round((float) $totalAC, 2),
            'sv' => round((float) $sv, 2),
            'spi' => $spi !== null ? round((float) $spi, 4) : null,
            'cv' => round((float) $cv, 2),
            'cpi' => $cpi !== null ? round((float) $cpi, 4) : null,
            'eac' => $eac !== null ? round((float) $eac, 2) : null,
            'etc' => $etc !== null ? round((float) $etc, 2) : null,
            'meta' => [
                'bac_source' => $bacSource,
                'pv_ev_source' => 'tasks.budget_cost',
                'ac_source' => 'task_cost_entries',
                'task_count' => count($taskIds),
                'baseline_used' => $baselineId !== null,
                'planned_fraction_inclusive_days' => true,
            ],
        ];
    }

    /**
     * Inclusive-days planned fraction to avoid PV being 0 on the start day.
     *
     * - start null or duration <= 0 => 0
     * - asOf < start => 0
     * - else elapsedDays = diffInDays(start, asOf) + 1
     * - fraction = clamp(elapsedDays / duration, 0..1)
     *
     * @param Carbon $asOf
     * @param mixed $startPlanned date|string|null
     * @param int $durationDays
     */
    protected function plannedFractionInclusiveDays(Carbon $asOf, $startPlanned, int $durationDays): float
    {
        if (! $startPlanned || $durationDays <= 0) {
            return 0.0;
        }

        $start = Carbon::parse($startPlanned);

        if ($asOf->lt($start)) {
            return 0.0;
        }

        $elapsed = $start->diffInDays($asOf) + 1; // inclusive
        if ($elapsed < 0) $elapsed = 0;
        if ($elapsed > $durationDays) $elapsed = $durationDays;

        return $durationDays > 0 ? ($elapsed / $durationDays) : 0.0;
    }
}

