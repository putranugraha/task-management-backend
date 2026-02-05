<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\KpiSnapshotRepositoryInterface;
use App\Services\Contracts\KpiSnapshotServiceInterface;
use App\Models\KpiSnapshot;
use App\Models\ReportingPeriod;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class KpiSnapshotService implements KpiSnapshotServiceInterface
{
    protected KpiSnapshotRepositoryInterface $repository;

    const CACHE_ALL = 'kpi_snapshots.all';
    const CACHE_ID_PREFIX = 'kpi_snapshot.'; // + id
    const CACHE_PROJECT_PREFIX = 'kpi_snapshots.project.'; // + projectId
    const CACHE_PERIOD_PREFIX = 'kpi_snapshots.period.'; // + periodId
    const CACHE_PROJECT_PERIOD_PREFIX = 'kpi_snapshot.project_period.'; // + projectId.periodId
    const CACHE_AVG_PREFIX = 'kpi_snapshots.avg_cycle.project.'; // + projectId
    const CACHE_DURATION = 1800; // 30 minutes

    public function __construct(KpiSnapshotRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllKpiSnapshots()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, fn () => $this->repository->getAllKpiSnapshots());
    }

    public function getKpiSnapshotById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, fn () => $this->repository->getKpiSnapshotById($id));
    }

    public function getKpiSnapshotsByProject($projectId)
    {
        return Cache::remember(self::CACHE_PROJECT_PREFIX.$projectId, self::CACHE_DURATION, fn () => $this->repository->getKpiSnapshotsByProject($projectId));
    }

    public function getKpiSnapshotsByPeriod($periodId)
    {
        return Cache::remember(self::CACHE_PERIOD_PREFIX.$periodId, self::CACHE_DURATION, fn () => $this->repository->getKpiSnapshotsByPeriod($periodId));
    }

    public function getKpiSnapshotByProjectAndPeriod($projectId, $periodId)
    {
        $key = self::CACHE_PROJECT_PERIOD_PREFIX.$projectId.'.'.$periodId;
        return Cache::remember($key, self::CACHE_DURATION, fn () => $this->repository->getKpiSnapshotByProjectAndPeriod($projectId, $periodId));
    }

    public function createKpiSnapshot(array $data)
    {
        $snap = $this->repository->createKpiSnapshot($data);
        $this->clearCaches($snap->id ?? null, $snap->project_id ?? ($data['project_id'] ?? null), $snap->period_id ?? ($data['period_id'] ?? null));
        return $snap;
    }

    public function updateKpiSnapshot($id, array $data)
    {
        $before = $this->repository->getKpiSnapshotById($id);
        $snap = $this->repository->updateKpiSnapshot($id, $data);
        $this->clearCaches($id, $snap->project_id ?? $before->project_id ?? null, $snap->period_id ?? $before->period_id ?? null);
        return $snap;
    }

    public function deleteKpiSnapshot($id)
    {
        $snap = $this->repository->getKpiSnapshotById($id);
        $result = $this->repository->deleteKpiSnapshot($id);
        $this->clearCaches($id, $snap->project_id ?? null, $snap->period_id ?? null);
        return $result;
    }

    public function deleteKpiSnapshotsByProject($projectId)
    {
        $result = $this->repository->deleteKpiSnapshotsByProject($projectId);
        $this->clearCaches(null, $projectId, null);
        return $result;
    }

    public function getAverageCycleTimeByProject($projectId)
    {
        return Cache::remember(self::CACHE_AVG_PREFIX.$projectId, self::CACHE_DURATION, fn () => $this->repository->getAverageCycleTimeByProject($projectId));
    }

    public function generateForProjectAndDate($projectId, $periodDate, ?string $note = null)
    {
        $projectId = (int) $projectId;
        if (!$projectId || !$periodDate) {
            return null;
        }

        $date = Carbon::parse($periodDate)->startOfDay();

        $period = ReportingPeriod::where('project_id', $projectId)
            ->whereDate('period_date', $date->toDateString())
            ->first();

        if (!$period) {
            $period = ReportingPeriod::create([
                'project_id' => $projectId,
                'period_date' => $date->toDateString(),
                'note' => $note,
            ]);
        } elseif ($note !== null) {
            $period->note = $note;
            $period->save();
        }

        // Clear reporting period caches so FE can see latest periods without manual refresh
        Cache::forget('reporting_periods.all');
        Cache::forget('reporting_periods.project.'.$projectId);
        Cache::forget('reporting_period.date.'.$projectId.'.'.$date->toDateString());

        $tasks = Task::where('project_id', $projectId)->get();
        $tasksTotal = $tasks->count();
        $tasksDone = $tasks->where('status', 'Done')->count();

        $overdueCount = $tasks
            ->filter(function ($task) use ($date) {
                if (empty($task->end_planned)) {
                    return false;
                }

                $plannedEnd = Carbon::parse($task->end_planned);

                return $plannedEnd->lessThan($date) && $task->status !== 'Done';
            })
            ->count();

        $cycleTimes = $tasks
            ->filter(fn ($task) => $task->start_actual && $task->end_actual)
            ->map(function ($task) {
                $start = Carbon::parse($task->start_actual);
                $end = Carbon::parse($task->end_actual);

                return max(0, $start->diffInDays($end));
            });

        $avgCycle = $cycleTimes->isNotEmpty() ? round($cycleTimes->avg(), 2) : 0;

        $snap = KpiSnapshot::updateOrCreate(
            [
                'project_id' => $projectId,
                'period_id' => $period->id,
            ],
            [
                'tasks_total' => $tasksTotal,
                'tasks_done' => $tasksDone,
                'overdue_count' => $overdueCount,
                'avg_cycle_time_days' => $avgCycle,
            ]
        );

        $this->clearCaches($snap->id ?? null, $projectId, $period->id);

        return $snap->fresh(['project', 'reportingPeriod']);
    }

    protected function clearCaches($id = null, $projectId = null, $periodId = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id !== null) Cache::forget(self::CACHE_ID_PREFIX.$id);
        if ($projectId !== null) Cache::forget(self::CACHE_PROJECT_PREFIX.$projectId);
        if ($periodId !== null) Cache::forget(self::CACHE_PERIOD_PREFIX.$periodId);
        if ($projectId !== null && $periodId !== null) Cache::forget(self::CACHE_PROJECT_PERIOD_PREFIX.$projectId.'.'.$periodId);
        if ($projectId !== null) Cache::forget(self::CACHE_AVG_PREFIX.$projectId);
    }
}
