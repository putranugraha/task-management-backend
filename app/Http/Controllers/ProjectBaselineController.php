<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ProjectBaselineStoreRequest;
use App\Http\Requests\ProjectBaselineUpdateRequest;
use App\Http\Resources\ProjectBaselineResource;
use App\Services\Contracts\ProjectBaselineServiceInterface;

class ProjectBaselineController extends Controller
{
    protected ProjectBaselineServiceInterface $service;

    public function __construct(ProjectBaselineServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $routeProject = $request->route('project');
        $projectId = $request->query('project_id');

        if (!$projectId && $routeProject !== null) {
            if (is_object($routeProject) && method_exists($routeProject, 'getKey')) {
                $projectId = $routeProject->getKey();
            } elseif (is_scalar($routeProject)) {
                $projectId = $routeProject;
            }
        }

        if ($projectId) {
            $items = $this->service->getBaselinesByProject($projectId);
        } else {
            $items = $this->service->getAllBaselines();
        }

        return ProjectBaselineResource::collection($items);
    }

    public function store(ProjectBaselineStoreRequest $request)
    {
        $baseline = $this->service->createBaseline($request->validated());
        if (!$baseline) return response()->json(['message' => 'Gagal membuat baseline'], 400);
        $baseline->loadMissing('project');
        return new ProjectBaselineResource($baseline);
    }

    public function show(string $id)
    {
        $baseline = $this->service->getBaselineById($id);
        if (!$baseline) return response()->json(['message' => 'Baseline tidak ditemukan'], 404);
        return new ProjectBaselineResource($baseline);
    }

    public function update(ProjectBaselineUpdateRequest $request, string $id)
    {
        $baseline = $this->service->updateBaseline($id, $request->validated());
        if (!$baseline) return response()->json(['message' => 'Baseline tidak ditemukan atau invalid'], 404);
        $baseline->loadMissing('project');
        return new ProjectBaselineResource($baseline);
    }

    public function destroy(string $id)
    {
        $deleted = $this->service->deleteBaseline($id);
        if (!$deleted) return response()->json(['message' => 'Baseline tidak ditemukan'], 404);
        return response()->json(['message' => 'Baseline berhasil dihapus']);
    }

    public function latest(string $projectId)
    {
        $baseline = $this->service->getLatestBaselineByProject($projectId);
        if (!$baseline) return response()->json(['message' => 'Baseline tidak ditemukan'], 404);
        return new ProjectBaselineResource($baseline);
    }

    public function destroyByProject(string $projectId)
    {
        $deleted = $this->service->deleteBaselinesByProject($projectId);
        if (!$deleted) return response()->json(['message' => 'Tidak ada baseline dihapus atau project tidak ditemukan'], 404);
        return response()->json(['message' => 'Baseline proyek berhasil dihapus']);
    }
}

