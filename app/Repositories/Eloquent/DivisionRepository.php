<?php

namespace App\Repositories\Eloquent;

use App\Models\Division;
use App\Models\User;
use App\Repositories\Contracts\DivisionRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class DivisionRepository implements DivisionRepositoryInterface
{
    protected Division $model;

    public function __construct(Division $model)
    {
        $this->model = $model;
    }

    public function getAllDivisions()
    {
        return $this->model->orderBy('name')->get();
    }

    public function getDivisionByStatus($status)
    {
        return $this->model->where('status', $status)->orderBy('name')->get();
    }

    public function getDivisionById($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Division with ID {$id} not found.");
            return null;
        }
    }

    public function getDivisionByCode($code)
    {
        return $this->model->where('code', $code)->first();
    }

    public function getDivisionByName($name)
    {
        return $this->model->where('name', $name)->first();
    }

    public function createDivision(array $data)
    {
        try {
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error("Failed to create division: {$e->getMessage()}");
            return null;
        }
    }

    public function updateDivision($id, array $data)
    {
        $division = $this->find($id);
        if (!$division) {
            return null;
        }

        try {
            $division->update($data);
            return $division->fresh();
        } catch (\Exception $e) {
            Log::error("Failed to update division {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteDivision($id)
    {
        return (bool) $this->updateDivisionStatus($id, 'Non Aktif');
    }

    public function updateDivisionStatus($id, $status)
    {
        $division = $this->find($id);
        if (!$division) {
            return null;
        }

        try {
            $division->status = $status;
            $division->save();
            return $division->fresh();
        } catch (\Exception $e) {
            Log::error("Failed to update division status {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function countUsersInDivision($divisionId)
    {
        return (int) User::where('division_id', $divisionId)->count();
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Division with ID {$id} not found.");
            return null;
        }
    }
}

