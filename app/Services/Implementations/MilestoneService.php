<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\MilestoneRepositoryInterface;
use App\Services\Contracts\MilestoneServiceInterface;
use App\Services\Contracts\ProjectBaselineServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
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

    public function paginateMilestones(array $filters = [], int $perPage = 20)
    {
        // Pagination tidak dicache untuk menjaga kesederhanaan key cache.
        return $this->repository->paginateMilestones($filters, $perPage);
    }

    /**
     * Hitung statistik milestone (total, completed, overdue) berdasarkan filter sederhana.
     *
     * Completed didefinisikan sebagai status "Completed".
     * Overdue didefinisikan sebagai status "Overdue".
     *
     * @param array $filters
     * @return array{total:int,completed:int,overdue:int}
     */
    public function getMilestoneStats(array $filters = []): array
    {
        $counts = $this->repository->getMilestoneStatusCounts($filters);

        $total = $counts['total'] ?? 0;
        $byStatus = $counts['by_status'] ?? [];

        $completed = $byStatus['Completed'] ?? 0;
        $overdue = $byStatus['Overdue'] ?? 0;

        return [
            'total' => (int) $total,
            'completed' => (int) $completed,
            'overdue' => (int) $overdue,
        ];
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

        if ($ms) {
            $actor = Auth::user();

            $properties = [
                'milestone_id' => $ms->id,
                'project_id' => $ms->project_id,
                'name' => $ms->name,
                'due_planned' => $ms->due_planned,
                'due_actual' => $ms->due_actual,
                'status' => $ms->status,
            ];

            $activity = activity('milestones')
                ->performedOn($ms)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('created');
        }

        return $ms;
    }

    public function updateMilestone($id, array $data)
    {
        $before = $this->repository->getMilestoneById($id);

        $ms = $this->repository->updateMilestone($id, $data);
        $this->clearCaches($ms->project_id ?? null, $ms->status ?? null, $id);

        if ($ms) {
            $actor = Auth::user();

            $properties = [
                'milestone_id' => $ms->id,
                'project_id_before' => $before->project_id ?? null,
                'project_id_after' => $ms->project_id,
                'name_before' => $before->name ?? null,
                'name_after' => $ms->name,
                'due_planned_before' => $before->due_planned ?? null,
                'due_planned_after' => $ms->due_planned,
                'due_actual_before' => $before->due_actual ?? null,
                'due_actual_after' => $ms->due_actual,
                'status_before' => $before->status ?? null,
                'status_after' => $ms->status,
            ];

            $activity = activity('milestones')
                ->performedOn($ms)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('updated');
        }

        return $ms;
    }

    public function deleteMilestone($id)
    {
        $ms = $this->getMilestoneById($id);
        $result = $this->repository->deleteMilestone($id);
        $this->clearCaches($ms->project_id ?? null, $ms->status ?? null, $id);

        if ($result && $ms) {
            $actor = Auth::user();

            $properties = [
                'milestone_id' => $ms->id,
                'project_id' => $ms->project_id,
                'name' => $ms->name,
                'due_planned' => $ms->due_planned,
                'due_actual' => $ms->due_actual,
                'status' => $ms->status,
            ];

            $activity = activity('milestones')
                ->performedOn($ms)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('deleted');
        }

        return $result;
    }

    public function updateMilestoneStatus($id, $status)
    {
        if (!in_array($status, self::ALLOWED_STATUSES)) return null;
        $before = $this->repository->getMilestoneById($id);
        $ms = $this->repository->updateMilestoneStatus($id, $status);
        $this->clearCaches($ms->project_id ?? null, $status, $id);

        if ($ms) {
            $actor = Auth::user();

            $properties = [
                'milestone_id' => $ms->id,
                'project_id' => $ms->project_id,
                'status_before' => $before->status ?? null,
                'status_after' => $ms->status,
            ];

            $activity = activity('milestones')
                ->performedOn($ms)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('status_changed');
        }

        return $ms;
    }

    public function completeMilestone($id)
    {
        $before = $this->repository->getMilestoneById($id);
        $ms = $this->repository->completeMilestone($id);
        $this->clearCaches($ms->project_id ?? null, $ms->status ?? null, $id);

        if ($ms) {
            $actor = Auth::user();

            $properties = [
                'milestone_id' => $ms->id,
                'project_id' => $ms->project_id,
                'status_before' => $before->status ?? null,
                'status_after' => $ms->status,
            ];

            $activity = activity('milestones')
                ->performedOn($ms)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('completed');
        }

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
