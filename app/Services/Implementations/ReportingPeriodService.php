<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\ReportingPeriodRepositoryInterface;
use App\Services\Contracts\ReportingPeriodServiceInterface;
use Illuminate\Support\Facades\Cache;

class ReportingPeriodService implements ReportingPeriodServiceInterface
{
    protected ReportingPeriodRepositoryInterface $repository;

    const CACHE_ALL = 'reporting_periods.all';
    const CACHE_ID_PREFIX = 'reporting_period.'; // + id
    const CACHE_PROJECT_PREFIX = 'reporting_periods.project.'; // + projectId
    const CACHE_DATE_PREFIX = 'reporting_period.date.'; // + projectId + date
    const CACHE_DURATION = 1800; // 30 minutes

    public function __construct(ReportingPeriodRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllReportingPeriods()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, function () {
            return $this->repository->getAllReportingPeriods();
        });
    }

    public function getReportingPeriodById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, function () use ($id) {
            return $this->repository->getReportingPeriodById($id);
        });
    }

    public function getReportingPeriodsByProject($projectId)
    {
        return Cache::remember(self::CACHE_PROJECT_PREFIX.$projectId, self::CACHE_DURATION, function () use ($projectId) {
            return $this->repository->getReportingPeriodsByProject($projectId);
        });
    }

    public function getReportingPeriodByDate($projectId, $date)
    {
        $dateKey = $this->normaliseDateKey($date);

        return Cache::remember(self::CACHE_DATE_PREFIX.$projectId.'.'.$dateKey, self::CACHE_DURATION, function () use ($projectId, $date) {
            return $this->repository->getReportingPeriodByDate($projectId, $date);
        });
    }

    public function getReportingPeriodsByDateRange($projectId, $startDate, $endDate)
    {
        return $this->repository->getReportingPeriodsByDateRange($projectId, $startDate, $endDate);
    }

    public function createReportingPeriod(array $data)
    {
        $period = $this->repository->createReportingPeriod($data);
        $this->clearCaches(
            $period->id ?? null,
            $period->project_id ?? ($data['project_id'] ?? null),
            $period->period_date ?? ($data['period_date'] ?? null)
        );
        return $period;
    }

    public function updateReportingPeriod($id, array $data)
    {
        $period = $this->repository->updateReportingPeriod($id, $data);
        $this->clearCaches(
            $id,
            $period->project_id ?? ($data['project_id'] ?? null),
            $period->period_date ?? ($data['period_date'] ?? null)
        );
        return $period;
    }

    public function deleteReportingPeriod($id)
    {
        $period = $this->repository->getReportingPeriodById($id);
        $result = $this->repository->deleteReportingPeriod($id);
        $this->clearCaches($id, $period->project_id ?? null, $period->period_date ?? null);
        return $result;
    }

    public function deleteReportingPeriodsByProject($projectId)
    {
        $result = $this->repository->deleteReportingPeriodsByProject($projectId);
        $this->clearCaches(null, $projectId, null);
        return $result;
    }

    protected function clearCaches($id = null, $projectId = null, $date = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id !== null) {
            Cache::forget(self::CACHE_ID_PREFIX.$id);
        }
        if ($projectId !== null) {
            Cache::forget(self::CACHE_PROJECT_PREFIX.$projectId);
        }
        if ($projectId !== null && $date) {
            Cache::forget(self::CACHE_DATE_PREFIX.$projectId.'.'.$this->normaliseDateKey($date));
        }
    }

    protected function normaliseDateKey($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
        }

        return $value ?: null;
    }
}

