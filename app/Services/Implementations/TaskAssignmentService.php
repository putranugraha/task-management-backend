<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\TaskAssignmentRepositoryInterface;
use App\Services\Contracts\TaskAssignmentServiceInterface;
use Illuminate\Support\Facades\Cache;

class TaskAssignmentService implements TaskAssignmentServiceInterface
{
    protected TaskAssignmentRepositoryInterface $repository;

    const CACHE_ALL = 'task_assignments.all';
    const CACHE_ID_PREFIX = 'task_assignment.'; // + id
    const CACHE_TASK_PREFIX = 'task_assignments.task.'; // + taskId
    const CACHE_USER_PREFIX = 'task_assignments.user.'; // + userId
    const CACHE_DURATION = 1800; // 30 minutes

    public function __construct(TaskAssignmentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllAssignments()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, fn () => $this->repository->getAllAssignments());
    }

    public function getAssignmentById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, fn () => $this->repository->getAssignmentById($id));
    }

    public function getAssignmentsByTask($taskId)
    {
        return Cache::remember(self::CACHE_TASK_PREFIX.$taskId, self::CACHE_DURATION, fn () => $this->repository->getAssignmentsByTask($taskId));
    }

    public function getAssignmentsByUser($userId)
    {
        return Cache::remember(self::CACHE_USER_PREFIX.$userId, self::CACHE_DURATION, fn () => $this->repository->getAssignmentsByUser($userId));
    }

    public function getAssignmentByTaskAndUser($taskId, $userId)
    {
        return $this->repository->getAssignmentByTaskAndUser($taskId, $userId);
    }

    public function createAssignment(array $data)
    {
        $assignment = $this->repository->createAssignment($data);
        $this->clearCaches($assignment->id ?? null, $assignment->task_id ?? null, $assignment->user_id ?? null);
        return $assignment;
    }

    public function updateAssignment($id, array $data)
    {
        $assignment = $this->repository->updateAssignment($id, $data);
        $this->clearCaches($id, $assignment->task_id ?? null, $assignment->user_id ?? null);
        return $assignment;
    }

    public function deleteAssignment($id)
    {
        $assignment = $this->getAssignmentById($id);
        $result = $this->repository->deleteAssignment($id);
        $this->clearCaches($id, $assignment->task_id ?? null, $assignment->user_id ?? null);
        return $result;
    }

    public function deleteAssignmentsByTask($taskId)
    {
        $result = $this->repository->deleteAssignmentsByTask($taskId);
        $this->clearCaches(null, $taskId, null);
        return $result;
    }

    public function deleteAssignmentsByUser($userId)
    {
        $result = $this->repository->deleteAssignmentsByUser($userId);
        $this->clearCaches(null, null, $userId);
        return $result;
    }

    protected function clearCaches($id = null, $taskId = null, $userId = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id) Cache::forget(self::CACHE_ID_PREFIX.$id);
        if ($taskId) Cache::forget(self::CACHE_TASK_PREFIX.$taskId);
        if ($userId) Cache::forget(self::CACHE_USER_PREFIX.$userId);
    }
}

