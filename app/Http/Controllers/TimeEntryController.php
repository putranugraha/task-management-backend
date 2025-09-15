<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\TimeEntryStoreRequest;
use App\Http\Requests\TimeEntryUpdateRequest;
use App\Http\Resources\TimeEntryResource;
use App\Services\Contracts\TimeEntryServiceInterface;

class TimeEntryController extends Controller
{
    protected TimeEntryServiceInterface $service;

    public function __construct(TimeEntryServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $routeTask = $request->route('task');
        $routeUser = $request->route('user');
        $taskId = $request->query('task_id', is_scalar($routeTask) ? $routeTask : null);
        $userId = $request->query('user_id', is_scalar($routeUser) ? $routeUser : null);
        $start = $request->query('start_date');
        $end = $request->query('end_date');
        $include = $request->query('include'); // task,user

        if (($request->routeIs('tasks.*') && $taskId) || ($taskId && !$userId)) {
            $items = $this->service->getTimeEntriesByTask($taskId);
        } elseif (($request->routeIs('users.*') && $userId) || ($userId && !$taskId)) {
            $items = $this->service->getTimeEntriesByUser($userId);
        } elseif ($taskId && $userId) {
            $items = $this->service->getTimeEntriesByTaskAndUser($taskId, $userId);
        } elseif ($start && $end) {
            $items = $this->service->getTimeEntriesByDateRange($start, $end);
        } else {
            $items = $this->service->getAllTimeEntries();
        }

        if ($include) {
            $map = [ 'task' => 'task', 'user' => 'user' ];
            $rels = collect(explode(',', $include))
                ->map(fn($s) => trim($s))
                ->filter()
                ->map(fn($key) => $map[$key] ?? null)
                ->filter()
                ->values()->all();
            if (!empty($rels) && method_exists($items, 'load')) {
                $items->load($rels);
            }
        }

        return TimeEntryResource::collection($items);
    }

    public function store(TimeEntryStoreRequest $request)
    {
        $row = $this->service->createTimeEntry($request->validated());
        if (!$row) return response()->json(['message' => 'Gagal membuat time entry (mungkin duplikat tanggal untuk user/task).'], 400);
        return new TimeEntryResource($row);
    }

    public function show(string $id)
    {
        $row = $this->service->getTimeEntryById($id);
        if (!$row) return response()->json(['message' => 'Time entry tidak ditemukan'], 404);
        $include = request()->query('include');
        if ($include) {
            $map = [ 'task' => 'task', 'user' => 'user' ];
            $rels = collect(explode(',', $include))->map(fn($s) => trim($s))->filter()->map(fn($key) => $map[$key] ?? null)->filter()->values()->all();
            if (!empty($rels)) $row->load($rels);
        }
        return new TimeEntryResource($row);
    }

    public function update(TimeEntryUpdateRequest $request, string $id)
    {
        $row = $this->service->updateTimeEntry($id, $request->validated());
        if (!$row) return response()->json(['message' => 'Time entry tidak ditemukan atau invalid'], 404);
        return new TimeEntryResource($row);
    }

    public function destroy(string $id)
    {
        $deleted = $this->service->deleteTimeEntry($id);
        if (!$deleted) return response()->json(['message' => 'Time entry tidak ditemukan'], 404);
        return response()->json(['message' => 'Time entry berhasil dihapus']);
    }

    public function totalHoursByTask(string $taskId)
    {
        $total = $this->service->getTotalHoursByTask($taskId);
        return response()->json(['task_id' => (int)$taskId, 'total_hours' => (float)$total]);
    }

    public function totalHoursByUser(string $userId)
    {
        $total = $this->service->getTotalHoursByUser($userId);
        return response()->json(['user_id' => (int)$userId, 'total_hours' => (float)$total]);
    }
}

