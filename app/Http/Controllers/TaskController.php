<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\TaskResource;
use App\Http\Requests\TaskStoreRequest;
use App\Http\Requests\TaskUpdateRequest;
use App\Models\Milestone;
use App\Services\Contracts\TaskServiceInterface;

class TaskController extends Controller
{
    protected TaskServiceInterface $service;

    public function __construct(TaskServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        // Optional filters
        $milestoneId = $request->query('milestone_id');
        $projectId = $request->query('project_id');
        $status = $request->query('status');
        $priority = $request->query('priority');
        $startPlanned = $request->query('start_planned');
        $endPlanned = $request->query('end_planned');
        $startActual = $request->query('start_actual');
        $endActual = $request->query('end_actual');
        $dependsOnTaskId = $request->query('depends_on_task_id');
        $include = $request->query('include'); // e.g., "dependencies,dependents,project"

        if ($milestoneId) {
            $tasks = $this->service->getTasksByMilestone($milestoneId);
        } elseif ($projectId) {
            $tasks = $this->service->getTasksByProject($projectId);
        } elseif ($status) {
            $tasks = $this->service->getTasksByStatus($status);
        } elseif ($priority) {
            $tasks = $this->service->getTasksByPriority($priority);
        } elseif ($dependsOnTaskId) {
            $tasks = $this->service->getTasksByDependsOnTask($dependsOnTaskId);
        } elseif ($startPlanned && $endPlanned) {
            $tasks = $this->service->getTasksByPlannedDateRange($startPlanned, $endPlanned);
        } elseif ($startActual && $endActual) {
            $tasks = $this->service->getTasksByActualDateRange($startActual, $endActual);
        } else {
            $tasks = $this->service->getAllTasks();
        }

        // Eager-load optional includes for clarity in responses
        if ($include) {
            $map = [
                'project' => 'project',
                'dependencies' => 'dependencies.dependsOn',
                'dependents' => 'dependents.task',
            ];
            $rels = collect(explode(',', $include))
                ->map(fn($s) => trim($s))
                ->filter()
                ->map(fn($key) => $map[$key] ?? null)
                ->filter()
                ->values()
                ->all();
            if (!empty($rels) && method_exists($tasks, 'load')) {
                $tasks->load($rels);
            }
        }

        return TaskResource::collection($tasks);
    }

    public function store(TaskStoreRequest $request)
    {
        $task = $this->service->createTask($request->validated());
        if (!$task) return response()->json(['message' => 'Gagal membuat task'], 400);
        return new TaskResource($task);
    }

    /**
     * Nested: GET /milestones/{milestone}/tasks
     */
    public function indexByMilestone(Milestone $milestone)
    {
        $tasks = $this->service->getTasksByMilestone($milestone->id);
        return TaskResource::collection($tasks);
    }

    /**
     * Nested: POST /milestones/{milestone}/tasks
     */
    public function storeForMilestone(Milestone $milestone, TaskStoreRequest $request)
    {
        $data = $request->validated();
        $data['project_id'] = $milestone->project_id; // enforce same project
        $data['milestone_id'] = $milestone->id;
        $task = $this->service->createTask($data);
        if (!$task) return response()->json(['message' => 'Gagal membuat task'], 400);
        return new TaskResource($task);
    }

    public function show(string $id)
    {
        $task = $this->service->getTaskById($id);
        if (!$task) return response()->json(['message' => 'Task tidak ditemukan'], 404);
        return new TaskResource($task);
    }

    public function update(TaskUpdateRequest $request, string $id)
    {
        $task = $this->service->updateTask($id, $request->validated());
        if (!$task) return response()->json(['message' => 'Task tidak ditemukan'], 404);
        return new TaskResource($task);
    }

    public function destroy(string $id)
    {
        $deleted = $this->service->deleteTask($id);
        if (!$deleted) return response()->json(['message' => 'Task tidak ditemukan'], 404);
        return response()->json(['message' => 'Task berhasil dihapus']);
    }

    public function updateStatus(string $id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:To Do,In Progress,Done,On Hold,Cancelled',
        ]);
        $task = $this->service->updateTaskStatus($id, $request->input('status'));
        if (!$task) return response()->json(['message' => 'Gagal update status atau task tidak ditemukan'], 400);
        return new TaskResource($task);
    }

    public function updateProgress(string $id, Request $request)
    {
        $request->validate([
            'percent' => 'required|integer|min:0|max:100',
        ]);
        $task = $this->service->updateTaskProgress($id, (int) $request->input('percent'));
        if (!$task) return response()->json(['message' => 'Gagal update progres atau task tidak ditemukan'], 400);
        return new TaskResource($task);
    }

    public function complete(string $id)
    {
        $task = $this->service->completeTask($id);
        if (!$task) return response()->json(['message' => 'Task tidak ditemukan'], 404);
        return new TaskResource($task);
    }
}
