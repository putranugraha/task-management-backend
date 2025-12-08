<?php

namespace App\Repositories\Eloquent;

use App\Models\TimeEntry;
use App\Repositories\Contracts\TimeEntryRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class TimeEntryRepository implements TimeEntryRepositoryInterface
{
    protected TimeEntry $model;

    public function __construct(TimeEntry $model)
    {
        $this->model = $model;
    }

    public function getAllTimeEntries()
    {
        return $this->model->latest('date')->latest('id')->get();
    }

    public function getTimeEntryById($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("TimeEntry with ID {$id} not found.");
            return null;
        }
    }

    public function getTimeEntriesByTask($taskId)
    {
        return $this->model->where('task_id', $taskId)->orderBy('date','desc')->orderBy('id','desc')->get();
    }

    public function getTimeEntriesByUser($userId)
    {
        return $this->model->where('user_id', $userId)->orderBy('date','desc')->orderBy('id','desc')->get();
    }

    public function getTimeEntriesByTaskAndUser($taskId, $userId)
    {
        return $this->model->where('task_id', $taskId)->where('user_id', $userId)->orderBy('date','desc')->orderBy('id','desc')->get();
    }

    public function getTimeEntriesByDateRange($startDate, $endDate)
    {
        return $this->model
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date','desc')
            ->orderBy('id','desc')
            ->get();
    }

    public function createTimeEntry(array $data)
    {
        try {
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error("Failed to create time entry: {$e->getMessage()}");
            return null;
        }
    }

    public function updateTimeEntry($id, array $data)
    {
        $row = $this->find($id);
        if (!$row) return null;
        try {
            $row->update($data);
            return $row;
        } catch (\Exception $e) {
            Log::error("Failed to update time entry {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteTimeEntry($id)
    {
        $row = $this->find($id);
        if (!$row) return false;
        try {
            $row->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete time entry {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function getTotalHoursByTask($taskId)
    {
        return (float) $this->model->where('task_id', $taskId)->sum('hours');
    }

    public function getTotalHoursByUser($userId)
    {
        return (float) $this->model->where('user_id', $userId)->sum('hours');
    }

    public function paginateTimeEntries(array $filters = [], int $perPage = 20)
    {
        $query = $this->model
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        if (isset($filters['task_id'])) {
            $query->where('task_id', $filters['task_id']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('date', '<=', $filters['end_date']);
        }

        return $query->paginate($perPage);
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("TimeEntry with ID {$id} not found.");
            return null;
        }
    }
}
