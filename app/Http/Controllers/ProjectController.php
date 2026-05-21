<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\ProjectResource;
use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Services\Contracts\ProjectServiceInterface;

class ProjectController extends Controller
{
    protected ProjectServiceInterface $service;

    public function __construct(ProjectServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        // Optional filters
        $status = $request->query('status');
        $divisionId = $request->query('division_id');
        $start = $request->query('start');
        $end = $request->query('end');
        $name = $request->query('name');
        $client = $request->query('client_name');
        $search = $request->query('search');

        // 1) Cari berdasarkan nama (tetap non-paginated, hasil maksimal 1)
        if ($name) {
            $project = $this->service->getProjectByName($name);
            if (!$project) {
                return response()->json(['message' => 'Project tidak ditemukan'], 404);
            }
            return ProjectResource::collection(collect([$project]));
        }

        // 2) Filter berdasarkan rentang tanggal planned (tetap non-paginated)
        if ($start && $end) {
            $projects = $this->service->getProjectsByDateRange($start, $end);
            return ProjectResource::collection($projects);
        }

        // 3) Path baru: pagination dengan filter sederhana
        $filters = [
            'status' => $status,
            // division_id di query = division_owner_id di database
            'division_owner_id' => $divisionId,
            'client_name' => $client,
            'search' => $search,
        ];

        // Buang filter kosong/null
        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }

        $projects = $this->service->paginateProjects($filters, $perPage);

        return response()->json([
            'data' => $projects->getCollection()->map(fn ($project) => $this->projectPayload($project))->values(),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'from' => $projects->firstItem(),
                'to' => $projects->lastItem(),
            ],
        ]);
    }

    /**
     * Statistik ringkas proyek untuk dashboard cards.
     *
     * Menghormati filter sederhana yang sama dengan index (status, division_id, client_name, search)
     * namun tidak terikat pada pagination (selalu menghitung dari seluruh hasil filter).
     */
    public function stats(Request $request)
    {
        $status = $request->query('status');
        $divisionId = $request->query('division_id');
        $client = $request->query('client_name');
        $search = $request->query('search');

        $filters = [
            'status' => $status,
            'division_owner_id' => $divisionId,
            'client_name' => $client,
            'search' => $search,
        ];

        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        $stats = $this->service->getProjectStats($filters);

        return response()->json($stats);
    }

    public function archived(Request $request)
    {
        $filters = $this->projectFiltersFromRequest($request);
        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }

        $projects = $this->service->getArchivedProjects($filters, $perPage);

        return response()->json([
            'data' => $projects->getCollection()->map(fn ($project) => $this->projectPayload($project))->values(),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'from' => $projects->firstItem(),
                'to' => $projects->lastItem(),
            ],
        ]);
    }

    public function byName(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
        ]);
        $project = $this->service->getProjectByName($validated['name']);
        if (!$project) {
            return response()->json(['message' => 'Project tidak ditemukan'], 404);
        }
        return new ProjectResource($project);
    }

    public function byClient(Request $request)
    {
        $validated = $request->validate([
            'client_name' => 'required|string',
        ]);
        $projects = $this->service->getProjectByClient($validated['client_name']);
        return ProjectResource::collection($projects);
    }

    public function byDivision(Request $request)
    {
        $validated = $request->validate([
            'division_id' => 'required|integer|exists:users,id',
        ]);
        $projects = $this->service->getProjectsByDivision($validated['division_id']);
        return ProjectResource::collection($projects);
    }

    public function byDateRange(Request $request)
    {
        $validated = $request->validate([
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);
        $projects = $this->service->getProjectsByDateRange($validated['start'], $validated['end']);
        return ProjectResource::collection($projects);
    }

    public function store(ProjectStoreRequest $request)
    {
        $project = $this->service->createProject($request->validated());
        if (!$project) {
            return response()->json(['message' => 'Gagal membuat project'], 400);
        }
        return new ProjectResource($project);
    }

    public function show(string $id)
    {
        $project = $this->service->getProjectById($id);
        if (!$project) {
            return response()->json(['message' => 'Project tidak ditemukan'], 404);
        }
        return new ProjectResource($project);
    }

    public function update(ProjectUpdateRequest $request, string $id)
    {
        $project = $this->service->updateProject($id, $request->validated());
        if (!$project) {
            return response()->json(['message' => 'Project tidak ditemukan'], 404);
        }
        return new ProjectResource($project);
    }

    public function destroy(string $id)
    {
        $deleted = $this->service->deleteProject($id);
        if (!$deleted) {
            return response()->json(['message' => 'Project tidak ditemukan'], 404);
        }
        return response()->json(['message' => 'Project berhasil di-archive']);
    }

    public function restore(string $id)
    {
        $project = $this->service->restoreProject($id);
        if (!$project) {
            return response()->json(['message' => 'Project archive tidak ditemukan'], 404);
        }
        return new ProjectResource($project);
    }

    public function forceDelete(string $id)
    {
        $deleted = $this->service->forceDeleteArchivedProject($id);
        if (!$deleted) {
            return response()->json(['message' => 'Project archive tidak ditemukan atau gagal dihapus permanen'], 404);
        }

        return response()->json([
            'message' => 'Project berhasil dihapus permanen beserta milestone, task, progress, biaya, baseline, komentar, dan attachment terkait.',
        ]);
    }

    public function updateStatus(string $id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:Planned,In Progress,Completed,On Hold,Cancelled',
        ]);
        $project = $this->service->updateProjectStatus($id, $request->input('status'));
        if (!$project) {
            return response()->json(['message' => 'Gagal update status atau project tidak ditemukan'], 400);
        }
        return new ProjectResource($project);
    }

    protected function projectFiltersFromRequest(Request $request): array
    {
        $filters = [
            'status' => $request->query('status'),
            'division_owner_id' => $request->query('division_id'),
            'client_name' => $request->query('client_name'),
            'search' => $request->query('search'),
        ];

        return array_filter($filters, fn ($value) => $value !== null && $value !== '');
    }

    protected function projectPayload($project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'client_name' => $project->client_name,
            'value_amount' => (float) $project->value_amount,
            'division_owner_id' => $project->division_owner_id,
            'division_owner' => $project->divisionOwner
                ? [
                    'id' => $project->divisionOwner->id,
                    'name' => $project->divisionOwner->name,
                    'email' => $project->divisionOwner->email,
                ]
                : null,
            'start_planned' => optional($project->start_planned)->format('Y-m-d'),
            'end_planned' => optional($project->end_planned)->format('Y-m-d'),
            'status' => $project->status,
            'created_at' => optional($project->created_at)->toDateTimeString(),
            'deleted_at' => optional($project->deleted_at)->toDateTimeString(),
        ];
    }
}
