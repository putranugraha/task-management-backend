<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Services\Contracts\ProjectServiceInterface;
use Illuminate\Support\Facades\Cache;

class ProjectService implements ProjectServiceInterface
{
    /** @var ProjectRepositoryInterface */
    protected $repository;

    const CACHE_ALL = 'projects.all';
    const CACHE_STATUS_PREFIX = 'projects.status.'; // + status
    const CACHE_ID_PREFIX = 'project.'; // + id
    const CACHE_DURATION = 3600; // 1 hour

    // Allowed statuses for validation at service layer
    const ALLOWED_STATUSES = ['Planned', 'In Progress', 'Completed', 'On Hold', 'Cancelled'];

    public function __construct(ProjectRepositoryInterface $repository)
    {
        $this->repository = $repository;
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

    public function createProject(array $data)
    {
        $project = $this->repository->createProject($data);
        $this->clearCaches();
        return $project;
    }

    public function updateProject($id, array $data)
    {
        $project = $this->repository->updateProject($id, $data);
        $this->clearCaches($id, $project->status ?? null);
        return $project;
    }

    public function deleteProject($id)
    {
        $result = $this->repository->deleteProject($id);
        $this->clearCaches($id);
        return $result;
    }

    public function updateProjectStatus($id, $status)
    {
        if (!in_array($status, self::ALLOWED_STATUSES)) {
            return null;
        }
        $project = $this->repository->updateProjectStatus($id, $status);
        $this->clearCaches($id, $status);
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
}

