<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\TimeEntryStoreRequest;
use App\Http\Requests\TimeEntryUpdateRequest;
use App\Http\Requests\TaskTimeEntryStoreRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Services\Contracts\TimeEntryServiceInterface;
use Illuminate\Support\Carbon;
use App\Support\TaskHistoryLogger;

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
        $actorId = $request->user()?->id;
        $note = 'Time entry ditambahkan: '.$row->date.' ('.$row->hours.' jam)';
        TaskHistoryLogger::logByTaskId((int) ($row->task_id ?? 0), $actorId, $note);
        return new TimeEntryResource($row);
    }

    /**
     * Create a time entry for a specific Task for the authenticated user.
     * Route: POST /tasks/{task}/time-entries
     */
    public function startForTask(Task $task, Request $request)
    {
        $userId = $request->user()?->id;
        if (!$userId) {
            return response()->json(['message' => 'User tidak terautentik'], 401);
        }

        $date = Carbon::today()->toDateString();
        $this->service->startTaskForTimeEntry((int) $task->id, $date);

        $task->refresh();

        return response()->json([
            'message' => 'Task siap dicatat waktunya.',
            'task_id' => $task->id,
            'status' => $task->status,
            'start_actual' => optional($task->start_actual)->format('Y-m-d'),
        ]);
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

        $actorId = $request->user()?->id;
        $note = 'Time entry ditambahkan: '.$row->date.' ('.$row->hours.' jam)';
        TaskHistoryLogger::log($task, $actorId, $note);

        return new TimeEntryResource($row);
    }

    /**
     * Upsert time entry by (task_id, user_id, date).
     * Simplifies FE flow: repeat POST to update hours on the same day.
     */
public function storeOrUpdate(TimeEntryStoreRequest $request)
{
    $data = $request->validated();

    $userId = $request->user()?->id;

    if (!$userId) {
        return response()->json([
            'message' => 'User tidak terautentik'
        ], 401);
    }

    // Jangan percaya user_id dari frontend.
    $data['user_id'] = (int) $userId;

    $before = TimeEntry::where('task_id', $data['task_id'])
        ->where('user_id', $data['user_id'])
        ->where('date', $data['date'])
        ->first();

    $row = $this->service->createOrUpdate($data);

    $actorId = $request->user()?->id;
    $date = (string) ($row->date ?? $data['date']);
    $beforeHours = $before?->hours ?? null;
    $afterHours = $row->hours ?? null;

    $note = $before
        ? ('Time entry diperbarui: '.$date.' ('.$beforeHours.' -> '.$afterHours.' jam)')
        : ('Time entry ditambahkan: '.$date.' ('.$afterHours.' jam)');

    TaskHistoryLogger::logByTaskId(
        (int) ($row->task_id ?? $data['task_id']),
        $actorId,
        $note
    );

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

        $before = TimeEntry::where('task_id', $data['task_id'])
            ->where('user_id', $data['user_id'])
            ->where('date', $data['date'])
            ->first();

        $row = $this->service->createOrUpdate($data);

        $actorId = $request->user()?->id;
        $date = (string) ($row->date ?? $data['date']);
        $beforeHours = $before?->hours ?? null;
        $afterHours = $row->hours ?? null;
        $note = $before
            ? ('Time entry diperbarui: '.$date.' ('.$beforeHours.' -> '.$afterHours.' jam)')
            : ('Time entry ditambahkan: '.$date.' ('.$afterHours.' jam)');
        TaskHistoryLogger::log($task, $actorId, $note);
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
        $before = $this->service->getTimeEntryById($id);
        $row = $this->service->updateTimeEntry($id, $request->validated());
        if (!$row) return response()->json(['message' => 'Time entry tidak ditemukan atau invalid'], 404);
        $actorId = $request->user()?->id;
        $beforeHours = $before?->hours ?? null;
        $afterHours = $row->hours ?? null;
        $date = (string) ($row->date ?? ($before?->date ?? ''));
        $note = 'Time entry diperbarui'.($date !== '' ? (': '.$date) : '').' ('.$beforeHours.' -> '.$afterHours.' jam)';
        TaskHistoryLogger::logByTaskId((int) ($row->task_id ?? 0), $actorId, $note);
        return new TimeEntryResource($row);
    }

    public function destroy(string $id)
    {
        $before = $this->service->getTimeEntryById($id);
        $deleted = $this->service->deleteTimeEntry($id);
        if (!$deleted) return response()->json(['message' => 'Time entry tidak ditemukan'], 404);
        $actorId = request()->user()?->id;
        if ($before) {
            $note = 'Time entry dihapus: '.$before->date.' ('.$before->hours.' jam)';
            TaskHistoryLogger::logByTaskId((int) ($before->task_id ?? 0), $actorId, $note);
        }
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

    /**
     * Total actual hours for a project up to an as-of date (inclusive).
     *
     * Route: GET /projects/{project}/time-entries/total-hours?date=YYYY-MM-DD
     */
    public function totalHoursByProject(Project $project, Request $request)
    {
        $date = $request->query('date');
        try {
            $asOf = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid date. Use YYYY-MM-DD.'], 422);
        }

        $total = $this->service->getTotalHoursByProjectAsOf((int) $project->id, $asOf);

        return response()->json([
            'project_id' => (int) $project->id,
            'as_of' => $asOf,
            'total_hours' => (float) $total,
        ]);
    }

    /**
     * Top tasks by actual hours for a project up to an as-of date (inclusive).
     *
     * Route: GET /projects/{project}/time-entries/top-tasks?date=YYYY-MM-DD&limit=5
     */
    public function topTasksByProject(Project $project, Request $request)
    {
        $date = $request->query('date');
        try {
            $asOf = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid date. Use YYYY-MM-DD.'], 422);
        }
        $limit = (int) $request->query('limit', 5);
        if ($limit <= 0) $limit = 5;
        if ($limit > 50) $limit = 50;

        $items = $this->service->getTopTasksByHoursAsOf((int) $project->id, $asOf, $limit);

        return response()->json([
            'project_id' => (int) $project->id,
            'as_of' => $asOf,
            'limit' => $limit,
            'items' => $items,
        ]);
    }
}
