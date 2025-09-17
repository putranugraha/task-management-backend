<?php

namespace App\Repositories\Eloquent;

use App\Models\ReportingPeriod;
use App\Repositories\Contracts\ReportingPeriodRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class ReportingPeriodRepository implements ReportingPeriodRepositoryInterface
{
    /** @var ReportingPeriod */
    protected $model;

    public function __construct(ReportingPeriod $model)
    {
        $this->model = $model;
    }

    public function getAllReportingPeriods()
    {
        return $this->model->with('project')->orderByDesc('period_date')->get();
    }

    public function getReportingPeriodById($id)
    {
        try {
            return $this->model->with('project')->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Reporting period with ID {$id} not found.");
            return null;
        }
    }

    public function getReportingPeriodsByProject($projectId)
    {
        return $this->model
            ->where('project_id', $projectId)
            ->with('project')
            ->orderByDesc('period_date')
            ->get();
    }

    public function getReportingPeriodByDate($projectId, $date)
    {
        return $this->model
            ->where('project_id', $projectId)
            ->whereDate('period_date', $date)
            ->with('project')
            ->first();
    }

    public function getReportingPeriodsByDateRange($projectId, $startDate, $endDate)
    {
        return $this->model
            ->where('project_id', $projectId)
            ->whereBetween('period_date', [$startDate, $endDate])
            ->with('project')
            ->orderBy('period_date')
            ->get();
    }

    public function createReportingPeriod(array $data)
    {
        try {
            $period = $this->model->create($data);
            return $period->fresh('project');
        } catch (\Exception $e) {
            Log::error("Failed to create reporting period: {$e->getMessage()}");
            return null;
        }
    }

    public function updateReportingPeriod($id, array $data)
    {
        $period = $this->find($id);
        if (!$period) {
            return null;
        }

        try {
            $period->update($data);
            return $period->fresh('project');
        } catch (\Exception $e) {
            Log::error("Failed to update reporting period {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteReportingPeriod($id)
    {
        $period = $this->find($id);
        if (!$period) {
            return false;
        }

        try {
            $period->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete reporting period {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function deleteReportingPeriodsByProject($projectId)
    {
        try {
            return $this->model->where('project_id', $projectId)->delete();
        } catch (\Exception $e) {
            Log::error("Failed to delete reporting periods for project {$projectId}: {$e->getMessage()}");
            return false;
        }
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Reporting period with ID {$id} not found.");
            return null;
        }
    }
}

