<?php

namespace App\Repositories\Eloquent;

use App\Models\TaskDependency;
use App\Repositories\Contracts\TaskDependencyRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class TaskDependencyRepository implements TaskDependencyRepositoryInterface
{
    protected TaskDependency $model;

    public function __construct(TaskDependency $model)
    {
        $this->model = $model;
    }

    public function getAllDependencies()
    {
        return $this->model->with(['task', 'dependsOn'])->get();
    }

    public function getDependencyById($id)
    {
        try {
            return $this->model->with(['task', 'dependsOn'])->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("TaskDependency with ID {$id} not found.");
            return null;
        }
    }

    public function getDependenciesByTask($taskId)
    {
        return $this->model->where('task_id', $taskId)->with('dependsOn')->get();
    }

    public function getDependentsByTask($dependsOnTaskId)
    {
        return $this->model->where('depends_on_task_id', $dependsOnTaskId)->with('task')->get();
    }

    public function createDependency(array $data)
    {
        try {
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error("Failed to create task dependency: {$e->getMessage()}");
            return null;
        }
    }

    public function updateDependency($id, array $data)
    {
        $dep = $this->find($id);
        if (!$dep) return null;

        try {
            $dep->update($data);
            return $dep->fresh(['task', 'dependsOn']);
        } catch (\Exception $e) {
            Log::error("Failed to update task dependency {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteDependency($id)
    {
        $dep = $this->find($id);
        if (!$dep) return false;

        try {
            $dep->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete task dependency {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function deleteDependenciesByTask($taskId)
    {
        try {
            return (bool) $this->model->where('task_id', $taskId)->delete();
        } catch (\Exception $e) {
            Log::error("Failed to delete dependencies by task {$taskId}: {$e->getMessage()}");
            return false;
        }
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("TaskDependency with ID {$id} not found.");
            return null;
        }
    }
}

