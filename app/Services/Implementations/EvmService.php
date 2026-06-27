<?php

namespace App\Services\Implementations;

use App\Models\ProjectBaseline;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskBaseline;
use App\Models\TaskProgressEntry;
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
            if (! $baseline) {
                abort(422, 'Invalid baseline_id for this project.');
            }
        }
        if (!$baseline) {
            $baseline = $this->baselineService->getLatestBaselineByProject($projectId);
        }
        $baselineId = $baseline->id ?? null;

        // Fetch tasks for project (minimal columns).
        // When a baseline is selected, only tasks captured in that baseline are part of the baseline calculation.
        $tasksQuery = ($baselineId ? Task::withTrashed() : Task::query())
            ->where('project_id', $projectId)
            ->where(function ($query) {
                $query->whereNull('milestone_id')
                    ->orWhereHas('milestone');
            });

        if ($baselineId) {
            $tasksQuery
                ->whereHas('baselines', function ($query) use ($baselineId) {
                    $query->where('baseline_id', $baselineId);
                });

            if ($baseline?->taken_at) {
                $tasksQuery->where('created_at', '<=', $baseline->taken_at);
            }
        }

        $tasks = $tasksQuery
            ->get([
                'id', 'project_id', 'start_planned', 'end_planned', 'duration_planned', 'percent_complete', 'created_at',
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

        // Historical progress (EV) up to as-of date: task_id -> latest percent_complete.
        // Falls back to current percent only for today's query when no history exists yet.
        $progressAsOf = [];
        if (!empty($taskIds)) {
            try {
                $progressAsOf = TaskProgressEntry::query()
                    ->whereIn('task_id', $taskIds)
                    ->whereDate('progress_date', '<=', $asOfDate)
                    ->selectRaw('DISTINCT ON (task_id) task_id, percent_complete')
                    ->orderBy('task_id')
                    ->orderByDesc('progress_date')
                    ->orderByDesc('id')
                    ->pluck('percent_complete', 'task_id')
                    ->toArray();
            } catch (\Throwable $e) {
                $progressAsOf = [];
            }
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
        $baselineEffortRows = 0;

        foreach ($tasks as $task) {
            $taskId = $task->id;

            // Planned effort priority:
            // 1) task_baselines.planned_effort_hours when a baseline is selected
            // 2) current assignment estimated effort
            // 3) duration x 8 fallback
            $plannedEffort = null;
            $baseRow = $taskBaselineMap[$taskId] ?? null;
            $startPlanned = $baseRow['start_planned_base'] ?? $task->start_planned;
            $durationPlanned = (int) ($baseRow['duration_planned_base'] ?? $task->duration_planned ?? 0);

            $tbEffort = isset($baseRow['planned_effort_hours']) ? (float) $baseRow['planned_effort_hours'] : 0.0;
            if ($baselineId && $tbEffort > 0) {
                $plannedEffort = $tbEffort;
                $baselineEffortRows++;
            }

            $assignEffort = (float) ($assignSums[$taskId] ?? 0);
            if (($plannedEffort === null || $plannedEffort <= 0) && $assignEffort > 0) {
                $plannedEffort = $assignEffort;
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
            $today = Carbon::today()->toDateString();
            if (array_key_exists($taskId, $progressAsOf)) {
                $pct = (int) $progressAsOf[$taskId];
            } elseif ($asOfDate === $today) {
                $pct = (int) ($task->percent_complete ?? 0);
            } else {
                $pct = 0;
            }
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
                'planned_effort_source' => ($baselineId && $baselineEffortRows > 0)
                    ? 'task_baselines.planned_effort_hours'
                    : 'task_assignments.estimated_effort_hours',
                'assignments_as_primary_effort' => !($baselineId && $baselineEffortRows > 0),
                'baseline_effort_rows' => $baselineEffortRows,
                'pv_fraction_inclusive_days' => true,
                'baseline_used' => $baselineId !== null,
            ],
        ];
    }
}
