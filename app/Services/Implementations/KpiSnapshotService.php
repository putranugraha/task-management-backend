<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\KpiSnapshotRepositoryInterface;
use App\Services\Contracts\KpiSnapshotServiceInterface;
use App\Models\KpiSnapshot;
use App\Models\ReportingPeriod;
use App\Models\StatusHistory;
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

        $asOfEnd = $date->copy()->endOfDay();
        $tasks = Task::withTrashed()
            ->where('project_id', $projectId)
            ->where('created_at', '<=', $asOfEnd)
            ->where(function ($query) use ($asOfEnd) {
                $query->whereNull('deleted_at')
                    ->orWhere('deleted_at', '>', $asOfEnd);
            })
            ->get();
        $tasksTotal = $tasks->count();
        $taskIds = $tasks->pluck('id')->all();
        $statusAsOf = $this->taskStatusesAsOf($taskIds, $asOfEnd);

        $tasksDone = $tasks
            ->filter(function ($task) use ($asOfEnd, $statusAsOf) {
                $status = $statusAsOf[$task->id] ?? $task->status;
                $endActual = $task->end_actual ? Carbon::parse($task->end_actual)->endOfDay() : null;

                return $this->isDoneStatus($status)
                    && ($endActual === null || $endActual->lessThanOrEqualTo($asOfEnd));
            })
            ->count();

        $overdueCount = $tasks
            ->filter(function ($task) use ($date, $asOfEnd, $statusAsOf) {
                if (empty($task->end_planned)) {
                    return false;
                }

                $plannedEnd = Carbon::parse($task->end_planned);
                $status = $statusAsOf[$task->id] ?? $task->status;
                $endActual = $task->end_actual ? Carbon::parse($task->end_actual)->endOfDay() : null;
                $doneByAsOf = $this->isDoneStatus($status)
                    && ($endActual === null || $endActual->lessThanOrEqualTo($asOfEnd));

                return $plannedEnd->lessThan($date) && ! $doneByAsOf;
            })
            ->count();

        $cycleTimes = $tasks
            ->filter(function ($task) use ($asOfEnd) {
                if (! $task->start_actual || ! $task->end_actual) {
                    return false;
                }

                return Carbon::parse($task->end_actual)->endOfDay()->lessThanOrEqualTo($asOfEnd);
            })
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

    /**
     * Resolve task statuses as they were at the snapshot date.
     *
     * If there is no prior history, use the first future row's from_status to infer
     * the status before that change. This keeps old KPI generation from using a
     * task's current status after it has changed.
     *
     * @param array<int> $taskIds
     * @return array<int, string|null>
     */
    protected function taskStatusesAsOf(array $taskIds, Carbon $asOfEnd): array
    {
        if (empty($taskIds)) {
            return [];
        }

        $histories = StatusHistory::query()
            ->whereIn('task_id', $taskIds)
            ->orderBy('task_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['task_id', 'from_status', 'to_status', 'created_at']);

        $result = [];
        foreach ($histories->groupBy('task_id') as $taskId => $rows) {
            $latestBefore = null;
            $firstAfter = null;

            foreach ($rows as $row) {
                $changedAt = Carbon::parse($row->created_at);
                if ($changedAt->lessThanOrEqualTo($asOfEnd)) {
                    $latestBefore = $row;
                    continue;
                }

                $firstAfter = $row;
                break;
            }

            if ($latestBefore) {
                $result[(int) $taskId] = $latestBefore->to_status;
            } elseif ($firstAfter) {
                $result[(int) $taskId] = $firstAfter->from_status;
            }
        }

        return $result;
    }

    protected function isDoneStatus(?string $status): bool
    {
        return strtolower(trim((string) $status)) === 'done'
            || strtolower(trim((string) $status)) === 'selesai';
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
