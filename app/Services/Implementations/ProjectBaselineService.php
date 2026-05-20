<?php

namespace App\Services\Implementations;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Repositories\Contracts\ProjectBaselineRepositoryInterface;
use App\Services\Contracts\ProjectBaselineServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProjectBaselineService implements ProjectBaselineServiceInterface
{
    protected ProjectBaselineRepositoryInterface $repository;

    const CACHE_ALL = 'project_baselines.all';
    const CACHE_ID_PREFIX = 'project_baseline.'; // + id
    const CACHE_PROJECT_PREFIX = 'project_baselines.project.'; // + projectId
    const CACHE_LATEST_PREFIX = 'project_baselines.latest.'; // + projectId
    const CACHE_DURATION = 1800; // 30 minutes

    public function __construct(ProjectBaselineRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllBaselines()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, function () {
            return $this->repository->getAllBaselines();
        });
    }

    public function getBaselineById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, function () use ($id) {
            return $this->repository->getBaselineById($id);
        });
    }

    public function getBaselinesByProject($projectId)
    {
        return Cache::remember(self::CACHE_PROJECT_PREFIX.$projectId, self::CACHE_DURATION, function () use ($projectId) {
            return $this->repository->getBaselinesByProject($projectId);
        });
    }

    public function getBaselineByName($projectId, $baselineName)
    {
        return $this->repository->getBaselineByName($projectId, $baselineName);
    }

    public function getLatestBaselineByProject($projectId)
    {
        return Cache::remember(self::CACHE_LATEST_PREFIX.$projectId, self::CACHE_DURATION, function () use ($projectId) {
            return $this->repository->getLatestBaselineByProject($projectId);
        });
    }

    public function createBaseline(array $data)
    {
        return DB::transaction(function () use ($data) {
            $projectId = $data['project_id'] ?? null;
            if (!$projectId) {
                return null;
            }

            $project = Project::find($projectId);
            if (!$project) {
                return null;
            }

            $start = $project->start_planned ?? Carbon::now();

            $tasks = Task::where('project_id', $project->id)->get(['id','start_planned','end_planned','duration_planned','budget_cost']);
            $taskIds = $tasks->pluck('id')->all();
            $assignmentEffortByTask = [];
            if (! empty($taskIds)) {
                $assignmentEffortByTask = TaskAssignment::query()
                    ->whereIn('task_id', $taskIds)
                    ->selectRaw('task_id, COALESCE(SUM(estimated_effort_hours),0) as sum_hours')
                    ->groupBy('task_id')
                    ->pluck('sum_hours', 'task_id')
                    ->toArray();
            }
            $totalDays = (int) $tasks->sum('duration_planned');
            $end = $totalDays > 0 ? Carbon::parse($start)->copy()->addDays($totalDays) : Carbon::parse($start)->copy();
            $projectValue = max(0.0, (float) ($project->value_amount ?? 0));
            $totalTaskBudget = max(0.0, (float) $tasks->sum('budget_cost'));
            $budgetScale = ($projectValue > 0 && $totalTaskBudget > 0)
                ? $projectValue / $totalTaskBudget
                : 1.0;
            $remainingBaselineBudget = $projectValue > 0 ? $projectValue : null;
            $taskCount = $tasks->count();
            $taskIndex = 0;

            // Respect FE-provided base dates; fallback to computed values if absent
            $data['start_planned_base'] = $data['start_planned_base'] ?? Carbon::parse($start)->toDateString();
            $data['end_planned_base'] = $data['end_planned_base'] ?? Carbon::parse($end)->toDateString();
            $data['value_amount_base'] = $data['value_amount_base'] ?? $projectValue;

            // Default taken_at to now if missing (request still requires it, but be defensive)
            if (empty($data['taken_at'])) {
                $data['taken_at'] = Carbon::now();
            }

            $baseline = $this->repository->createBaseline($data);
            if (!$baseline) {
                return null;
            }

            // Generate task_baselines snapshot for each task
            foreach ($tasks as $task) {
                $taskIndex++;
                // Inclusive duration from dates; fallback to task->duration_planned
                $duration = null;
                if ($task->start_planned && $task->end_planned) {
                    try {
                        $s = Carbon::parse($task->start_planned);
                        $e = Carbon::parse($task->end_planned);
                        $duration = max(1, $s->diffInDays($e) + 1);
                    } catch (\Throwable $t) {
                        $duration = null;
                    }
                }
                if (!$duration && $task->duration_planned) {
                    $duration = max(1, (int) $task->duration_planned);
                }

                $budgetCostBase = max(0.0, (float) ($task->budget_cost ?? 0));
                if ($projectValue > 0 && $totalTaskBudget > 0) {
                    if ($taskIndex === $taskCount) {
                        $budgetCostBase = max(0.0, (float) $remainingBaselineBudget);
                    } else {
                        $budgetCostBase = round($budgetCostBase * $budgetScale, 2);
                        $remainingBaselineBudget = max(0.0, (float) $remainingBaselineBudget - $budgetCostBase);
                    }
                }

                $plannedEffortHours = (float) ($assignmentEffortByTask[$task->id] ?? 0);
                if ($plannedEffortHours <= 0 && $duration) {
                    $plannedEffortHours = (float) $duration * 8.0;
                }

                $baseline->taskBaselines()->create([
                    'task_id' => $task->id,
                    'start_planned_base' => $task->start_planned,
                    'end_planned_base' => $task->end_planned,
                    'duration_planned_base' => $duration,
                    'planned_effort_hours' => $plannedEffortHours > 0 ? $plannedEffortHours : null,
                    'budget_cost_base' => $budgetCostBase,
                    'weight' => 1,
                ]);
            }

            $this->clearCaches($baseline->id ?? null, $baseline->project_id ?? $project->id);

            return $baseline;
        });
    }

    public function updateBaseline($id, array $data)
    {
        $baseline = $this->repository->updateBaseline($id, $data);
        $this->clearCaches($id, $baseline->project_id ?? ($data['project_id'] ?? null));
        return $baseline;
    }

    public function deleteBaseline($id)
    {
        $baseline = $this->repository->getBaselineById($id);
        $result = $this->repository->deleteBaseline($id);
        $this->clearCaches($id, $baseline->project_id ?? null);
        return $result;
    }

    public function deleteBaselinesByProject($projectId)
    {
        $result = $this->repository->deleteBaselinesByProject($projectId);
        $this->clearCaches(null, $projectId);
        return $result;
    }

    protected function clearCaches($id = null, $projectId = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id !== null) {
            Cache::forget(self::CACHE_ID_PREFIX.$id);
        }
        if ($projectId !== null) {
            Cache::forget(self::CACHE_PROJECT_PREFIX.$projectId);
            Cache::forget(self::CACHE_LATEST_PREFIX.$projectId);
        }
    }
}

