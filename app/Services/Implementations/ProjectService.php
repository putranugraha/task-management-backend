<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Services\Contracts\ProjectBaselineServiceInterface;
use App\Services\Contracts\ProjectServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Mews\Purifier\Facades\Purifier;

class ProjectService implements ProjectServiceInterface
{
    /** @var ProjectRepositoryInterface */
    protected $repository;

    protected ProjectBaselineServiceInterface $baselineService;

    const CACHE_ALL = 'projects.all';
    const CACHE_STATUS_PREFIX = 'projects.status.'; // + status
    const CACHE_ID_PREFIX = 'project.'; // + id
    const CACHE_DURATION = 3600; // 1 hour

    // Allowed statuses for validation at service layer
    const ALLOWED_STATUSES = ['Planned', 'In Progress', 'Completed', 'On Hold', 'Cancelled'];

    public function __construct(ProjectRepositoryInterface $repository, ProjectBaselineServiceInterface $baselineService)
    {
        $this->repository = $repository;
        $this->baselineService = $baselineService;
    }

    public function getAllProjects()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, function () {
            return $this->repository->getAllProjects();
        });
    }

    public function getProjectById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, function () use ($id) {
            return $this->repository->getProjectById($id);
        });
    }

    public function getProjectByName($name)
    {
        return $this->repository->getProjectByName($name);  
    }

    public function getProjectByClient($clientName)
    {
        return $this->repository->getProjectByClient($clientName);
    }

    public function getProjectsByDivision($divisionId)
    {
        return $this->repository->getProjectsByDivision($divisionId);
    }

    public function getProjectsByStatus($status)
    {
        return Cache::remember(self::CACHE_STATUS_PREFIX.$status, self::CACHE_DURATION, function () use ($status) {
            return $this->repository->getProjectsByStatus($status);
        });
    }

    public function getProjectsByDateRange($startDate, $endDate)
    {
        return $this->repository->getProjectsByDateRange($startDate, $endDate);
    }

    public function paginateProjects(array $filters = [], int $perPage = 20)
    {
        // Untuk saat ini pagination tidak dicache agar sederhana
        // dan menghindari kompleksitas key per kombinasi filter + halaman.
        return $this->repository->paginateProjects($filters, $perPage);
    }

    /**
     * Hitung statistik proyek (total, active, completed) berdasarkan filter sederhana.
     *
     * Active didefinisikan sebagai semua status yang bukan Completed atau Cancelled.
     *
     * @param array $filters
     * @return array{total:int,active:int,completed:int}
     */
    public function getProjectStats(array $filters = []): array
    {
        $counts = $this->repository->getProjectStatusCounts($filters);

        $total = $counts['total'] ?? 0;
        $byStatus = $counts['by_status'] ?? [];

        $completed = $byStatus['Completed'] ?? 0;

        // Active = semua status yang bukan Completed atau Cancelled
        $inactiveStatuses = ['Completed', 'Cancelled'];
        $active = 0;
        foreach ($byStatus as $status => $count) {
            if (!in_array($status, $inactiveStatuses, true)) {
                $active += $count;
            }
        }

        return [
            'total' => (int) $total,
            'active' => (int) $active,
            'completed' => (int) $completed,
        ];
    }

    public function createProject(array $data)
    {
        $data = $this->sanitizeProjectRichText($data);
        $project = $this->repository->createProject($data);
        $this->clearCaches();

        // Auto-create initial baseline so FE doesn't need extra call
        if ($project) {
            $this->baselineService->createBaseline([
                'project_id' => $project->id,
                'baseline_name' => 'Initial Baseline',
                'taken_at' => Carbon::now(),
                // start/end base will be computed by service if not provided
            ]);

            $actor = Auth::user();

            $properties = [
                'project_id' => $project->id,
                'name' => $project->name,
                'client_name' => $project->client_name,
                'division_owner_id' => $project->division_owner_id,
                'status' => $project->status,
                'value_amount' => $project->value_amount,
            ];

            $activity = activity('projects')
                ->performedOn($project)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('created');
        }

        return $project;
    }

    public function updateProject($id, array $data)
    {
        $data = $this->sanitizeProjectRichText($data);
        $before = $this->repository->getProjectById($id);

        $project = $this->repository->updateProject($id, $data);
        $this->clearCaches($id, $project->status ?? null);

        if ($project) {
            $actor = Auth::user();

            $properties = [
                'project_id' => $project->id,
                'name_before' => $before->name ?? null,
                'name_after' => $project->name,
                'client_name_before' => $before->client_name ?? null,
                'client_name_after' => $project->client_name,
                'division_owner_id_before' => $before->division_owner_id ?? null,
                'division_owner_id_after' => $project->division_owner_id,
                'status_before' => $before->status ?? null,
                'status_after' => $project->status,
                'value_amount_before' => $before->value_amount ?? null,
                'value_amount_after' => $project->value_amount,
            ];

            $activity = activity('projects')
                ->performedOn($project)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('updated');
        }

        return $project;
    }

    public function deleteProject($id)
    {
        $project = $this->repository->getProjectById($id);
        $result = $this->repository->deleteProject($id);
        $this->clearCaches($id);

        if ($result && $project) {
            $actor = Auth::user();

            $properties = [
                'project_id' => $project->id,
                'name' => $project->name,
                'client_name' => $project->client_name,
                'division_owner_id' => $project->division_owner_id,
                'status' => $project->status,
                'value_amount' => $project->value_amount,
            ];

            $activity = activity('projects')
                ->performedOn($project)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('deleted');
        }

        return $result;
    }

    public function updateProjectStatus($id, $status)
    {
        if (!in_array($status, self::ALLOWED_STATUSES)) {
            return null;
        }

        $before = $this->repository->getProjectById($id);
        $project = $this->repository->updateProjectStatus($id, $status);
        $this->clearCaches($id, $status);

        if ($project) {
            $actor = Auth::user();

            $properties = [
                'project_id' => $project->id,
                'status_before' => $before->status ?? null,
                'status_after' => $project->status,
            ];

            $activity = activity('projects')
                ->performedOn($project)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('status_changed');
        }

        return $project;
    }

    protected function clearCaches($id = null, $status = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id !== null) {
            Cache::forget(self::CACHE_ID_PREFIX.$id);
        }
        if ($status !== null) {
            Cache::forget(self::CACHE_STATUS_PREFIX.$status);
        }
    }

    protected function sanitizeProjectRichText(array $data): array
    {
        $allowed = 'p,b,strong,i,em,ul,ol,li,br';

        foreach (['scope', 'objective'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if ($value === null || $value === '') {
                $data[$key] = $value;
                continue;
            }
            $data[$key] = Purifier::clean((string) $value, [
                'HTML.Allowed' => $allowed,
                'Attr.EnableID' => false,
                'CSS.AllowedProperties' => [],
                'AutoFormat.RemoveEmpty' => true,
            ]);
        }

        return $data;
    }
}
