<?php

namespace App\Services\Implementations;

use App\Models\ProjectBaseline;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskBaseline;
use App\Models\TimeEntry;
use App\Services\Contracts\EvmServiceInterface;
use App\Services\Contracts\ProjectBaselineServiceInterface;
use Carbon\Carbon;

class EvmService implements EvmServiceInterface
{
    /**
     * Default working hours per day used for duration→effort fallback
     */
    private const HOURS_PER_DAY = 8; // adjust if needed

    protected ProjectBaselineServiceInterface $baselineService;

    public function __construct(ProjectBaselineServiceInterface $baselineService)
    {
        $this->baselineService = $baselineService;
    }

    public function computeForProjectDate(int $projectId, $date, ?int $baselineId = null): array
    {
        $asOf = $date instanceof \DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date);
        $asOfDate = $asOf->toDateString();

        // Determine baseline to use (optional)
        $baseline = null;
        if ($baselineId) {
            // Ensure the provided baseline belongs to the project
            $baseline = ProjectBaseline::where('project_id', $projectId)
                ->where('id', $baselineId)
                ->first();
        }
        if (!$baseline) {
            $baseline = $this->baselineService->getLatestBaselineByProject($projectId);
        }
        $baselineId = $baseline->id ?? null;

        // Fetch tasks for project (minimal columns)
        $tasks = Task::query()
            ->where('project_id', $projectId)
            ->get([
                'id', 'project_id', 'start_planned', 'end_planned', 'duration_planned', 'percent_complete',
            ]);

        $taskIds = $tasks->pluck('id')->all();

        // Sum planned effort from assignments per task
        $assignSums = [];
        if (!empty($taskIds)) {
            $assignSums = TaskAssignment::query()
                ->whereIn('task_id', $taskIds)
                ->selectRaw('task_id, COALESCE(SUM(estimated_effort_hours),0) as sum_hours')
                ->groupBy('task_id')
                ->pluck('sum_hours', 'task_id')
                ->toArray();
        }

        // Pull baselines for tasks if baseline chosen
        $taskBaselineMap = [];
        if ($baselineId && !empty($taskIds)) {
            $taskBaselineMap = TaskBaseline::query()
                ->where('baseline_id', $baselineId)
                ->whereIn('task_id', $taskIds)
                ->get(['task_id', 'start_planned_base', 'end_planned_base', 'duration_planned_base', 'planned_effort_hours'])
                ->keyBy('task_id')
                ->toArray();
        }

        // Sum actual hours (AC) up to the date, grouped by task
        $acSums = [];
        if (!empty($taskIds)) {
            $acSums = TimeEntry::query()
                ->whereIn('task_id', $taskIds)
                ->whereDate('date', '<=', $asOfDate)
                ->selectRaw('task_id, COALESCE(SUM(hours),0) as sum_hours')
                ->groupBy('task_id')
                ->pluck('sum_hours', 'task_id')
                ->toArray();
        }

        // Compute aggregates
        $totalPV = 0.0;
        $totalEV = 0.0;
        $totalAC = 0.0;

        foreach ($tasks as $task) {
            $taskId = $task->id;

            // Planned effort prioritization:
            // 1) sum(assignment estimated_effort_hours)
            // 2) TaskBaseline.planned_effort_hours
            // 3) duration_planned_base × 8 (or task duration × 8)
            $plannedEffort = null;
            $assignEffort = (float) ($assignSums[$taskId] ?? 0);
            if ($assignEffort > 0) {
                $plannedEffort = $assignEffort;
            }

            // Baseline/task planning dates and duration for PV fraction
            $baseRow = $taskBaselineMap[$taskId] ?? null;
            $startPlanned = $baseRow['start_planned_base'] ?? $task->start_planned;
            $durationPlanned = (int) ($baseRow['duration_planned_base'] ?? $task->duration_planned ?? 0);
            if ($plannedEffort === null) {
                $tbEffort = isset($baseRow['planned_effort_hours']) ? (float) $baseRow['planned_effort_hours'] : 0.0;
                if ($tbEffort > 0) {
                    $plannedEffort = $tbEffort;
                }
            }

            // Fallback planned effort if no assignments
            if (($plannedEffort === null || $plannedEffort <= 0) && $durationPlanned > 0) {
                $plannedEffort = $durationPlanned * self::HOURS_PER_DAY;
            }

            // PV fraction over time
            $fraction = 0.0;
            if ($startPlanned && $durationPlanned > 0) {
                $start = Carbon::parse($startPlanned);
                if ($asOf->lt($start)) {
                    $fraction = 0.0;
                } else {
                    // inclusive days elapsed (min with duration)
                    $elapsed = $start->diffInDays($asOf) + 1; // inclusive
                    if ($elapsed < 0) $elapsed = 0;
                    if ($elapsed > $durationPlanned) $elapsed = $durationPlanned;
                    $fraction = $durationPlanned > 0 ? ($elapsed / $durationPlanned) : 0.0;
                }
            }

            $plannedEffort = (float) ($plannedEffort ?? 0.0);
            $pv = $plannedEffort * $fraction;
            $pct = (int) ($task->percent_complete ?? 0);
            if ($pct < 0) $pct = 0;
            if ($pct > 100) $pct = 100;
            $ev = $plannedEffort * ($pct / 100);
            $ac = (float) ($acSums[$taskId] ?? 0.0);

            $totalPV += $pv;
            $totalEV += $ev;
            $totalAC += $ac;
        }

        // Derived metrics with safe division
        $sv = $totalEV - $totalPV;
        $spi = ($totalPV > 0.0) ? ($totalEV / $totalPV) : null;
        $cv = $totalEV - $totalAC;
        $cpi = ($totalAC > 0.0) ? ($totalEV / $totalAC) : null;

        return [
            'project_id' => $projectId,
            'date' => $asOfDate,
            'baseline_id' => $baselineId,
            'pv' => round((float) $totalPV, 2),
            'ev' => round((float) $totalEV, 2),
            'ac' => round((float) $totalAC, 2),
            'sv' => round((float) $sv, 2),
            'spi' => $spi !== null ? round((float) $spi, 4) : null,
            'cv' => round((float) $cv, 2),
            'cpi' => $cpi !== null ? round((float) $cpi, 4) : null,
            'meta' => [
                'hours_per_day' => self::HOURS_PER_DAY,
                'task_count' => count($taskIds),
                'assignments_as_primary_effort' => true,
                'pv_fraction_inclusive_days' => true,
                'baseline_used' => $baselineId !== null,
            ],
        ];
    }
}
