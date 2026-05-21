<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\TaskBaselineStoreRequest;
use App\Http\Requests\TaskBaselineUpdateRequest;
use App\Http\Resources\TaskBaselineResource;
use App\Models\Task;
use App\Services\Contracts\TaskBaselineServiceInterface;
use Illuminate\Support\Facades\Auth;
use App\Support\TaskHistoryLogger;

class TaskBaselineController extends Controller
{
    protected TaskBaselineServiceInterface $service;

    public function __construct(TaskBaselineServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $routeBaseline = $request->route('baseline');
        $routeTask = $request->route('task');

        $baselineId = $request->query('baseline_id');
        $taskId = $request->query('task_id');

        if (!$baselineId && $routeBaseline !== null) {
            if (is_object($routeBaseline) && method_exists($routeBaseline, 'getKey')) {
                $baselineId = $routeBaseline->getKey();
            } elseif (is_scalar($routeBaseline)) {
                $baselineId = $routeBaseline;
            }
        }

        if (!$taskId && $routeTask !== null) {
            if (is_object($routeTask) && method_exists($routeTask, 'getKey')) {
                $taskId = $routeTask->getKey();
            } elseif (is_scalar($routeTask)) {
                $taskId = $routeTask;
            }
        }

        if ($baselineId) {
            $items = $this->service->getTaskBaselinesByBaseline($baselineId);
        } elseif ($taskId) {
            $items = $this->service->getTaskBaselinesByTask($taskId);
        } else {
            $items = $this->service->getAllTaskBaselines();
        }

        return TaskBaselineResource::collection($items);
    }

    public function store(TaskBaselineStoreRequest $request)
    {
        $taskBaseline = $this->service->createTaskBaseline($request->validated());
        if (!$taskBaseline) {
            return response()->json(['message' => 'Gagal membuat baseline task'], 400);
        }
        $taskBaseline->loadMissing(['baseline.project', 'task']);

        $task = $taskBaseline->task instanceof Task ? $taskBaseline->task : Task::find($taskBaseline->task_id);
        $note = 'Baseline task dibuat';
        if ($taskBaseline->baseline_id) {
            $note .= ' (baseline_id: '.$taskBaseline->baseline_id.')';
        }
        if ($taskBaseline->weight !== null) {
            $note .= ' (weight: '.$taskBaseline->weight.')';
        }
        TaskHistoryLogger::log($task, Auth::id(), $note);

        return new TaskBaselineResource($taskBaseline);
    }

    public function show(string $id)
    {
        $taskBaseline = $this->service->getTaskBaselineById($id);
        if (!$taskBaseline) {
            return response()->json(['message' => 'Baseline task tidak ditemukan'], 404);
        }
        return new TaskBaselineResource($taskBaseline);
    }

    public function update(TaskBaselineUpdateRequest $request, string $id)
    {
        $before = $this->service->getTaskBaselineById($id);
        $taskBaseline = $this->service->updateTaskBaseline($id, $request->validated());
        if (!$taskBaseline) {
            return response()->json(['message' => 'Baseline task tidak ditemukan atau invalid'], 404);
        }
        $taskBaseline->loadMissing(['baseline.project', 'task']);

        $task = $taskBaseline->task instanceof Task ? $taskBaseline->task : Task::find($taskBaseline->task_id);
        $note = 'Baseline task diperbarui';
        if ($before) {
            $parts = [];
            if (($before->start_planned_base ?? null) !== ($taskBaseline->start_planned_base ?? null)) $parts[] = 'start_planned_base';
            if (($before->end_planned_base ?? null) !== ($taskBaseline->end_planned_base ?? null)) $parts[] = 'end_planned_base';
            if ((int) ($before->duration_planned_base ?? 0) !== (int) ($taskBaseline->duration_planned_base ?? 0)) $parts[] = 'duration_planned_base';
            if ((string) ($before->weight ?? '') !== (string) ($taskBaseline->weight ?? '')) $parts[] = 'weight';
            if ((string) ($before->planned_effort_hours ?? '') !== (string) ($taskBaseline->planned_effort_hours ?? '')) $parts[] = 'planned_effort_hours';
            if (!empty($parts)) {
                $note .= ' (field: '.implode(',', $parts).')';
            }
        }
        TaskHistoryLogger::log($task, Auth::id(), $note);

        return new TaskBaselineResource($taskBaseline);
    }

    public function destroy(string $id)
    {
        $before = $this->service->getTaskBaselineById($id);
        $deleted = $this->service->deleteTaskBaseline($id);
        if (!$deleted) {
            return response()->json(['message' => 'Baseline task tidak ditemukan'], 404);
        }
        if ($before) {
            $task = $before->task instanceof Task ? $before->task : Task::find($before->task_id);
            $note = 'Baseline task dihapus';
            if ($before->baseline_id) {
                $note .= ' (baseline_id: '.$before->baseline_id.')';
            }
            TaskHistoryLogger::log($task, Auth::id(), $note);
        }
        return response()->json(['message' => 'Baseline task berhasil dihapus']);
    }

    public function destroyByBaseline(string $baselineId)
    {
        $deleted = $this->service->deleteTaskBaselinesByBaseline($baselineId);
        if (!$deleted) {
            return response()->json(['message' => 'Tidak ada baseline task dihapus atau baseline tidak ditemukan'], 404);
        }
        return response()->json(['message' => 'Semua baseline task untuk baseline ini berhasil dihapus', 'deleted' => $deleted]);
    }

    public function totalWeight(string $baselineId)
    {
        $total = $this->service->getTotalWeightByBaseline($baselineId);
        return response()->json([
            'baseline_id' => (int) $baselineId,
            'total_weight' => (float) $total,
        ]);
    }
}


