<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\TaskDependencyStoreRequest;
use App\Http\Requests\TaskDependencyUpdateRequest;
use App\Http\Resources\TaskDependencyResource;
use App\Services\Contracts\TaskDependencyServiceInterface;

class TaskDependencyController extends Controller
{
    protected TaskDependencyServiceInterface $service;

    public function __construct(TaskDependencyServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        // Support route-based task param for convenience endpoints
        $routeTask = $request->route('task');
        $taskId = $request->query('task_id', is_scalar($routeTask) ? $routeTask : null);
        $dependsOnTaskId = $request->query('depends_on_task_id', is_scalar($routeTask) ? $routeTask : null);

        if ($request->routeIs('*dependencies') && $taskId) {
            $deps = $this->service->getDependenciesByTask($taskId);
        } elseif ($request->routeIs('*dependents') && $dependsOnTaskId) {
            $deps = $this->service->getDependentsByTask($dependsOnTaskId);
        } else {
            $deps = $this->service->getAllDependencies();
        }

        return TaskDependencyResource::collection($deps);
    }

    public function store(TaskDependencyStoreRequest $request)
    {
        $dep = $this->service->createDependency($request->validated());
        if (!$dep) return response()->json(['message' => 'Gagal membuat dependency'], 400);
        return new TaskDependencyResource($dep);
    }

    public function show(string $id)
    {
        $dep = $this->service->getDependencyById($id);
        if (!$dep) return response()->json(['message' => 'Dependency tidak ditemukan'], 404);
        return new TaskDependencyResource($dep);
    }

    public function update(TaskDependencyUpdateRequest $request, string $id)
    {
        $dep = $this->service->updateDependency($id, $request->validated());
        if (!$dep) return response()->json(['message' => 'Dependency tidak ditemukan atau invalid'], 404);
        return new TaskDependencyResource($dep);
    }

    public function destroy(string $id)
    {
        $deleted = $this->service->deleteDependency($id);
        if (!$deleted) return response()->json(['message' => 'Dependency tidak ditemukan'], 404);
        return response()->json(['message' => 'Dependency berhasil dihapus']);
    }

    public function destroyByTask(string $taskId)
    {
        $deleted = $this->service->deleteDependenciesByTask($taskId);
        if (!$deleted) return response()->json(['message' => 'Tidak ada dependency dihapus atau task tidak ditemukan'], 404);
        return response()->json(['message' => 'Semua dependency untuk task berhasil dihapus']);
    }
}
