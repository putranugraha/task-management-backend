<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StatusHistoryStoreRequest;
use App\Http\Resources\StatusHistoryResource;
use App\Services\Contracts\StatusHistoryServiceInterface;

class StatusHistoryController extends Controller
{
    protected StatusHistoryServiceInterface $service;

    public function __construct(StatusHistoryServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $routeTask = $request->route('task');
        $actorId = $request->query('actor_id');
        $entityType = $request->query('entity_type');
        $entityId = $request->query('entity_id', is_scalar($routeTask) ? $routeTask : null);
        $start = $request->query('start_date');
        $end = $request->query('end_date');
        $include = $request->query('include'); // e.g., "task,changer"

        if ($request->routeIs('*status-histories') && $routeTask) {
            $items = $this->service->getHistoriesByEntity('Task', $routeTask);
        } elseif ($actorId) {
            $items = $this->service->getHistoriesByActor($actorId);
        } elseif ($entityType && $entityId) {
            $items = $this->service->getHistoriesByEntity($entityType, $entityId);
        } elseif ($entityType) {
            $items = $this->service->getHistoriesByEntityType($entityType);
        } elseif ($start && $end) {
            $items = $this->service->getHistoriesByDateRange($start, $end);
        } else {
            $items = $this->service->getAllHistories();
        }

        if ($include) {
            $map = [
                'task' => 'task',
                'changer' => 'changer',
            ];
            $rels = collect(explode(',', $include))
                ->map(fn($s) => trim($s))
                ->filter()
                ->map(fn($key) => $map[$key] ?? null)
                ->filter()
                ->values()
                ->all();
            if (!empty($rels) && method_exists($items, 'load')) {
                $items->load($rels);
            }
        }

        return StatusHistoryResource::collection($items);
    }

    public function store(StatusHistoryStoreRequest $request)
    {
        $row = $this->service->createHistory($request->validated());
        if (!$row) return response()->json(['message' => 'Gagal membuat histori'], 400);
        return new StatusHistoryResource($row);
    }

    public function show(string $id)
    {
        $row = $this->service->getHistoryById($id);
        $include = request()->query('include');
        if ($include && $row) {
            $map = [
                'task' => 'task',
                'changer' => 'changer',
            ];
            $rels = collect(explode(',', $include))
                ->map(fn($s) => trim($s))
                ->filter()
                ->map(fn($key) => $map[$key] ?? null)
                ->filter()
                ->values()
                ->all();
            if (!empty($rels)) {
                $row->load($rels);
            }
        }
        if (!$row) return response()->json(['message' => 'Histori tidak ditemukan'], 404);
        return new StatusHistoryResource($row);
    }

    public function destroy(string $id)
    {
        $deleted = $this->service->deleteHistory($id);
        if (!$deleted) return response()->json(['message' => 'Histori tidak ditemukan'], 404);
        return response()->json(['message' => 'Histori berhasil dihapus']);
    }

    public function destroyByEntity(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:Task',
            'entity_id' => 'required|integer',
        ]);
        $deleted = $this->service->deleteHistoriesByEntity($request->input('entity_type'), (int) $request->input('entity_id'));
        if (!$deleted) return response()->json(['message' => 'Tidak ada histori dihapus atau entity tidak didukung'], 404);
        return response()->json(['message' => 'Seluruh histori untuk entity dihapus']);
    }
}
