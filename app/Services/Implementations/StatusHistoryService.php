<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\StatusHistoryRepositoryInterface;
use App\Services\Contracts\StatusHistoryServiceInterface;
use Illuminate\Support\Facades\Cache;

class StatusHistoryService implements StatusHistoryServiceInterface
{
    protected StatusHistoryRepositoryInterface $repository;

    const CACHE_ALL = 'status_histories.all';
    const CACHE_ID_PREFIX = 'status_history.'; // + id
    const CACHE_ACTOR_PREFIX = 'status_histories.actor.'; // + actorId
    const CACHE_ENTITY_TYPE_PREFIX = 'status_histories.entity_type.'; // + entityType
    const CACHE_ENTITY_PREFIX = 'status_histories.entity.'; // + entityType.entityId
    const CACHE_RANGE_PREFIX = 'status_histories.range.'; // + start_end
    const CACHE_DURATION = 900; // 15 minutes

    public function __construct(StatusHistoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllHistories()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, fn () => $this->repository->getAllHistories());
    }

    public function getHistoryById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, fn () => $this->repository->getHistoryById($id));
    }

    public function getHistoriesByActor($actorId)
    {
        return Cache::remember(self::CACHE_ACTOR_PREFIX.$actorId, self::CACHE_DURATION, fn () => $this->repository->getHistoriesByActor($actorId));
    }

    public function getHistoriesByEntityType($entityType)
    {
        return Cache::remember(self::CACHE_ENTITY_TYPE_PREFIX.$entityType, self::CACHE_DURATION, fn () => $this->repository->getHistoriesByEntityType($entityType));
    }

    public function getHistoriesByEntity($entityType, $entityId)
    {
        $key = self::CACHE_ENTITY_PREFIX.$entityType.'.'.$entityId;
        return Cache::remember($key, self::CACHE_DURATION, fn () => $this->repository->getHistoriesByEntity($entityType, $entityId));
    }

    public function createHistory(array $data)
    {
        $row = $this->repository->createHistory($data);
        $this->clearCaches($row->id ?? null, $row->changed_by ?? null, 'Task', $row->task_id ?? null);
        return $row;
    }

    public function deleteHistory($id)
    {
        $row = $this->repository->getHistoryById($id);
        $result = $this->repository->deleteHistory($id);
        $this->clearCaches($id, $row->changed_by ?? null, 'Task', $row->task_id ?? null);
        return $result;
    }

    public function deleteHistoriesByEntity($entityType, $entityId)
    {
        $result = $this->repository->deleteHistoriesByEntity($entityType, $entityId);
        $this->clearCaches(null, null, $entityType, $entityId);
        return $result;
    }

    public function getHistoriesByDateRange($startDate, $endDate)
    {
        $key = self::CACHE_RANGE_PREFIX.$startDate.'_'.$endDate;
        return Cache::remember($key, self::CACHE_DURATION, fn () => $this->repository->getHistoriesByDateRange($startDate, $endDate));
    }

    protected function clearCaches($id = null, $actorId = null, $entityType = null, $entityId = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id) Cache::forget(self::CACHE_ID_PREFIX.$id);
        if ($actorId) Cache::forget(self::CACHE_ACTOR_PREFIX.$actorId);
        if ($entityType) Cache::forget(self::CACHE_ENTITY_TYPE_PREFIX.$entityType);
        if ($entityType && $entityId) Cache::forget(self::CACHE_ENTITY_PREFIX.$entityType.'.'.$entityId);
    }
}

