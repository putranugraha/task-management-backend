<?php

namespace App\Repositories\Eloquent;

use App\Models\TaskBaseline;
use App\Repositories\Contracts\TaskBaselineRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class TaskBaselineRepository implements TaskBaselineRepositoryInterface
{
    /** @var TaskBaseline */
    protected $model;

    public function __construct(TaskBaseline $model)
    {
        $this->model = $model;
    }

    public function getAllTaskBaselines()
    {
        return $this->model
            ->with(['baseline.project', 'task'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function getTaskBaselineById($id)
    {
        try {
            return $this->model->with(['baseline.project', 'task'])->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Task baseline with ID {$id} not found.");
            return null;
        }
    }

    public function getTaskBaselinesByBaseline($baselineId)
    {
        return $this->model
            ->where('baseline_id', $baselineId)
            ->with(['baseline.project', 'task'])
            ->orderBy('task_id')
            ->get();
    }

    public function getTaskBaselinesByTask($taskId)
    {
        return $this->model
            ->where('task_id', $taskId)
            ->with(['baseline.project', 'task'])
            ->orderByDesc('baseline_id')
            ->get();
    }

    public function createTaskBaseline(array $data)
    {
        try {
            $taskBaseline = $this->model->create($data);
            return $taskBaseline->fresh(['baseline.project', 'task']);
        } catch (\Exception $e) {
            Log::error("Failed to create task baseline: {$e->getMessage()}");
            return null;
        }
    }

    public function updateTaskBaseline($id, array $data)
    {
        $taskBaseline = $this->find($id);
        if (!$taskBaseline) {
            return null;
        }

        try {
            $taskBaseline->update($data);
            return $taskBaseline->fresh(['baseline.project', 'task']);
        } catch (\Exception $e) {
            Log::error("Failed to update task baseline {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteTaskBaseline($id)
    {
        $taskBaseline = $this->find($id);
        if (!$taskBaseline) {
            return false;
        }

        try {
            $taskBaseline->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete task baseline {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function deleteTaskBaselinesByBaseline($baselineId)
    {
        try {
            return $this->model->where('baseline_id', $baselineId)->delete();
        } catch (\Exception $e) {
            Log::error("Failed to delete task baselines for baseline {$baselineId}: {$e->getMessage()}");
            return false;
        }
    }

    public function getTotalWeightByBaseline($baselineId)
    {
        return (float) $this->model->where('baseline_id', $baselineId)->sum('weight');
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Task baseline with ID {$id} not found.");
            return null;
        }
    }
}






