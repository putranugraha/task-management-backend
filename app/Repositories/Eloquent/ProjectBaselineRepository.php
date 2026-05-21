<?php

namespace App\Repositories\Eloquent;

use App\Models\ProjectBaseline;
use App\Repositories\Contracts\ProjectBaselineRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class ProjectBaselineRepository implements ProjectBaselineRepositoryInterface
{
    /** @var ProjectBaseline */
    protected $model;

    public function __construct(ProjectBaseline $model)
    {
        $this->model = $model;
    }

    public function getAllBaselines()
    {
        return $this->model->with('project')->orderByDesc('taken_at')->get();
    }

    public function getBaselineById($id)
    {
        try {
            return $this->model->with('project')->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Project baseline with ID {$id} not found.");
            return null;
        }
    }

    public function getBaselinesByProject($projectId)
    {
        return $this->model
            ->where('project_id', $projectId)
            ->with('project')
            ->orderByDesc('taken_at')
            ->get();
    }

    public function getBaselineByName($projectId, $baselineName)
    {
        return $this->model
            ->where('project_id', $projectId)
            ->where('baseline_name', $baselineName)
            ->with('project')
            ->first();
    }

    public function getLatestBaselineByProject($projectId)
    {
        return $this->model
            ->where('project_id', $projectId)
            ->with('project')
            ->orderByDesc('taken_at')
            ->orderByDesc('id')
            ->first();
    }

    public function createBaseline(array $data)
    {
        try {
            $baseline = $this->model->create($data);
            return $baseline->fresh('project');
        } catch (\Exception $e) {
            Log::error("Failed to create project baseline: {$e->getMessage()}");
            return null;
        }
    }

    public function updateBaseline($id, array $data)
    {
        $baseline = $this->find($id);
        if (!$baseline) {
            return null;
        }

        try {
            $baseline->update($data);
            return $baseline->fresh('project');
        } catch (\Exception $e) {
            Log::error("Failed to update project baseline {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteBaseline($id)
    {
        $baseline = $this->find($id);
        if (!$baseline) {
            return false;
        }

        try {
            $baseline->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete project baseline {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function deleteBaselinesByProject($projectId)
    {
        try {
            return $this->model->where('project_id', $projectId)->delete();
        } catch (\Exception $e) {
            Log::error("Failed to delete project baselines for project {$projectId}: {$e->getMessage()}");
            return false;
        }
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Project baseline with ID {$id} not found.");
            return null;
        }
    }
}

