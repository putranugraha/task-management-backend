<?php

namespace App\Repositories\Eloquent;

use App\Models\StatusHistory;
use App\Repositories\Contracts\StatusHistoryRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class StatusHistoryRepository implements StatusHistoryRepositoryInterface
{
    protected StatusHistory $model;

    public function __construct(StatusHistory $model)
    {
        $this->model = $model;
    }

    public function getAllHistories()
    {
        return $this->model->latest('id')->get();
    }

    public function getHistoryById($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("StatusHistory with ID {$id} not found.");
            return null;
        }
    }

    public function getHistoriesByActor($actorId)
    {
        return $this->model->where('changed_by', $actorId)->latest('id')->get();
    }

    public function getHistoriesByEntityType($entityType)
    {
        // Saat ini hanya mendukung Task
        if (strtolower($entityType) !== 'task') {
            return collect();
        }
        return $this->model->latest('id')->get();
    }

    public function getHistoriesByEntity($entityType, $entityId)
    {
        if (strtolower($entityType) !== 'task') {
            return collect();
        }
        return $this->model->where('task_id', $entityId)->latest('id')->get();
    }

    public function createHistory(array $data)
    {
        try {
            // Mendukung bentuk entity_type/entity_id - map ke task_id bila entity_type=Task
            if (isset($data['entity_type']) && strtolower($data['entity_type']) === 'task' && isset($data['entity_id'])) {
                $data['task_id'] = $data['entity_id'];
                unset($data['entity_type'], $data['entity_id']);
            }
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error("Failed to create status history: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteHistory($id)
    {
        $row = $this->find($id);
        if (!$row) return false;
        try {
            $row->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete status history {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function deleteHistoriesByEntity($entityType, $entityId)
    {
        if (strtolower($entityType) !== 'task') {
            return false;
        }
        try {
            return (bool) $this->model->where('task_id', $entityId)->delete();
        } catch (\Exception $e) {
            Log::error("Failed to delete status histories for {$entityType}#{$entityId}: {$e->getMessage()}");
            return false;
        }
    }

    public function getHistoriesByDateRange($startDate, $endDate)
    {
        return $this->model
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->latest('id')
            ->get();
    }

    public function paginateHistories(array $filters = [], int $perPage = 20)
    {
        $query = $this->model->latest('id');

        if (isset($filters['actor_id'])) {
            $query->where('changed_by', $filters['actor_id']);
        }

        if (isset($filters['entity_type']) && strtolower($filters['entity_type']) === 'task') {
            if (isset($filters['entity_id'])) {
                $query->where('task_id', $filters['entity_id']);
            }
        }

        return $query->paginate($perPage);
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("StatusHistory with ID {$id} not found.");
            return null;
        }
    }
}
