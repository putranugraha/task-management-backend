<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\TimeEntryStoreRequest;
use App\Http\Requests\TimeEntryUpdateRequest;
use App\Http\Requests\TaskTimeEntryStoreRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\Task;
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

        // Khusus untuk laporan rentang tanggal tanpa filter lain, gunakan path non-paginated
        if ($start && $end && !$taskId && !$userId) {
            $items = $this->service->getTimeEntriesByDateRange($start, $end);

            if ($include) {
                $map = ['task' => 'task', 'user' => 'user'];
                $rels = collect(explode(',', $include))
                    ->map(fn ($s) => trim($s))
                    ->filter()
                    ->map(fn ($key) => $map[$key] ?? null)
                    ->filter()
                    ->values()
                    ->all();

                if (!empty($rels) && method_exists($items, 'load')) {
                    $items->load($rels);
                }
            }

            return TimeEntryResource::collection($items);
        }

        // Path default: pagination dengan filter sederhana (task_id, user_id, optional range)
        $filters = [
            'task_id' => $taskId,
            'user_id' => $userId,
        ];

        if ($start && $end) {
            $filters['start_date'] = $start;
            $filters['end_date'] = $end;
        }

        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }

        $items = $this->service->paginateTimeEntries($filters, $perPage);

        if ($include) {
            $map = ['task' => 'task', 'user' => 'user'];
            $rels = collect(explode(',', $include))
                ->map(fn ($s) => trim($s))
                ->filter()
                ->map(fn ($key) => $map[$key] ?? null)
                ->filter()
                ->values()
                ->all();

            $collection = $items->getCollection();
            if (!empty($rels)) {
                $collection->load($rels);
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

    /**
     * Create a time entry for a specific Task for the authenticated user.
     * Route: POST /tasks/{task}/time-entries
     */
    public function storeForTask(Task $task, TaskTimeEntryStoreRequest $request)
    {
        $userId = $request->user()?->id;
        if (!$userId) {
            return response()->json(['message' => 'User tidak terautentik'], 401);
        }

        $data = $request->validated();
        $data['task_id'] = $task->id;
        $data['user_id'] = $userId;

        $row = $this->service->createTimeEntry($data);
        if (!$row) {
            return response()->json(['message' => 'Gagal membuat time entry (mungkin duplikat tanggal untuk user/task).'], 400);
        }

        return new TimeEntryResource($row);
    }

    /**
     * Upsert time entry by (task_id, user_id, date).
     * Simplifies FE flow: repeat POST to update hours on the same day.
     */
    public function storeOrUpdate(TimeEntryStoreRequest $request)
    {
        $row = $this->service->createOrUpdate($request->validated());
        return new TimeEntryResource($row);
    }

    /**
     * Upsert time entry by (task_id, user_id, date) for a specific Task.
     * Route: POST /tasks/{task}/time-entries/upsert
     */
    public function storeOrUpdateForTask(Task $task, TaskTimeEntryStoreRequest $request)
    {
        $userId = $request->user()?->id;
        if (!$userId) {
            return response()->json(['message' => 'User tidak terautentik'], 401);
        }

        $data = $request->validated();
        $data['task_id'] = $task->id;
        $data['user_id'] = $userId;

        $row = $this->service->createOrUpdate($data);
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
