<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Contracts\TaskServiceInterface;
use Illuminate\Support\Facades\Cache;
use App\Models\StatusHistory;
use Illuminate\Support\Facades\Auth;

class TaskService implements TaskServiceInterface
{
    protected TaskRepositoryInterface $repository;

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

    public function __construct(TaskRepositoryInterface $repository)
    {
        $this->repository = $repository;
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

    public function createTask(array $data)
    {
        $task = $this->repository->createTask($data);
        $this->clearCaches($task->id ?? null, $task->status ?? null, $task->project_id ?? null, $task->priority ?? null, $task->milestone_id ?? null);
        return $task;
    }

    public function updateTask($id, array $data)
    {
        $task = $this->repository->updateTask($id, $data);
        $this->clearCaches($id, $task->status ?? null, $task->project_id ?? null, $task->priority ?? null, $task->milestone_id ?? null);
        return $task;
    }

    public function deleteTask($id)
    {
        $task = $this->getTaskById($id);
        $result = $this->repository->deleteTask($id);
        $this->clearCaches($id, $task->status ?? null, $task->project_id ?? null, $task->priority ?? null, $task->milestone_id ?? null);
        return $result;
    }

    public function updateTaskStatus($id, $status)
    {
        if (!in_array($status, self::ALLOWED_STATUSES)) return null;
        $before = $this->getTaskById($id);
        $task = $this->repository->updateTaskStatus($id, $status);
        $this->clearCaches($id, $status, $task->project_id ?? null, $task->priority ?? null);
        if ($task) {
            StatusHistory::create([
                'task_id' => $task->id,
                'from_status' => $before->status ?? null,
                'to_status' => $task->status,
                'changed_by' => Auth::id(),
                'note' => null,
            ]);
        }
        return $task;
    }

    public function updateTaskProgress($id, $percent)
    {
        if (!is_numeric($percent) || $percent < 0 || $percent > 100) return null;
        $task = $this->repository->updateTaskProgress($id, (int) $percent);
        $this->clearCaches($id, $task->status ?? null, $task->project_id ?? null, $task->priority ?? null);
        return $task;
    }

    public function completeTask($id)
    {
        $before = $this->getTaskById($id);
        $task = $this->repository->completeTask($id);
        $this->clearCaches($id, $task->status ?? null, $task->project_id ?? null, $task->priority ?? null);
        if ($task) {
            StatusHistory::create([
                'task_id' => $task->id,
                'from_status' => $before->status ?? null,
                'to_status' => $task->status,
                'changed_by' => Auth::id(),
                'note' => 'Completed via action',
            ]);
        }
        return $task;
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
