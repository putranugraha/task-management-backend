<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\KpiSnapshotRepositoryInterface;
use App\Services\Contracts\KpiSnapshotServiceInterface;
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

