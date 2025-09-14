<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\TaskDependencyRepositoryInterface;
use App\Services\Contracts\TaskDependencyServiceInterface;
use Illuminate\Support\Facades\Cache;

class TaskDependencyService implements TaskDependencyServiceInterface
{
    protected TaskDependencyRepositoryInterface $repository;

    const CACHE_ALL = 'task_deps.all';
    const CACHE_ID_PREFIX = 'task_dep.'; // + id
    const CACHE_TASK_PREFIX = 'task_deps.task.'; // + taskId
    const CACHE_DEPENDS_PREFIX = 'task_deps.depends.'; // + dependsOnTaskId
    const CACHE_DURATION = 1800; // 30 minutes

    const ALLOWED_TYPES = ['FS', 'SS', 'FF', 'SF'];

    public function __construct(TaskDependencyRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllDependencies()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, fn () => $this->repository->getAllDependencies());
    }

    public function getDependencyById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, fn () => $this->repository->getDependencyById($id));
    }

    public function getDependenciesByTask($taskId)
    {
        return Cache::remember(self::CACHE_TASK_PREFIX.$taskId, self::CACHE_DURATION, fn () => $this->repository->getDependenciesByTask($taskId));
    }

    public function getDependentsByTask($dependsOnTaskId)
    {
        return Cache::remember(self::CACHE_DEPENDS_PREFIX.$dependsOnTaskId, self::CACHE_DURATION, fn () => $this->repository->getDependentsByTask($dependsOnTaskId));
    }

    public function createDependency(array $data)
    {
        if (isset($data['type']) && !in_array($data['type'], self::ALLOWED_TYPES)) return null;
        if (($data['task_id'] ?? null) && ($data['depends_on_task_id'] ?? null) && $data['task_id'] == $data['depends_on_task_id']) return null;

        $dep = $this->repository->createDependency($data);
        $this->clearCaches($dep->id ?? null, $dep->task_id ?? null, $dep->depends_on_task_id ?? null);
        return $dep;
    }

    public function updateDependency($id, array $data)
    {
        if (isset($data['type']) && !in_array($data['type'], self::ALLOWED_TYPES)) return null;
        if (($data['task_id'] ?? null) && ($data['depends_on_task_id'] ?? null) && $data['task_id'] == $data['depends_on_task_id']) return null;

        $dep = $this->repository->updateDependency($id, $data);
        $this->clearCaches($id, $dep->task_id ?? null, $dep->depends_on_task_id ?? null);
        return $dep;
    }

    public function deleteDependency($id)
    {
        $dep = $this->getDependencyById($id);
        $result = $this->repository->deleteDependency($id);
        $this->clearCaches($id, $dep->task_id ?? null, $dep->depends_on_task_id ?? null);
        return $result;
    }

    public function deleteDependenciesByTask($taskId)
    {
        $result = $this->repository->deleteDependenciesByTask($taskId);
        $this->clearCaches(null, $taskId, null);
        return $result;
    }

    protected function clearCaches($id = null, $taskId = null, $dependsOnTaskId = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id) Cache::forget(self::CACHE_ID_PREFIX.$id);
        if ($taskId) Cache::forget(self::CACHE_TASK_PREFIX.$taskId);
        if ($dependsOnTaskId) Cache::forget(self::CACHE_DEPENDS_PREFIX.$dependsOnTaskId);
    }
}

