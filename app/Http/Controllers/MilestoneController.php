<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Http\Requests\MilestoneStoreRequest;
use App\Http\Requests\MilestoneUpdateRequest;
use App\Http\Resources\MilestoneResource;
use App\Services\Contracts\MilestoneServiceInterface;

class MilestoneController extends Controller
{
    protected MilestoneServiceInterface $service;

    public function __construct(MilestoneServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $projectId = $request->query('project_id');
        $status = $request->query('status');
        $start = $request->query('start');
        $end = $request->query('end');
        $search = $request->query('search');

        // 1) Laporan rentang tanggal (tanpa pagination) untuk kasus khusus
        if ($start && $end && !$projectId && !$status) {
            $milestones = $this->service->getMilestonesByDateRange($start, $end);
            return MilestoneResource::collection($milestones);
        }

        // 2) Path default: pagination dengan filter sederhana
        $filters = [
            'project_id' => $projectId,
            'status' => $status,
            'search' => $search,
        ];

        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }

        $milestones = $this->service->paginateMilestones($filters, $perPage);

        return MilestoneResource::collection($milestones);
    }

    /**
     * Statistik ringkas milestones untuk dashboard cards.
     *
     * Menghormati filter sederhana (project_id, status, search) namun tidak terikat pagination.
     */
    public function stats(Request $request)
    {
        $projectId = $request->query('project_id');
        $status = $request->query('status');
        $search = $request->query('search');

        $filters = [
            'project_id' => $projectId,
            'status' => $status,
            'search' => $search,
        ];

        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        $stats = $this->service->getMilestoneStats($filters);

        return response()->json($stats);
    }

    public function archived(Request $request)
    {
        $filters = $this->milestoneFiltersFromRequest($request);

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }

        $milestones = $this->service->getArchivedMilestones($filters, $perPage);

        return MilestoneResource::collection($milestones);
    }

    public function store(MilestoneStoreRequest $request)
    {
        $ms = $this->service->createMilestone($request->validated());
        if (!$ms) return response()->json(['message' => 'Gagal membuat milestone'], 400);
        return new MilestoneResource($ms);
    }

    /**
     * Nested: GET /projects/{project}/milestones
     */
    public function indexByProject(Project $project)
    {
        $milestones = $this->service->getMilestonesByProject($project->id);
        return MilestoneResource::collection($milestones);
    }

    /**
     * Nested: POST /projects/{project}/milestones
     */
    public function storeForProject(Project $project, MilestoneStoreRequest $request)
    {
        $data = array_merge($request->validated(), ['project_id' => $project->id]);
        $ms = $this->service->createMilestone($data);
        if (!$ms) return response()->json(['message' => 'Gagal membuat milestone'], 400);
        return new MilestoneResource($ms);
    }

    public function show(string $id)
    {
        $ms = $this->service->getMilestoneById($id);
        if (!$ms) return response()->json(['message' => 'Milestone tidak ditemukan'], 404);
        return new MilestoneResource($ms);
    }

    public function update(MilestoneUpdateRequest $request, string $id)
    {
        $ms = $this->service->updateMilestone($id, $request->validated());
        if (!$ms) return response()->json(['message' => 'Milestone tidak ditemukan'], 404);
        return new MilestoneResource($ms);
    }

    public function destroy(string $id)
    {
        $deleted = $this->service->deleteMilestone($id);
        if (!$deleted) return response()->json(['message' => 'Milestone tidak ditemukan'], 404);
        return response()->json(['message' => 'Milestone berhasil di-archive']);
    }

    public function restore(string $id)
    {
        $ms = $this->service->restoreMilestone($id);
        if (!$ms) return response()->json(['message' => 'Milestone archive tidak ditemukan'], 404);
        return new MilestoneResource($ms);
    }

    public function updateStatus(string $id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:Planned,In Progress,Completed,Overdue,On Hold',
        ]);
        $ms = $this->service->updateMilestoneStatus($id, $request->input('status'));
        if (!$ms) return response()->json(['message' => 'Gagal update status atau milestone tidak ditemukan'], 400);
        return new MilestoneResource($ms);
    }

    public function complete(string $id)
    {
        $ms = $this->service->completeMilestone($id);
        if (!$ms) return response()->json(['message' => 'Milestone tidak ditemukan'], 404);
        return new MilestoneResource($ms);
    }

    protected function milestoneFiltersFromRequest(Request $request): array
    {
        $filters = [
            'project_id' => $request->query('project_id'),
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ];

        return array_filter($filters, fn ($value) => $value !== null && $value !== '');
    }
}
