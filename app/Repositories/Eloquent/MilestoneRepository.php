<?php

namespace App\Repositories\Eloquent;

use App\Models\Milestone;
use App\Repositories\Contracts\MilestoneRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class MilestoneRepository implements MilestoneRepositoryInterface
{
    /** @var Milestone */
    protected $model;

    public function __construct(Milestone $model)
    {
        $this->model = $model;
    }

    public function getAllMilestones()
    {
        return $this->model->with('project')->get();
    }

    public function getMilestoneById($id)
    {
        try {
            return $this->model->with('project')->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Milestone with ID {$id} not found.");
            return null;
        }
    }

    public function getMilestonesByProject($projectId)
    {
        return $this->model->where('project_id', $projectId)->with('project')->get();
    }

    public function getMilestonesByStatus($status)
    {
        return $this->model->where('status', $status)->with('project')->get();
    }

    public function getMilestonesByDateRange($startDate, $endDate)
    {
        return $this->model
            ->whereDate('due_planned', '>=', $startDate)
            ->whereDate('due_planned', '<=', $endDate)
            ->with('project')
            ->get();
    }

    public function createMilestone(array $data)
    {
        try {
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error("Failed to create milestone: {$e->getMessage()}");
            return null;
        }
    }

    public function updateMilestone($id, array $data)
    {
        $milestone = $this->find($id);
        if (!$milestone) return null;

        try {
            $milestone->update($data);
            return $milestone->fresh('project');
        } catch (\Exception $e) {
            Log::error("Failed to update milestone {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteMilestone($id)
    {
        $milestone = $this->find($id);
        if (!$milestone) return false;

        try {
            $milestone->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete milestone {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function updateMilestoneStatus($id, $status)
    {
        $milestone = $this->find($id);
        if (!$milestone) return null;

        $milestone->status = $status;
        $milestone->save();
        return $milestone->fresh('project');
    }

    public function completeMilestone($id)
    {
        $milestone = $this->find($id);
        if (!$milestone) return null;

        $milestone->status = 'Completed';
        $milestone->due_actual = Carbon::now()->toDateString();
        $milestone->save();

        return $milestone->fresh('project');
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Milestone with ID {$id} not found.");
            return null;
        }
    }
}

