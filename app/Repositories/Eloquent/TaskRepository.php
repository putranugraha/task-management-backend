<?php

namespace App\Repositories\Eloquent;

use App\Models\Task;
use App\Models\Project;
use App\Models\Milestone;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class TaskRepository implements TaskRepositoryInterface
{
    private const STATS_CACHE_TTL_SECONDS = 60;

    /** @var Task */
    protected $model;

    public function __construct(Task $model)
    {
        $this->model = $model;
    }

    public function getAllTasks()
    {
        return $this->activeQuery()->with(['project', 'milestone'])->get();
    }

    public function getTaskById($id)
    {
        try {
            return $this->activeQuery()->with(['project', 'milestone', 'assignments.user', 'dependencies.dependsOn'])->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Task with ID {$id} not found.");
            return null;
        }
    }

    public function getTasksByProject($projectId)
    {
        return $this->activeQuery()->where('project_id', $projectId)->with(['project', 'milestone'])->get();
    }

    public function getTasksByMilestone($milestoneId)
    {
        return $this->activeQuery()->where('milestone_id', $milestoneId)->with(['project', 'milestone'])->get();
    }

    public function getTasksByStatus($status)
    {
        return $this->activeQuery()->where('status', $status)->with(['project', 'milestone'])->get();
    }

    public function getTasksByPriority($priority)
    {
        return $this->activeQuery()->where('priority', $priority)->with(['project', 'milestone'])->get();
    }

    public function getTasksByPlannedDateRange($startDate, $endDate)
    {
        // Jatuh tempo planned: gunakan end_planned berada di antara range
        return $this->activeQuery()
            ->whereNotNull('end_planned')
            ->whereDate('end_planned', '>=', $startDate)
            ->whereDate('end_planned', '<=', $endDate)
            ->with(['project', 'milestone'])
            ->get();
    }

    public function getTasksByActualDateRange($startDate, $endDate)
    {
        // Selesai di rentang actual: gunakan end_actual berada di antara range
        return $this->activeQuery()
            ->whereNotNull('end_actual')
            ->whereDate('end_actual', '>=', $startDate)
            ->whereDate('end_actual', '<=', $endDate)
            ->with(['project', 'milestone'])
            ->get();
    }

    public function createTask(array $data)
    {
        try {
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error("Failed to create task: {$e->getMessage()}");
            return null;
        }
    }

    public function updateTask($id, array $data)
    {
        $task = $this->find($id);
        if (!$task) return null;

        try {
            if (($data['status'] ?? null) === 'Done') {
                if (empty($data['end_actual']) && !$task->end_actual) {
                    $data['end_actual'] = Carbon::now()->toDateString();
                }
                if (!array_key_exists('percent_complete', $data) && (int) ($task->percent_complete ?? 0) < 100) {
                    $data['percent_complete'] = 100;
                }
            }
            $task->update($data);
            if ($task->status === 'Done') {
                $this->updateProjectCompletionIfDone($task->project_id);
            }
            return $task->fresh(['project', 'milestone']);
        } catch (\Exception $e) {
            Log::error("Failed to update task {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteTask($id)
    {
        $task = $this->find($id);
        if (!$task) return false;

        try {
            $task->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete task {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function getArchivedTasks(array $filters = [], int $perPage = 20)
    {
        $query = $this->model
            ->onlyTrashed()
            ->whereHas('project')
            ->where(function ($query) {
                $query->whereNull('milestone_id')
                    ->orWhereHas('milestone');
            })
            ->with(['project', 'milestone']);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('deleted_at')
            ->paginate($perPage);
    }

    public function restoreTask($id)
    {
        $task = $this->model->onlyTrashed()->find($id);
        if (!$task) return null;
        if (!Project::query()->whereKey($task->project_id)->exists()) return null;
        if ($task->milestone_id && !Milestone::query()->whereKey($task->milestone_id)->exists()) return null;

        try {
            $task->restore();
            return $task->fresh(['project', 'milestone']);
        } catch (\Exception $e) {
            Log::error("Failed to restore task {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function updateTaskStatus($id, $status)
    {
        $task = $this->find($id);
        if (!$task) return null;

        $task->status = $status;
        if ($status === 'Done' && !$task->end_actual) {
            $task->end_actual = Carbon::now()->toDateString();
        }
        $task->save();
        if ($status === 'Done') {
            $this->updateProjectCompletionIfDone($task->project_id);
        }
        return $task->fresh(['project', 'milestone']);
    }

    public function updateTaskProgress($id, $percent)
    {
        $task = $this->find($id);
        if (!$task) return null;
        $task->percent_complete = $percent;
        $task->save();
        return $task->fresh(['project', 'milestone']);
    }

    public function completeTask($id)
    {
        $task = $this->find($id);
        if (!$task) return null;

        $task->status = 'Done';
        $task->end_actual = Carbon::now()->toDateString();
        $task->percent_complete = 100;
        $task->save();
        $this->updateProjectCompletionIfDone($task->project_id);

        return $task->fresh(['project', 'milestone']);
    }

    public function getTasksByDependsOnTask($dependsOnTaskId)
    {
        return $this->activeQuery()
            ->whereHas('dependencies', function ($q) use ($dependsOnTaskId) {
                $q->where('depends_on_task_id', $dependsOnTaskId);
            })
            ->with(['project', 'milestone', 'dependencies.dependsOn'])
            ->get();
    }

    public function paginateTasks(array $filters = [], int $perPage = 20)
    {
        $query = $this->activeQuery()->with(['project', 'milestone']);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Hitung jumlah task total dan per status berdasarkan filter sederhana.
     *
     * @param array $filters
     * @return array{total:int,by_status:array<string,int>}
     */
    public function getTaskStatusCounts(array $filters = []): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $cacheKey = 'tasks:status-counts:' . md5(json_encode($normalizedFilters));

        return Cache::remember($cacheKey, self::STATS_CACHE_TTL_SECONDS, function () use ($normalizedFilters) {
            $baseQuery = $this->activeQuery();

            if (isset($normalizedFilters['project_id'])) {
                $baseQuery->where('project_id', $normalizedFilters['project_id']);
            }

            if (isset($normalizedFilters['milestone_id'])) {
                $baseQuery->where('milestone_id', $normalizedFilters['milestone_id']);
            }

            if (isset($normalizedFilters['status'])) {
                $baseQuery->where('status', $normalizedFilters['status']);
            }

            if (isset($normalizedFilters['priority'])) {
                $baseQuery->where('priority', $normalizedFilters['priority']);
            }

            if (!empty($normalizedFilters['search'])) {
                $search = $normalizedFilters['search'];
                $baseQuery->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('priority', 'like', "%{$search}%")
                        ->orWhereHas('project', function ($qp) use ($search) {
                            $qp->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('milestone', function ($qm) use ($search) {
                            $qm->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $total = (clone $baseQuery)->count();

            $byStatus = (clone $baseQuery)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->toArray();

            return [
                'total' => (int) $total,
                'by_status' => array_map('intval', $byStatus),
            ];
        });
    }

    protected function normalizeFilters(array $filters): array
    {
        ksort($filters);

        return array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $filters);
    }

    protected function applyFilters($query, array $filters): void
    {
        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (isset($filters['milestone_id'])) {
            $query->where('milestone_id', $filters['milestone_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('priority', 'like', "%{$search}%")
                    ->orWhereHas('project', function ($qp) use ($search) {
                        $qp->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('milestone', function ($qm) use ($search) {
                        $qm->where('name', 'like', "%{$search}%");
                    });
            });
        }
    }

    protected function find($id)
    {
        try {
            return $this->activeQuery()->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Task with ID {$id} not found.");
            return null;
        }
    }

    protected function activeQuery()
    {
        return $this->model->newQuery()
            ->whereHas('project')
            ->where(function ($query) {
                $query->whereNull('milestone_id')
                    ->orWhereHas('milestone');
            });
    }

    protected function updateProjectCompletionIfDone(?int $projectId): void
    {
        if (!$projectId) {
            return;
        }

        $project = Project::find($projectId);
        if (!$project) {
            return;
        }

        if (in_array($project->status, ['Completed', 'Cancelled'], true)) {
            return;
        }

        $taskTotal = Task::where('project_id', $projectId)->count();
        if ($taskTotal === 0) {
            return;
        }

        $taskDone = Task::where('project_id', $projectId)
            ->where('status', 'Done')
            ->count();

        if ($taskDone !== $taskTotal) {
            return;
        }

        $milestoneTotal = Milestone::where('project_id', $projectId)->count();
        if ($milestoneTotal > 0) {
            $milestoneDone = Milestone::where('project_id', $projectId)
                ->where('status', 'Completed')
                ->count();

            if ($milestoneDone !== $milestoneTotal) {
                return;
            }
        }

        $project->status = 'Completed';
        $project->save();
        Cache::forget('projects.all');
        Cache::forget('project.'.$projectId);
        Cache::forget('projects.status.'.$project->status);
    }
}
