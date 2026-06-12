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
        $routeTaskId = $this->routeEntityId($request->route('task'));
        $actorId = $request->query('actor_id');
        $entityType = $request->query('entity_type');
        $entityId = $request->query('entity_id', $routeTaskId);
        $start = $request->query('start_date');
        $end = $request->query('end_date');
        $include = $request->query('include'); // e.g., "task,changer"

        // Laporan range tanggal murni (tanpa entity/actor) tetap non-paginated
        if ($start && $end && !$actorId && !$entityType && !$entityId && !$routeTaskId) {
            $items = $this->service->getHistoriesByDateRange($start, $end);

            if ($include) {
                $map = [
                    'task' => 'task',
                    'changer' => 'changer',
                ];
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

            return StatusHistoryResource::collection($items);
        }

        // Path default: pagination dengan filter sederhana
        $filters = [];

        if ($routeTaskId !== null) {
            $filters['entity_type'] = 'Task';
            $filters['entity_id'] = $routeTaskId;
        } elseif ($entityType && $entityId) {
            $filters['entity_type'] = $entityType;
            $filters['entity_id'] = $entityId;
        } elseif ($entityType) {
            $filters['entity_type'] = $entityType;
        }

        if ($actorId) {
            $filters['actor_id'] = $actorId;
        }

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }

        $items = $this->service->paginateHistories($filters, $perPage);

        if ($include) {
            $map = [
                'task' => 'task',
                'changer' => 'changer',
            ];
            $rels = collect(explode(',', $include))
                ->map(fn ($s) => trim($s))
                ->filter()
                ->map(fn ($key) => $map[$key] ?? null)
                ->filter()
                ->values()
                ->all();
            if (!empty($rels)) {
                $items->getCollection()->load($rels);
            }
        }

        return StatusHistoryResource::collection($items);
    }

    protected function routeEntityId(mixed $value): int|string|null
    {
        if (is_object($value) && isset($value->id)) {
            return $value->id;
        }

        if (is_scalar($value) && $value !== '') {
            return $value;
        }

        return null;
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
