<?php

namespace App\Repositories\Eloquent;

use App\Models\KpiSnapshot;
use App\Repositories\Contracts\KpiSnapshotRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class KpiSnapshotRepository implements KpiSnapshotRepositoryInterface
{
    /** @var KpiSnapshot */
    protected $model;

    public function __construct(KpiSnapshot $model)
    {
        $this->model = $model;
    }

    public function getAllKpiSnapshots()
    {
        return $this->model->with(['project', 'reportingPeriod'])->latest('id')->get();
    }

    public function getKpiSnapshotById($id)
    {
        try {
            return $this->model->with(['project', 'reportingPeriod'])->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("KPI Snapshot with ID {$id} not found.");
            return null;
        }
    }

    public function getKpiSnapshotsByProject($projectId)
    {
        return $this->model
            ->where('project_id', $projectId)
            ->with(['project', 'reportingPeriod'])
            ->orderByDesc('period_id')
            ->get();
    }

    public function getKpiSnapshotsByPeriod($periodId)
    {
        return $this->model
            ->where('period_id', $periodId)
            ->with(['project', 'reportingPeriod'])
            ->get();
    }

    public function getKpiSnapshotByProjectAndPeriod($projectId, $periodId)
    {
        return $this->model
            ->where('project_id', $projectId)
            ->where('period_id', $periodId)
            ->with(['project', 'reportingPeriod'])
            ->first();
    }

    public function createKpiSnapshot(array $data)
    {
        try {
            $snap = $this->model->create($data);
            return $snap->fresh(['project', 'reportingPeriod']);
        } catch (\Exception $e) {
            Log::error("Failed to create KPI snapshot: {$e->getMessage()}");
            return null;
        }
    }

    public function updateKpiSnapshot($id, array $data)
    {
        $snap = $this->find($id);
        if (!$snap) return null;

        try {
            $snap->update($data);
            return $snap->fresh(['project', 'reportingPeriod']);
        } catch (\Exception $e) {
            Log::error("Failed to update KPI snapshot {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteKpiSnapshot($id)
    {
        $snap = $this->find($id);
        if (!$snap) return false;

        try {
            $snap->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete KPI snapshot {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function deleteKpiSnapshotsByProject($projectId)
    {
        try {
            return $this->model->where('project_id', $projectId)->delete();
        } catch (\Exception $e) {
            Log::error("Failed to delete KPI snapshots for project {$projectId}: {$e->getMessage()}");
            return false;
        }
    }

    public function getAverageCycleTimeByProject($projectId)
    {
        // Rata-rata dari kolom avg_cycle_time_days per snapshot project tsb
        return (float) $this->model
            ->where('project_id', $projectId)
            ->avg('avg_cycle_time_days');
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("KPI Snapshot with ID {$id} not found.");
            return null;
        }
    }
}

