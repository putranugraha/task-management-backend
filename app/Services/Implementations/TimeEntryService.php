<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\TimeEntryRepositoryInterface;
use App\Services\Contracts\TimeEntryServiceInterface;
use Illuminate\Support\Facades\Cache;

class TimeEntryService implements TimeEntryServiceInterface
{
    protected TimeEntryRepositoryInterface $repository;

    const CACHE_ALL = 'time_entries.all';
    const CACHE_ID_PREFIX = 'time_entry.'; // + id
    const CACHE_TASK_PREFIX = 'time_entries.task.'; // + taskId
    const CACHE_USER_PREFIX = 'time_entries.user.'; // + userId
    const CACHE_RANGE_PREFIX = 'time_entries.range.'; // + start_end
    const CACHE_TOTAL_TASK_PREFIX = 'time_entries.total.task.'; // + taskId
    const CACHE_TOTAL_USER_PREFIX = 'time_entries.total.user.'; // + userId
    const CACHE_DURATION = 900; // 15 minutes

    public function __construct(TimeEntryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllTimeEntries()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, fn () => $this->repository->getAllTimeEntries());
    }

    public function getTimeEntryById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, fn () => $this->repository->getTimeEntryById($id));
    }

    public function getTimeEntriesByTask($taskId)
    {
        return Cache::remember(self::CACHE_TASK_PREFIX.$taskId, self::CACHE_DURATION, fn () => $this->repository->getTimeEntriesByTask($taskId));
    }

    public function getTimeEntriesByUser($userId)
    {
        return Cache::remember(self::CACHE_USER_PREFIX.$userId, self::CACHE_DURATION, fn () => $this->repository->getTimeEntriesByUser($userId));
    }

    public function getTimeEntriesByTaskAndUser($taskId, $userId)
    {
        return $this->repository->getTimeEntriesByTaskAndUser($taskId, $userId);
    }

    public function getTimeEntriesByDateRange($startDate, $endDate)
    {
        $key = self::CACHE_RANGE_PREFIX.$startDate.'_'.$endDate;
        return Cache::remember($key, self::CACHE_DURATION, fn () => $this->repository->getTimeEntriesByDateRange($startDate, $endDate));
    }

    public function createTimeEntry(array $data)
    {
        $row = $this->repository->createTimeEntry($data);
        $this->clearCaches($row->id ?? null, $row->task_id ?? null, $row->user_id ?? null);
        return $row;
    }

    public function updateTimeEntry($id, array $data)
    {
        $row = $this->repository->updateTimeEntry($id, $data);
        $this->clearCaches($id, $row->task_id ?? null, $row->user_id ?? null);
        return $row;
    }

    public function deleteTimeEntry($id)
    {
        $row = $this->repository->getTimeEntryById($id);
        $result = $this->repository->deleteTimeEntry($id);
        $this->clearCaches($id, $row->task_id ?? null, $row->user_id ?? null);
        return $result;
    }

    public function getTotalHoursByTask($taskId)
    {
        return Cache::remember(self::CACHE_TOTAL_TASK_PREFIX.$taskId, self::CACHE_DURATION, fn () => $this->repository->getTotalHoursByTask($taskId));
    }

    public function getTotalHoursByUser($userId)
    {
        return Cache::remember(self::CACHE_TOTAL_USER_PREFIX.$userId, self::CACHE_DURATION, fn () => $this->repository->getTotalHoursByUser($userId));
    }

    protected function clearCaches($id = null, $taskId = null, $userId = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id) Cache::forget(self::CACHE_ID_PREFIX.$id);
        if ($taskId) Cache::forget(self::CACHE_TASK_PREFIX.$taskId);
        if ($userId) Cache::forget(self::CACHE_USER_PREFIX.$userId);
        if ($taskId) Cache::forget(self::CACHE_TOTAL_TASK_PREFIX.$taskId);
        if ($userId) Cache::forget(self::CACHE_TOTAL_USER_PREFIX.$userId);
    }
}

