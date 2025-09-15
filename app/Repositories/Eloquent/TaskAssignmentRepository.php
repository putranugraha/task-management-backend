<?php

namespace App\Repositories\Eloquent;

use App\Models\TaskAssignment;
use App\Repositories\Contracts\TaskAssignmentRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class TaskAssignmentRepository implements TaskAssignmentRepositoryInterface
{
    protected TaskAssignment $model;

    public function __construct(TaskAssignment $model)
    {
        $this->model = $model;
    }

    public function getAllAssignments()
    {
        return $this->model->with(['task', 'user'])->get();
    }

    public function getAssignmentById($id)
    {
        try {
            return $this->model->with(['task', 'user'])->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("TaskAssignment with ID {$id} not found.");
            return null;
        }
    }

    public function getAssignmentsByTask($taskId)
    {
        return $this->model->where('task_id', $taskId)->with(['task', 'user'])->get();
    }

    public function getAssignmentsByUser($userId)
    {
        return $this->model->where('user_id', $userId)->with(['task', 'user'])->get();
    }

    public function getAssignmentByTaskAndUser($taskId, $userId)
    {
        return $this->model->where('task_id', $taskId)->where('user_id', $userId)->with(['task', 'user'])->first();
    }

    public function createAssignment(array $data)
    {
        try {
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error("Failed to create task assignment: {$e->getMessage()}");
            return null;
        }
    }

    public function updateAssignment($id, array $data)
    {
        $assignment = $this->find($id);
        if (!$assignment) return null;

        try {
            $assignment->update($data);
            return $assignment->fresh(['task', 'user']);
        } catch (\Exception $e) {
            Log::error("Failed to update task assignment {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteAssignment($id)
    {
        $assignment = $this->find($id);
        if (!$assignment) return false;

        try {
            $assignment->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete task assignment {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function deleteAssignmentsByTask($taskId)
    {
        try {
            return (bool) $this->model->where('task_id', $taskId)->delete();
        } catch (\Exception $e) {
            Log::error("Failed to delete assignments by task {$taskId}: {$e->getMessage()}");
            return false;
        }
    }

    public function deleteAssignmentsByUser($userId)
    {
        try {
            return (bool) $this->model->where('user_id', $userId)->delete();
        } catch (\Exception $e) {
            Log::error("Failed to delete assignments by user {$userId}: {$e->getMessage()}");
            return false;
        }
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("TaskAssignment with ID {$id} not found.");
            return null;
        }
    }
}

