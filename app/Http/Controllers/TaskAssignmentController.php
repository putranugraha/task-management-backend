<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\TaskAssignmentStoreRequest;
use App\Http\Requests\TaskAssignmentUpdateRequest;
use App\Http\Resources\TaskAssignmentResource;
use App\Services\Contracts\TaskAssignmentServiceInterface;

class TaskAssignmentController extends Controller
{
    protected TaskAssignmentServiceInterface $service;

    public function __construct(TaskAssignmentServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $routeTask = $request->route('task');
        $routeUser = $request->route('user');
        $taskId = $request->query('task_id', is_scalar($routeTask) ? $routeTask : null);
        $userId = $request->query('user_id', is_scalar($routeUser) ? $routeUser : null);

        if (($request->routeIs('*assignments') && $taskId) || ($taskId && !$request->routeIs('*user*'))) {
            $items = $this->service->getAssignmentsByTask($taskId);
        } elseif (($request->routeIs('users.*') && $userId) || ($userId && !$request->routeIs('*tasks*'))) {
            $items = $this->service->getAssignmentsByUser($userId);
        } else {
            $items = $this->service->getAllAssignments();
        }

        return TaskAssignmentResource::collection($items);
    }

    public function store(TaskAssignmentStoreRequest $request)
    {
        $item = $this->service->createAssignment($request->validated());
        if (!$item) return response()->json(['message' => 'Gagal membuat assignment'], 400);
        return new TaskAssignmentResource($item);
    }

    public function show(string $id)
    {
        $item = $this->service->getAssignmentById($id);
        if (!$item) return response()->json(['message' => 'Assignment tidak ditemukan'], 404);
        return new TaskAssignmentResource($item);
    }

    public function update(TaskAssignmentUpdateRequest $request, string $id)
    {
        $item = $this->service->updateAssignment($id, $request->validated());
        if (!$item) return response()->json(['message' => 'Assignment tidak ditemukan atau invalid'], 404);
        return new TaskAssignmentResource($item);
    }

    public function destroy(string $id)
    {
        $deleted = $this->service->deleteAssignment($id);
        if (!$deleted) return response()->json(['message' => 'Assignment tidak ditemukan'], 404);
        return response()->json(['message' => 'Assignment berhasil dihapus']);
    }

    public function destroyByTask(string $taskId)
    {
        $deleted = $this->service->deleteAssignmentsByTask($taskId);
        if (!$deleted) return response()->json(['message' => 'Tidak ada assignment dihapus atau task tidak ditemukan'], 404);
        return response()->json(['message' => 'Semua assignment untuk task berhasil dihapus']);
    }

    public function destroyByUser(string $userId)
    {
        $deleted = $this->service->deleteAssignmentsByUser($userId);
        if (!$deleted) return response()->json(['message' => 'Tidak ada assignment dihapus atau user tidak ditemukan'], 404);
        return response()->json(['message' => 'Semua assignment untuk user berhasil dihapus']);
    }
}

