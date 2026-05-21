<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\KpiSnapshotStoreRequest;
use App\Http\Requests\KpiSnapshotUpdateRequest;
use App\Http\Resources\KpiSnapshotResource;
use App\Services\Contracts\KpiSnapshotServiceInterface;

class KpiSnapshotController extends Controller
{
    protected KpiSnapshotServiceInterface $service;

    public function __construct(KpiSnapshotServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $routeProject = $request->route('project');
        $projectId = $request->query('project_id', is_scalar($routeProject) ? $routeProject : null);
        $periodId = $request->query('period_id');

        if ($projectId && $periodId) {
            $snap = $this->service->getKpiSnapshotByProjectAndPeriod($projectId, $periodId);
            if (!$snap) return response()->json(['message' => 'KPI snapshot tidak ditemukan'], 404);
            return new KpiSnapshotResource($snap);
        }

        if ($projectId) {
            $items = $this->service->getKpiSnapshotsByProject($projectId);
        } elseif ($periodId) {
            $items = $this->service->getKpiSnapshotsByPeriod($periodId);
        } else {
            $items = $this->service->getAllKpiSnapshots();
        }

        return KpiSnapshotResource::collection($items);
    }

    public function store(KpiSnapshotStoreRequest $request)
    {
        $snap = $this->service->createKpiSnapshot($request->validated());
        if (!$snap) return response()->json(['message' => 'Gagal membuat KPI snapshot'], 400);
        return new KpiSnapshotResource($snap);
    }

    public function show(string $id)
    {
        $snap = $this->service->getKpiSnapshotById($id);
        if (!$snap) return response()->json(['message' => 'KPI snapshot tidak ditemukan'], 404);
        return new KpiSnapshotResource($snap);
    }

    public function update(KpiSnapshotUpdateRequest $request, string $id)
    {
        $snap = $this->service->updateKpiSnapshot($id, $request->validated());
        if (!$snap) return response()->json(['message' => 'KPI snapshot tidak ditemukan atau invalid'], 404);
        return new KpiSnapshotResource($snap);
    }

    public function destroy(string $id)
    {
        $deleted = $this->service->deleteKpiSnapshot($id);
        if (!$deleted) return response()->json(['message' => 'KPI snapshot tidak ditemukan'], 404);
        return response()->json(['message' => 'KPI snapshot berhasil dihapus']);
    }

    public function destroyByProject(string $projectId)
    {
        $deleted = $this->service->deleteKpiSnapshotsByProject($projectId);
        if (!$deleted) return response()->json(['message' => 'Tidak ada snapshot dihapus atau project tidak ditemukan'], 404);
        return response()->json(['message' => 'Semua KPI snapshot untuk project berhasil dihapus', 'deleted' => (int) $deleted]);
    }

    public function averageCycleTimeByProject(Request $request)
    {
        $routeProject = $request->route('project');
        $projectId = $request->query('project_id', is_scalar($routeProject) ? $routeProject : null);
        if (!$projectId) return response()->json(['message' => 'Parameter project_id diperlukan'], 422);

        $avg = $this->service->getAverageCycleTimeByProject($projectId);
        return response()->json([
            'project_id' => (int) $projectId,
            'average_cycle_time_days' => round((float) $avg, 2),
        ]);
    }

    public function generateForProject(Request $request, string $projectId)
    {
        $data = $request->validate([
            'period_date' => ['required', 'date'],
            'note' => ['sometimes', 'nullable', 'string'],
        ]);

        $snap = $this->service->generateForProjectAndDate($projectId, $data['period_date'], $data['note'] ?? null);
        if (!$snap) {
            return response()->json(['message' => 'Gagal menghasilkan KPI snapshot'], 400);
        }

        return new KpiSnapshotResource($snap);
    }
}
