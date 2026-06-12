<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskCostEntryStoreRequest;
use App\Http\Resources\TaskCostEntryResource;
use App\Models\Task;
use App\Models\TaskCostEntry;
use App\Services\Contracts\TaskCostEntryServiceInterface;
use Illuminate\Http\Request;

class TaskCostEntryController extends Controller
{
    protected TaskCostEntryServiceInterface $service;

    public function __construct(TaskCostEntryServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/tasks/{task}/cost-entries
     * Optional query params:
     * - date=YYYY-MM-DD (list entries incurred_on <= date)
     * - limit=1..200
     */
    public function index(Task $task, Request $request)
    {
        $asOf = $request->query('date');
        $limit = (int) $request->query('limit', 200);
        $rows = $this->service->getCostEntriesByTask(
            $task,
            is_string($asOf) ? $asOf : null,
            $limit,
        );

        return TaskCostEntryResource::collection($rows);
    }

    /**
     * POST /api/tasks/{task}/cost-entries
     */
    public function store(Task $task, TaskCostEntryStoreRequest $request)
    {
        $row = $this->service->createCostEntry(
            $task,
            $request->validated(),
            $request->user()?->id,
        );

        if (!$row) {
            return response()->json(['message' => 'Gagal membuat cost entry'], 400);
        }

        return new TaskCostEntryResource($row);
    }

    /**
     * DELETE /api/tasks/{task}/cost-entries/{costEntry}
     */
    public function destroy(Task $task, TaskCostEntry $costEntry)
    {
        $deleted = $this->service->deleteCostEntry($task, $costEntry, request()->user()?->id);

        if (!$deleted) {
            abort(404);
        }

        return response()->json(['message' => 'Cost entry berhasil dihapus']);
    }
}
