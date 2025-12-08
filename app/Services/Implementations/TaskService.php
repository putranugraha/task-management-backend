<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Contracts\ProjectBaselineServiceInterface;
use App\Services\Contracts\TaskBaselineServiceInterface;
use App\Services\Contracts\TaskServiceInterface;
use Illuminate\Support\Facades\Cache;
use App\Models\StatusHistory;
use App\Models\Task;
use App\Models\Milestone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaskService implements TaskServiceInterface
{
    protected TaskRepositoryInterface $repository;
    protected TaskBaselineServiceInterface $taskBaselineService;
    protected ProjectBaselineServiceInterface $projectBaselineService;

    const CACHE_ALL = 'tasks.all';
    const CACHE_ID_PREFIX = 'task.'; // + id
    const CACHE_STATUS_PREFIX = 'tasks.status.'; // + status
    const CACHE_PROJECT_PREFIX = 'tasks.project.'; // + projectId
    const CACHE_MILESTONE_PREFIX = 'tasks.milestone.'; // + milestoneId
    const CACHE_PRIORITY_PREFIX = 'tasks.priority.'; // + priority
    const CACHE_DURATION = 1800; // 30 minutes

    // Allowed statuses for tasks
    const ALLOWED_STATUSES = ['To Do', 'In Progress', 'Done', 'On Hold', 'Cancelled'];
    const ALLOWED_PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];
    /**
     * Statuses considered "done" for milestone auto-completion checks.
     *
     * @var list<string>
     */
    const DONE_STATUSES_FOR_MILESTONE = ['Done'];

    public function __construct(TaskRepositoryInterface $repository, TaskBaselineServiceInterface $taskBaselineService, ProjectBaselineServiceInterface $projectBaselineService)
    {
        $this->repository = $repository;
        $this->taskBaselineService = $taskBaselineService;
        $this->projectBaselineService = $projectBaselineService;
    }

    public function getAllTasks()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, fn () => $this->repository->getAllTasks());
    }

    public function getTaskById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, fn () => $this->repository->getTaskById($id));
    }

    public function getTasksByProject($projectId)
    {
        return Cache::remember(self::CACHE_PROJECT_PREFIX.$projectId, self::CACHE_DURATION, fn () => $this->repository->getTasksByProject($projectId));
    }

    public function getTasksByMilestone($milestoneId)
    {
        return Cache::remember(self::CACHE_MILESTONE_PREFIX.$milestoneId, self::CACHE_DURATION, fn () => $this->repository->getTasksByMilestone($milestoneId));
    }

    public function getTasksByStatus($status)
    {
        return Cache::remember(self::CACHE_STATUS_PREFIX.$status, self::CACHE_DURATION, fn () => $this->repository->getTasksByStatus($status));
    }

    public function getTasksByPriority($priority)
    {
        return Cache::remember(self::CACHE_PRIORITY_PREFIX.$priority, self::CACHE_DURATION, fn () => $this->repository->getTasksByPriority($priority));
    }

    public function getTasksByPlannedDateRange($startDate, $endDate)
    {
        return $this->repository->getTasksByPlannedDateRange($startDate, $endDate);
    }

    public function getTasksByActualDateRange($startDate, $endDate)
    {
        return $this->repository->getTasksByActualDateRange($startDate, $endDate);
    }

    public function getTasksByDependsOnTask($dependsOnTaskId)
    {
        return $this->repository->getTasksByDependsOnTask($dependsOnTaskId);
    }

    public function paginateTasks(array $filters = [], int $perPage = 20)
    {
        // Untuk saat ini pagination tidak dicache supaya sederhana dan
        // menghindari kompleksitas key per kombinasi filter/page.
        return $this->repository->paginateTasks($filters, $perPage);
    }

    public function createTask(array $data)
    {
        $assignments = $data['assignments'] ?? null;
        $dependencies = $data['dependencies'] ?? null;
        unset($data['assignments']);
        unset($data['dependencies']);

        $task = null;
        DB::transaction(function () use (&$task, $data, $assignments, $dependencies) {
            $task = $this->repository->createTask($data);
            if ($task && is_array($assignments) && !empty($assignments)) {
                $rows = [];
                foreach ($assignments as $a) {
                    $rows[] = [
                        'user_id' => $a['user_id'],
                        'role_on_task' => $a['role_on_task'] ?? null,
                        'estimated_effort_hours' => $a['estimated_effort_hours'] ?? null,
                        'assigned_at' => now(),
                    ];
                }
                if (!empty($rows)) {
                    $task->assignments()->createMany($rows);
                }
            }

            if ($task && is_array($dependencies) && !empty($dependencies)) {
                $depRows = [];
                foreach ($dependencies as $d) {
                    $dependsId = $d['depends_on_task_id'] ?? null;
                    if (!$dependsId || $dependsId == $task->id) continue; // prevent self-dependency
                    $depRows[] = [
                        'depends_on_task_id' => $dependsId,
                        'type' => $d['type'] ?? 'FS',
                        'lag_days' => $d['lag_days'] ?? 0,
                    ];
                }
                if (!empty($depRows)) {
                    $task->dependencies()->createMany($depRows);
                }
            }
        });

        // Eager-load minimal relations for immediate response
        if ($task) {
            $task->loadMissing(['project', 'milestone', 'assignments.user', 'dependencies.dependsOn']);

            // Ensure a project baseline exists; create if missing
            $latest = $this->projectBaselineService->getLatestBaselineByProject($task->project_id);
            if (!$latest) {
                $latest = $this->projectBaselineService->createBaseline([
                    'project_id' => $task->project_id,
                    'baseline_name' => 'Initial Baseline',
                    'taken_at' => Carbon::now(),
                ]);
            }

            // Auto-snapshot this task into (latest) baseline
            $this->taskBaselineService->createTaskBaseline([
                // let service choose latest baseline if none passed
                'task_id' => $task->id,
            ]);
        }

        $this->clearCaches($task->id ?? null, $task->status ?? null, $task->project_id ?? null, $task->priority ?? null, $task->milestone_id ?? null);

        if ($task) {
            $actor = Auth::user();

            $properties = [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'milestone_id' => $task->milestone_id,
                'title' => $task->title,
                'priority' => $task->priority,
                'status' => $task->status,
                'percent_complete' => $task->percent_complete,
            ];

            $activity = activity('tasks')
                ->performedOn($task instanceof Task ? $task : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('created');
        }

        return $task;
    }

    public function updateTask($id, array $data)
    {
        $assignments = $data['assignments'] ?? null;
        $dependencies = $data['dependencies'] ?? null;
        unset($data['assignments']);
        unset($data['dependencies']);

        $before = $this->repository->getTaskById($id);

        $task = null;
        DB::transaction(function () use (&$task, $id, $data, $assignments, $dependencies) {
            $task = $this->repository->updateTask($id, $data);
            if ($task !== null && $assignments !== null) {
                // Replace strategy: delete existing, insert provided
                $task->assignments()->delete();
                if (is_array($assignments) && !empty($assignments)) {
                    $rows = [];
                    foreach ($assignments as $a) {
                        $rows[] = [
                            'user_id' => $a['user_id'],
                            'role_on_task' => $a['role_on_task'] ?? null,
                            'estimated_effort_hours' => $a['estimated_effort_hours'] ?? null,
                            'assigned_at' => now(),
                        ];
                    }
                    if (!empty($rows)) {
                        $task->assignments()->createMany($rows);
                    }
                }
            }

            if ($task !== null && $dependencies !== null) {
                // Replace dependencies if key provided
                $task->dependencies()->delete();
                if (is_array($dependencies) && !empty($dependencies)) {
                    $depRows = [];
                    foreach ($dependencies as $d) {
                        $dependsId = $d['depends_on_task_id'] ?? null;
                        if (!$dependsId || $dependsId == $task->id) continue; // prevent self-dependency
                        $depRows[] = [
                            'depends_on_task_id' => $dependsId,
                            'type' => $d['type'] ?? 'FS',
                            'lag_days' => $d['lag_days'] ?? 0,
                        ];
                    }
                    if (!empty($depRows)) {
                        $task->dependencies()->createMany($depRows);
                    }
                }
            }
        });

        if ($task) {
            $task->loadMissing(['project', 'milestone', 'assignments.user', 'dependencies.dependsOn']);
        }

        $this->clearCaches($id, $task->status ?? null, $task->project_id ?? null, $task->priority ?? null, $task->milestone_id ?? null);

        if ($task) {
            $actor = Auth::user();

            $properties = [
                'task_id' => $task->id,
                'project_id_before' => $before->project_id ?? null,
                'project_id_after' => $task->project_id,
                'milestone_id_before' => $before->milestone_id ?? null,
                'milestone_id_after' => $task->milestone_id,
                'title_before' => $before->title ?? null,
                'title_after' => $task->title,
                'priority_before' => $before->priority ?? null,
                'priority_after' => $task->priority,
                'status_before' => $before->status ?? null,
                'status_after' => $task->status,
                'percent_complete_before' => $before->percent_complete ?? null,
                'percent_complete_after' => $task->percent_complete,
            ];

            $activity = activity('tasks')
                ->performedOn($task instanceof Task ? $task : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('updated');

            $this->syncMilestoneCompletion($task);
        }

        return $task;
    }

    public function deleteTask($id)
    {
        $task = $this->getTaskById($id);
        $result = $this->repository->deleteTask($id);
        $this->clearCaches($id, $task->status ?? null, $task->project_id ?? null, $task->priority ?? null, $task->milestone_id ?? null);

        if ($result && $task) {
            $actor = Auth::user();

            $properties = [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'milestone_id' => $task->milestone_id,
                'title' => $task->title,
                'priority' => $task->priority,
                'status' => $task->status,
                'percent_complete' => $task->percent_complete,
            ];

            $activity = activity('tasks')
                ->performedOn($task instanceof Task ? $task : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('deleted');
        }

        return $result;
    }

    public function updateTaskStatus($id, $status)
    {
        if (!in_array($status, self::ALLOWED_STATUSES)) return null;
        $before = $this->getTaskById($id);
        $task = $this->repository->updateTaskStatus($id, $status);
        $this->clearCaches(
            $id,
            $status,
            $task->project_id ?? null,
            $task->priority ?? null,
            $task->milestone_id ?? null,
        );
        if ($task) {
            $changedBy = Auth::id();

            StatusHistory::create([
                'task_id' => $task->id,
                'from_status' => $before->status ?? null,
                'to_status' => $task->status,
                'changed_by' => $changedBy,
                'note' => null,
            ]);

            $actor = Auth::user();

            $properties = [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'milestone_id' => $task->milestone_id,
                'status_before' => $before->status ?? null,
                'status_after' => $task->status,
            ];

            $activity = activity('tasks')
                ->performedOn($task instanceof Task ? $task : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('status_changed');

            $this->syncMilestoneCompletion($task);
        }
        return $task;
    }

    public function updateTaskProgress($id, $percent)
    {
        if (!is_numeric($percent) || $percent < 0 || $percent > 100) return null;

        $before = $this->getTaskById($id);
        $task = $this->repository->updateTaskProgress($id, (int) $percent);
        $this->clearCaches(
            $id,
            $task->status ?? null,
            $task->project_id ?? null,
            $task->priority ?? null,
            $task->milestone_id ?? null,
        );

        if ($task) {
            $actor = Auth::user();

            $properties = [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'milestone_id' => $task->milestone_id,
                'percent_complete_before' => $before->percent_complete ?? null,
                'percent_complete_after' => $task->percent_complete,
            ];

            $activity = activity('tasks')
                ->performedOn($task instanceof Task ? $task : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('progress_updated');
        }

        return $task;
    }

    public function completeTask($id)
    {
        $before = $this->getTaskById($id);
        $task = $this->repository->completeTask($id);
        $this->clearCaches(
            $id,
            $task->status ?? null,
            $task->project_id ?? null,
            $task->priority ?? null,
            $task->milestone_id ?? null,
        );
        if ($task) {
            StatusHistory::create([
                'task_id' => $task->id,
                'from_status' => $before->status ?? null,
                'to_status' => $task->status,
                'changed_by' => Auth::id(),
                'note' => 'Completed via action',
            ]);

            $actor = Auth::user();

            $properties = [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'milestone_id' => $task->milestone_id,
                'status_before' => $before->status ?? null,
                'status_after' => $task->status,
            ];

            $activity = activity('tasks')
                ->performedOn($task instanceof Task ? $task : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('completed');

            $this->syncMilestoneCompletion($task);
        }
        return $task;
    }

    /**
     * Automatically sync milestone status based on all its tasks.
     *
     * - If all tasks in the milestone are in a "done" status, mark milestone as Completed.
     * - If milestone is Completed but there is at least one non-done task, reopen it to In Progress.
     */
    protected function syncMilestoneCompletion(?Task $task): void
    {
        if (!$task || !$task->milestone_id) {
            return;
        }

        $milestoneId = $task->milestone_id;

        $milestone = Milestone::find($milestoneId);
        if (!$milestone) {
            return;
        }

        $remaining = Task::where('milestone_id', $milestoneId)
            ->whereNotIn('status', self::DONE_STATUSES_FOR_MILESTONE)
            ->count();

        if ($remaining === 0) {
            if ($milestone->status !== 'Completed') {
                $endActualMax = Task::where('milestone_id', $milestoneId)->max('end_actual');
                $endPlannedMax = Task::where('milestone_id', $milestoneId)->max('end_planned');

                if ($endActualMax) {
                    $milestone->due_actual = $endActualMax;
                } elseif ($endPlannedMax) {
                    $milestone->due_actual = $endPlannedMax;
                } elseif (!$milestone->due_actual) {
                    $milestone->due_actual = Carbon::now();
                }

                $milestone->status = 'Completed';
                $milestone->save();
                $this->clearMilestoneCaches($milestone);
            }
        } else {
            if ($milestone->status === 'Completed') {
                $milestone->status = 'In Progress';
                $milestone->save();
                $this->clearMilestoneCaches($milestone);
            }
        }
    }

    /**
     * Clear milestone-related caches when its status changes.
     *
     * Mirrors the keys used in MilestoneService without changing its structure.
     */
    protected function clearMilestoneCaches(Milestone $milestone): void
    {
        Cache::forget('milestones.all');
        if ($milestone->project_id) {
            Cache::forget('milestones.project.'.$milestone->project_id);
        }
        if ($milestone->status) {
            Cache::forget('milestones.status.'.$milestone->status);
        }
        Cache::forget('milestone.'.$milestone->id);
    }

    protected function clearCaches($id = null, $status = null, $projectId = null, $priority = null, $milestoneId = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id) Cache::forget(self::CACHE_ID_PREFIX.$id);
        if ($status) Cache::forget(self::CACHE_STATUS_PREFIX.$status);
        if ($projectId) Cache::forget(self::CACHE_PROJECT_PREFIX.$projectId);
        if ($priority) Cache::forget(self::CACHE_PRIORITY_PREFIX.$priority);
        if ($milestoneId) Cache::forget(self::CACHE_MILESTONE_PREFIX.$milestoneId);
    }
}
