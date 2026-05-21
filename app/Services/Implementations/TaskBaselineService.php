<?php

namespace App\Services\Implementations;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\ProjectBaseline;
use App\Repositories\Contracts\TaskBaselineRepositoryInterface;
use App\Services\Contracts\TaskBaselineServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TaskBaselineService implements TaskBaselineServiceInterface
{
    protected TaskBaselineRepositoryInterface $repository;

    const CACHE_ALL = 'task_baselines.all';
    const CACHE_ID_PREFIX = 'task_baseline.'; // + id
    const CACHE_BASELINE_PREFIX = 'task_baselines.baseline.'; // + baselineId
    const CACHE_TASK_PREFIX = 'task_baselines.task.'; // + taskId
    const CACHE_WEIGHT_PREFIX = 'task_baselines.weight.'; // + baselineId
    const CACHE_DURATION = 1800; // 30 minutes

    public function __construct(TaskBaselineRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllTaskBaselines()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, function () {
            return $this->repository->getAllTaskBaselines();
        });
    }

    public function getTaskBaselineById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, function () use ($id) {
            return $this->repository->getTaskBaselineById($id);
        });
    }

    public function getTaskBaselinesByBaseline($baselineId)
    {
        return Cache::remember(self::CACHE_BASELINE_PREFIX.$baselineId, self::CACHE_DURATION, function () use ($baselineId) {
            return $this->repository->getTaskBaselinesByBaseline($baselineId);
        });
    }

    public function getTaskBaselinesByTask($taskId)
    {
        return Cache::remember(self::CACHE_TASK_PREFIX.$taskId, self::CACHE_DURATION, function () use ($taskId) {
            return $this->repository->getTaskBaselinesByTask($taskId);
        });
    }

    public function createTaskBaseline(array $data)
    {
        return DB::transaction(function () use ($data) {
            $task = Task::find($data['task_id'] ?? null);
            if (!$task) {
                return null;
            }

            // Determine baseline_id: prefer incoming value; otherwise pick latest project baseline by taken_at
            $baselineId = $data['baseline_id'] ?? null;
            if ($baselineId === null) {
                $latestBaseline = ProjectBaseline::where('project_id', $task->project_id)
                    ->latest('taken_at')
                    ->first();
                $baselineId = $latestBaseline->id ?? null;
            }

            // Inclusive duration helper
            $inclusiveDuration = function ($start, $end) {
                if (!$start || !$end) {
                    return null;
                }
                try {
                    $s = Carbon::parse($start);
                    $e = Carbon::parse($end);
                    $days = $s->diffInDays($e) + 1; // inclusive
                    return max(1, (int) $days);
                } catch (\Throwable $t) {
                    return null;
                }
            };

            $startBase = $data['start_planned_base'] ?? $task->start_planned;
            $endBase = $data['end_planned_base'] ?? $task->end_planned;
            $durationBase = $data['duration_planned_base']
                ?? ($task->duration_planned ?? null);

            if (empty($durationBase)) {
                $durationBase = $inclusiveDuration($startBase, $endBase);
            }
            if (!empty($durationBase)) {
                $durationBase = max(1, (int) $durationBase);
            }

            // Default weight = 1 if empty or 0
            $weight = $data['weight'] ?? null;
            if ($weight === null || (is_numeric($weight) && (float) $weight == 0.0)) {
                $weight = 1;
            }

            // Planned effort hours default = assignment snapshot, then duration x 8.
            $plannedEffort = $data['planned_effort_hours'] ?? null;
            if ($plannedEffort === null) {
                $assignmentEffort = (float) TaskAssignment::where('task_id', $task->id)
                    ->sum('estimated_effort_hours');
                if ($assignmentEffort > 0) {
                    $plannedEffort = $assignmentEffort;
                }
            }
            if ($plannedEffort === null && !empty($durationBase)) {
                $plannedEffort = (float) $durationBase * 8.0;
            }

            $budgetCostBase = $data['budget_cost_base'] ?? null;
            if ($budgetCostBase === null) {
                $budgetCostBase = max(0.0, (float) ($task->budget_cost ?? 0));
                if ($baselineId) {
                    $baseline = ProjectBaseline::find($baselineId);
                    $projectValue = max(0.0, (float) ($baseline?->value_amount_base ?? 0));
                    $totalTaskBudget = max(0.0, (float) Task::where('project_id', $task->project_id)->sum('budget_cost'));
                    if ($projectValue > 0 && $totalTaskBudget > 0) {
                        $budgetCostBase = round($budgetCostBase * ($projectValue / $totalTaskBudget), 2);
                    }
                }
            }

            $baselineData = [
                'baseline_id' => $baselineId,
                'task_id' => $task->id,
                'start_planned_base' => $startBase,
                'end_planned_base' => $endBase,
                'duration_planned_base' => $durationBase,
                'weight' => $weight,
                'planned_effort_hours' => $plannedEffort,
                'budget_cost_base' => $budgetCostBase,
            ];

            // Upsert by unique key (baseline_id, task_id)
            $taskBaseline = $this->repository->createTaskBaseline($baselineData);
            $this->clearCaches(
                $taskBaseline->id ?? null,
                $taskBaseline->baseline_id ?? ($data['baseline_id'] ?? null),
                $taskBaseline->task_id ?? ($data['task_id'] ?? null)
            );
            return $taskBaseline;
        });
    }

    public function updateTaskBaseline($id, array $data)
    {
        $taskBaseline = $this->repository->updateTaskBaseline($id, $data);
        $this->clearCaches($id, $taskBaseline->baseline_id ?? ($data['baseline_id'] ?? null), $taskBaseline->task_id ?? ($data['task_id'] ?? null));
        return $taskBaseline;
    }

    public function deleteTaskBaseline($id)
    {
        $taskBaseline = $this->repository->getTaskBaselineById($id);
        $result = $this->repository->deleteTaskBaseline($id);
        $this->clearCaches($id, $taskBaseline->baseline_id ?? null, $taskBaseline->task_id ?? null);
        return $result;
    }

    public function deleteTaskBaselinesByBaseline($baselineId)
    {
        $result = $this->repository->deleteTaskBaselinesByBaseline($baselineId);
        $this->clearCaches(null, $baselineId, null);
        return $result;
    }

    public function getTotalWeightByBaseline($baselineId)
    {
        return Cache::remember(self::CACHE_WEIGHT_PREFIX.$baselineId, self::CACHE_DURATION, function () use ($baselineId) {
            return $this->repository->getTotalWeightByBaseline($baselineId);
        });
    }

    protected function clearCaches($id = null, $baselineId = null, $taskId = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id !== null) {
            Cache::forget(self::CACHE_ID_PREFIX.$id);
        }
        if ($baselineId !== null) {
            Cache::forget(self::CACHE_BASELINE_PREFIX.$baselineId);
            Cache::forget(self::CACHE_WEIGHT_PREFIX.$baselineId);
        }
        if ($taskId !== null) {
            Cache::forget(self::CACHE_TASK_PREFIX.$taskId);
        }
    }
}

