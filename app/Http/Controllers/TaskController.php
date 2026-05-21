<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\TaskResource;
use App\Http\Requests\TaskStoreRequest;
use App\Http\Requests\TaskUpdateRequest;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\TaskProgressEntry;
use App\Services\Contracts\TaskServiceInterface;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    protected TaskServiceInterface $service;

    public function __construct(TaskServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        // Special filters that tidak cocok untuk pagination biasa
        $startPlanned = $request->query('start_planned');
        $endPlanned = $request->query('end_planned');
        $startActual = $request->query('start_actual');
        $endActual = $request->query('end_actual');
        $dependsOnTaskId = $request->query('depends_on_task_id');

        $include = $request->query('include'); // e.g., "dependencies,dependents,assignments"
        $search = $request->query('search');

        // Jika pakai filter tanggal atau depends_on_task_id, gunakan path lama (non-paginated)
        if ($dependsOnTaskId || ($startPlanned && $endPlanned) || ($startActual && $endActual)) {
            if ($dependsOnTaskId) {
                $tasks = $this->service->getTasksByDependsOnTask($dependsOnTaskId);
            } elseif ($startPlanned && $endPlanned) {
                $tasks = $this->service->getTasksByPlannedDateRange($startPlanned, $endPlanned);
            } else {
                $tasks = $this->service->getTasksByActualDateRange($startActual, $endActual);
            }

            if ($include && method_exists($tasks, 'load')) {
                $map = [
                    'project' => 'project',
                    'milestone' => 'milestone',
                    'dependencies' => 'dependencies.dependsOn',
                    'dependents' => 'dependents.task',
                    'assignments' => 'assignments.user',
                ];
                $rels = collect(explode(',', $include))
                    ->map(fn ($s) => trim($s))
                    ->filter()
                    ->map(fn ($key) => $map[$key] ?? null)
                    ->filter()
                    ->values()
                    ->all();

                if (!empty($rels)) {
                    $tasks->load($rels);
                }
            }

            return TaskResource::collection($tasks);
        }

        // Pagination path dengan filter sederhana
        $filters = [
            'milestone_id' => $request->query('milestone_id'),
            'project_id' => $request->query('project_id'),
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
            'search' => $search,
        ];

        // Hanya kirim filter yang terisi ke service
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }

        $tasks = $this->service->paginateTasks($filters, $perPage);

        // Eager-load optional includes untuk relasi berat
        if ($include) {
            $map = [
                'project' => 'project',
                'milestone' => 'milestone',
                'dependencies' => 'dependencies.dependsOn',
                'dependents' => 'dependents.task',
                'assignments' => 'assignments.user',
            ];
            $rels = collect(explode(',', $include))
                ->map(fn ($s) => trim($s))
                ->filter()
                ->map(fn ($key) => $map[$key] ?? null)
                ->filter()
                ->values()
                ->all();

            if (!empty($rels)) {
                $tasks->getCollection()->load($rels);
            }
        }

        return TaskResource::collection($tasks);
    }

    /**
     * Statistik ringkas tasks untuk dashboard cards.
     *
     * Menghormati filter sederhana (project_id, milestone_id, status, priority, search)
     * namun tidak terikat pagination.
     */
    public function stats(Request $request)
    {
        $filters = [
            'project_id' => $request->query('project_id'),
            'milestone_id' => $request->query('milestone_id'),
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
            'search' => $request->query('search'),
        ];

        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        $stats = $this->service->getTaskStats($filters);

        return response()->json($stats);
    }

    public function archived(Request $request)
    {
        $filters = $this->taskFiltersFromRequest($request);

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }

        $tasks = $this->service->getArchivedTasks($filters, $perPage);

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

    /**
     * Nested: GET /projects/{project}/tasks
     */
    public function indexByProject(Project $project, Request $request)
    {
        $tasks = $this->service->getTasksByProject($project->id);

        // Optional include handling, mirroring index()
        $include = $request->query('include'); // e.g., "dependencies,dependents,project,milestone"
        if ($include && method_exists($tasks, 'load')) {
            $map = [
                'project' => 'project',
                'milestone' => 'milestone',
                'dependencies' => 'dependencies.dependsOn',
                'dependents' => 'dependents.task',
                'assignments' => 'assignments.user',
            ];
            $rels = collect(explode(',', $include))
                ->map(fn($s) => trim($s))
                ->filter()
                ->map(fn($key) => $map[$key] ?? null)
                ->filter()
                ->values()
                ->all();
            if (!empty($rels)) {
                $tasks->load($rels);
            }
        }

        return TaskResource::collection($tasks);
    }

    /**
     * Nested: POST /projects/{project}/tasks (optional convenience)
     */
    public function storeForProject(Project $project, TaskStoreRequest $request)
    {
        $data = $request->validated();
        $data['project_id'] = $project->id;
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
        return response()->json(['message' => 'Task berhasil di-archive']);
    }

    public function restore(string $id)
    {
        $task = $this->service->restoreTask($id);
        if (!$task) return response()->json(['message' => 'Task archive tidak ditemukan'], 404);
        return new TaskResource($task);
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
            'progress_date' => 'nullable|date_format:Y-m-d',
        ]);
        $task = $this->service->updateTaskProgress($id, (int) $request->input('percent'));
        if (!$task) return response()->json(['message' => 'Gagal update progres atau task tidak ditemukan'], 400);

        $progressDate = $request->input('progress_date');
        $wroteProgressDate = null;
        if (is_string($progressDate) && $progressDate !== '') {
            // Debug/testing support: write a progress entry for an arbitrary date.
            // Guarded by local env (or APP_DEBUG) so production behavior is unchanged.
            $allowDebug = app()->environment('local') || config('app.debug');
            if ($allowDebug) {
                TaskProgressEntry::updateOrCreate(
                    ['task_id' => (int) $task->id, 'progress_date' => $progressDate],
                    ['percent_complete' => (int) $task->percent_complete, 'changed_by' => Auth::id()]
                );
                $wroteProgressDate = $progressDate;
            }
        }

        $res = (new TaskResource($task))->response();
        if (app()->environment('local') || config('app.debug')) {
            $res->headers->set('X-Debug-Progress-Date-Received', is_string($progressDate) ? $progressDate : '');
            $res->headers->set('X-Debug-Progress-Date-Written', is_string($wroteProgressDate) ? $wroteProgressDate : '');
            $res->headers->set('X-Debug-Env', (string) config('app.env'));
        }
        return $res;
    }

    public function complete(string $id)
    {
        $request = request();
        $request->validate([
            'progress_date' => 'nullable|date_format:Y-m-d',
        ]);
        $task = $this->service->completeTask($id);
        if (!$task) return response()->json(['message' => 'Task tidak ditemukan'], 404);

        $progressDate = $request->input('progress_date');
        $wroteProgressDate = null;
        if (is_string($progressDate) && $progressDate !== '') {
            $allowDebug = app()->environment('local') || config('app.debug');
            if ($allowDebug) {
                TaskProgressEntry::updateOrCreate(
                    ['task_id' => (int) $task->id, 'progress_date' => $progressDate],
                    ['percent_complete' => 100, 'changed_by' => Auth::id()]
                );
                $wroteProgressDate = $progressDate;
            }
        }

        $res = (new TaskResource($task))->response();
        if (app()->environment('local') || config('app.debug')) {
            $res->headers->set('X-Debug-Progress-Date-Received', is_string($progressDate) ? $progressDate : '');
            $res->headers->set('X-Debug-Progress-Date-Written', is_string($wroteProgressDate) ? $wroteProgressDate : '');
            $res->headers->set('X-Debug-Env', (string) config('app.env'));
        }
        return $res;
    }

    protected function taskFiltersFromRequest(Request $request): array
    {
        $filters = [
            'project_id' => $request->query('project_id'),
            'milestone_id' => $request->query('milestone_id'),
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
            'search' => $request->query('search'),
        ];

        return array_filter($filters, fn($value) => $value !== null && $value !== '');
    }
}
