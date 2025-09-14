<?php

namespace App\Repositories\Eloquent;

use App\Models\Task;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class TaskRepository implements TaskRepositoryInterface
{
    /** @var Task */
    protected $model;

    public function __construct(Task $model)
    {
        $this->model = $model;
    }

    public function getAllTasks()
    {
        return $this->model->with('project')->get();
    }

    public function getTaskById($id)
    {
        try {
            return $this->model->with('project')->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Task with ID {$id} not found.");
            return null;
        }
    }

    public function getTasksByProject($projectId)
    {
        return $this->model->where('project_id', $projectId)->with('project')->get();
    }

    public function getTasksByStatus($status)
    {
        return $this->model->where('status', $status)->with('project')->get();
    }

    public function getTasksByPriority($priority)
    {
        return $this->model->where('priority', $priority)->with('project')->get();
    }

    public function getTasksByPlannedDateRange($startDate, $endDate)
    {
        // Jatuh tempo planned: gunakan end_planned berada di antara range
        return $this->model
            ->whereNotNull('end_planned')
            ->whereDate('end_planned', '>=', $startDate)
            ->whereDate('end_planned', '<=', $endDate)
            ->with('project')
            ->get();
    }

    public function getTasksByActualDateRange($startDate, $endDate)
    {
        // Selesai di rentang actual: gunakan end_actual berada di antara range
        return $this->model
            ->whereNotNull('end_actual')
            ->whereDate('end_actual', '>=', $startDate)
            ->whereDate('end_actual', '<=', $endDate)
            ->with('project')
            ->get();
    }

    public function createTask(array $data)
    {
        try {
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error("Failed to create task: {$e->getMessage()}");
            return null;
        }
    }

    public function updateTask($id, array $data)
    {
        $task = $this->find($id);
        if (!$task) return null;

        try {
            $task->update($data);
            return $task->fresh('project');
        } catch (\Exception $e) {
            Log::error("Failed to update task {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteTask($id)
    {
        $task = $this->find($id);
        if (!$task) return false;

        try {
            $task->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete task {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function updateTaskStatus($id, $status)
    {
        $task = $this->find($id);
        if (!$task) return null;

        $task->status = $status;
        $task->save();
        return $task->fresh('project');
    }

    public function updateTaskProgress($id, $percent)
    {
        $task = $this->find($id);
        if (!$task) return null;
        $task->percent_complete = $percent;
        $task->save();
        return $task->fresh('project');
    }

    public function completeTask($id)
    {
        $task = $this->find($id);
        if (!$task) return null;

        $task->status = 'Done';
        $task->end_actual = Carbon::now()->toDateString();
        $task->percent_complete = 100;
        $task->save();

        return $task->fresh('project');
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Task with ID {$id} not found.");
            return null;
        }
    }
}
