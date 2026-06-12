<?php

namespace App\Repositories\Eloquent;

use App\Models\TaskCostEntry;
use App\Repositories\Contracts\TaskCostEntryRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class TaskCostEntryRepository implements TaskCostEntryRepositoryInterface
{
    protected TaskCostEntry $model;

    public function __construct(TaskCostEntry $model)
    {
        $this->model = $model;
    }

    public function getCostEntriesByTask($taskId, ?string $asOfDate = null, int $limit = 200)
    {
        $limit = max(1, min(200, $limit));

        $query = $this->model
            ->where('task_id', $taskId)
            ->orderByDesc('incurred_on')
            ->orderByDesc('id');

        if ($asOfDate !== null && $asOfDate !== '') {
            $query->whereDate('incurred_on', '<=', $asOfDate);
        }

        return $query->limit($limit)->get();
    }

    public function createCostEntry(array $data)
    {
        try {
            return $this->model->create($data);
        } catch (\Throwable $e) {
            Log::error("Failed to create task cost entry: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteCostEntry($id): bool
    {
        try {
            $row = $this->model->findOrFail($id);
            $row->delete();

            return true;
        } catch (ModelNotFoundException) {
            Log::error("TaskCostEntry with ID {$id} not found.");
            return false;
        } catch (\Throwable $e) {
            Log::error("Failed to delete task cost entry {$id}: {$e->getMessage()}");
            return false;
        }
    }
}
