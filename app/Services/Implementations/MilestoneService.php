<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\MilestoneRepositoryInterface;
use App\Services\Contracts\MilestoneServiceInterface;
use App\Services\Contracts\ProjectBaselineServiceInterface;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class MilestoneService implements MilestoneServiceInterface
{
    /** @var MilestoneRepositoryInterface */
    protected $repository;
    protected ProjectBaselineServiceInterface $baselineService;

    const CACHE_ALL = 'milestones.all';
    const CACHE_ID_PREFIX = 'milestone.';
    const CACHE_STATUS_PREFIX = 'milestones.status.';
    const CACHE_PROJECT_PREFIX = 'milestones.project.';
    const CACHE_DURATION = 1800; // 30 minutes

    const ALLOWED_STATUSES = ['Planned', 'In Progress', 'Completed', 'Overdue', 'On Hold'];

    public function __construct(MilestoneRepositoryInterface $repository, ProjectBaselineServiceInterface $baselineService)
    {
        $this->repository = $repository;
        $this->baselineService = $baselineService;
    }

    public function getAllMilestones()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, fn () => $this->repository->getAllMilestones());
    }

    public function getMilestoneById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, fn () => $this->repository->getMilestoneById($id));
    }

    public function getMilestonesByProject($projectId)
    {
        return Cache::remember(self::CACHE_PROJECT_PREFIX.$projectId, self::CACHE_DURATION, fn () => $this->repository->getMilestonesByProject($projectId));
    }

    public function getMilestonesByStatus($status)
    {
        return Cache::remember(self::CACHE_STATUS_PREFIX.$status, self::CACHE_DURATION, fn () => $this->repository->getMilestonesByStatus($status));
    }

    public function getMilestonesByDateRange($startDate, $endDate)
    {
        return $this->repository->getMilestonesByDateRange($startDate, $endDate);
    }

    public function createMilestone(array $data)
    {
        $ms = $this->repository->createMilestone($data);
        $this->clearCaches($ms->project_id ?? null, $ms->status ?? null, $ms->id ?? null);

        // Ensure project has at least one baseline so FE doesn't need to manage it
        if ($ms && $ms->project_id) {
            $latest = $this->baselineService->getLatestBaselineByProject($ms->project_id);
            if (!$latest) {
                $this->baselineService->createBaseline([
                    'project_id' => $ms->project_id,
                    'baseline_name' => 'Initial Baseline',
                    'taken_at' => Carbon::now(),
                ]);
            }
        }

        return $ms;
    }

    public function updateMilestone($id, array $data)
    {
        $ms = $this->repository->updateMilestone($id, $data);
        $this->clearCaches($ms->project_id ?? null, $ms->status ?? null, $id);
        return $ms;
    }

    public function deleteMilestone($id)
    {
        $ms = $this->getMilestoneById($id);
        $result = $this->repository->deleteMilestone($id);
        $this->clearCaches($ms->project_id ?? null, $ms->status ?? null, $id);
        return $result;
    }

    public function updateMilestoneStatus($id, $status)
    {
        if (!in_array($status, self::ALLOWED_STATUSES)) return null;
        $ms = $this->repository->updateMilestoneStatus($id, $status);
        $this->clearCaches($ms->project_id ?? null, $status, $id);
        return $ms;
    }

    public function completeMilestone($id)
    {
        $ms = $this->repository->completeMilestone($id);
        $this->clearCaches($ms->project_id ?? null, $ms->status ?? null, $id);
        return $ms;
    }

    protected function clearCaches($projectId = null, $status = null, $id = null)
    {
        Cache::forget(self::CACHE_ALL);
        if ($projectId) Cache::forget(self::CACHE_PROJECT_PREFIX.$projectId);
        if ($status) Cache::forget(self::CACHE_STATUS_PREFIX.$status);
        if ($id) Cache::forget(self::CACHE_ID_PREFIX.$id);
    }
}
