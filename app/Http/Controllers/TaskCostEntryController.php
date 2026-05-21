<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskCostEntryStoreRequest;
use App\Http\Resources\TaskCostEntryResource;
use App\Models\Task;
use App\Models\TaskCostEntry;
use Illuminate\Http\Request;
use App\Support\TaskHistoryLogger;

class TaskCostEntryController extends Controller
{
    /**
     * GET /api/tasks/{task}/cost-entries
     * Optional query params:
     * - date=YYYY-MM-DD (list entries incurred_on <= date)
     * - limit=1..200
     */
    public function index(Task $task, Request $request)
    {
        $q = $task->costEntries()->newQuery();

        $asOf = $request->query('date');
        if (is_string($asOf) && $asOf !== '') {
            $q->whereDate('incurred_on', '<=', $asOf);
        }

        $limit = (int) $request->query('limit', 200);
        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;

        $rows = $q
            ->where('task_id', $task->id)
            ->orderByDesc('incurred_on')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return TaskCostEntryResource::collection($rows);
    }

    /**
     * POST /api/tasks/{task}/cost-entries
     */
    public function store(Task $task, TaskCostEntryStoreRequest $request)
    {
        $data = $request->validated();
        $data['task_id'] = $task->id;

        $row = $task->costEntries()->create($data);

        $actorId = $request->user()?->id;
        $amount = $row->amount ?? null;
        $category = $row->category ?? null;
        $date = $row->incurred_on ?? null;
        $note = 'Cost entry ditambahkan'.($date ? (': '.$date) : '');
        if ($amount !== null) {
            $note .= ' (amount: '.$amount.')';
        }
        if ($category) {
            $note .= ' (kategori: '.$category.')';
        }
        TaskHistoryLogger::log($task, $actorId, $note);

        return new TaskCostEntryResource($row);
    }

    /**
     * DELETE /api/tasks/{task}/cost-entries/{costEntry}
     */
    public function destroy(Task $task, TaskCostEntry $costEntry)
    {
        if ((int) $costEntry->task_id !== (int) $task->id) {
            abort(404);
        }

        $actorId = request()->user()?->id;
        $amount = $costEntry->amount ?? null;
        $category = $costEntry->category ?? null;
        $date = $costEntry->incurred_on ?? null;

        $costEntry->delete();

        $note = 'Cost entry dihapus'.($date ? (': '.$date) : '');
        if ($amount !== null) {
            $note .= ' (amount: '.$amount.')';
        }
        if ($category) {
            $note .= ' (kategori: '.$category.')';
        }
        TaskHistoryLogger::log($task, $actorId, $note);

        return response()->json(['message' => 'Cost entry berhasil dihapus']);
    }
}
