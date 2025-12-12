<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class ProjectRepository implements ProjectRepositoryInterface
{
    /** @var Project */
    protected $model;

    public function __construct(Project $model)
    {
        $this->model = $model;
    }

    public function getAllProjects()
    {
        return $this->model->with(['divisionOwner'])->get();
    }

    public function getProjectById($id)
    {
        try {
            return $this->model->with(['divisionOwner'])->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Project with ID {$id} not found.");
            return null;
        }
    }

    public function getProjectByName($name)
    {
        return $this->model->where('name', $name)->with(['divisionOwner'])->first();
    }

    public function getProjectByClient($clientName)
    {
        return $this->model->where('client_name', $clientName)->with(['divisionOwner'])->get();
    }

    public function getProjectsByDivision($divisionId)
    {
        return $this->model->where('division_owner_id', $divisionId)->with(['divisionOwner'])->get();
    }

    public function getProjectsByStatus($status)
    {
        return $this->model->where('status', $status)->with(['divisionOwner'])->get();
    }

    public function getProjectsByDateRange($startDate, $endDate)
    {
        return $this->model
            ->whereDate('start_planned', '>=', $startDate)
            ->whereDate('end_planned', '<=', $endDate)
            ->with(['divisionOwner'])
            ->get();
    }

    public function createProject(array $data)
    {
        try {
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error("Failed to create project: {$e->getMessage()}");
            return null;
        }
    }

    public function updateProject($id, array $data)
    {
        $project = $this->find($id);
        if (!$project) {
            return null;
        }

        try {
            $project->update($data);
            return $project->fresh(['divisionOwner']);
        } catch (\Exception $e) {
            Log::error("Failed to update project {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteProject($id)
    {
        $project = $this->find($id);
        if (!$project) {
            return false;
        }

        try {
            $project->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete project {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function updateProjectStatus($id, $status)
    {
        $project = $this->find($id);
        if (!$project) {
            return null;
        }

        $project->status = $status;
        $project->save();

        return $project->fresh(['divisionOwner']);
    }

    public function paginateProjects(array $filters = [], int $perPage = 20)
    {
        $query = $this->model->with(['divisionOwner']);

        // Simple filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['division_owner_id'])) {
            $query->where('division_owner_id', $filters['division_owner_id']);
        }

        if (isset($filters['client_name'])) {
            $query->where('client_name', $filters['client_name']);
        }

        // Free-text search across common columns (case-insensitive)
        if (!empty($filters['search'])) {
            $search = mb_strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $like = "%{$search}%";
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(client_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(status) LIKE ?', [$like]);
            });
        }

        // Show newest projects first so recent creations
        // (e.g. id 36) appear on the first page.
        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Hitung jumlah proyek total dan per status berdasarkan filter sederhana.
     *
     * @param array $filters
     * @return array{total:int,by_status:array<string,int>}
     */
    public function getProjectStatusCounts(array $filters = []): array
    {
        $baseQuery = $this->model->newQuery();

        // Simple filters (sama seperti paginateProjects, tanpa eager load)
        if (isset($filters['status'])) {
            $baseQuery->where('status', $filters['status']);
        }

        if (isset($filters['division_owner_id'])) {
            $baseQuery->where('division_owner_id', $filters['division_owner_id']);
        }

        if (isset($filters['client_name'])) {
            $baseQuery->where('client_name', $filters['client_name']);
        }

        if (!empty($filters['search'])) {
            $search = mb_strtolower($filters['search']);
            $baseQuery->where(function ($q) use ($search) {
                $like = "%{$search}%";
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(client_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(status) LIKE ?', [$like]);
            });
        }

        $total = (clone $baseQuery)->count();

        $byStatus = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->toArray();

        return [
            'total' => (int) $total,
            'by_status' => array_map('intval', $byStatus),
        ];
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Project with ID {$id} not found.");
            return null;
        }
    }
}
