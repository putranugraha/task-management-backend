<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\TaskBaselineRepositoryInterface;
use App\Services\Contracts\TaskBaselineServiceInterface;
use Illuminate\Support\Facades\Cache;

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
        $taskBaseline = $this->repository->createTaskBaseline($data);
        $this->clearCaches($taskBaseline->id ?? null, $taskBaseline->baseline_id ?? ($data['baseline_id'] ?? null), $taskBaseline->task_id ?? ($data['task_id'] ?? null));
        return $taskBaseline;
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

