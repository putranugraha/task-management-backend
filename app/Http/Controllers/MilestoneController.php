<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\MilestoneStoreRequest;
use App\Http\Requests\MilestoneUpdateRequest;
use App\Http\Resources\MilestoneResource;
use App\Services\Contracts\MilestoneServiceInterface;

class MilestoneController extends Controller
{
    protected MilestoneServiceInterface $service;

    public function __construct(MilestoneServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $projectId = $request->query('project_id');
        $status = $request->query('status');
        $start = $request->query('start');
        $end = $request->query('end');

        if ($projectId) {
            $milestones = $this->service->getMilestonesByProject($projectId);
        } elseif ($status) {
            $milestones = $this->service->getMilestonesByStatus($status);
        } elseif ($start && $end) {
            $milestones = $this->service->getMilestonesByDateRange($start, $end);
        } else {
            $milestones = $this->service->getAllMilestones();
        }

        return MilestoneResource::collection($milestones);
    }

    public function store(MilestoneStoreRequest $request)
    {
        $ms = $this->service->createMilestone($request->validated());
        if (!$ms) return response()->json(['message' => 'Gagal membuat milestone'], 400);
        return new MilestoneResource($ms);
    }

    public function show(string $id)
    {
        $ms = $this->service->getMilestoneById($id);
        if (!$ms) return response()->json(['message' => 'Milestone tidak ditemukan'], 404);
        return new MilestoneResource($ms);
    }

    public function update(MilestoneUpdateRequest $request, string $id)
    {
        $ms = $this->service->updateMilestone($id, $request->validated());
        if (!$ms) return response()->json(['message' => 'Milestone tidak ditemukan'], 404);
        return new MilestoneResource($ms);
    }

    public function destroy(string $id)
    {
        $deleted = $this->service->deleteMilestone($id);
        if (!$deleted) return response()->json(['message' => 'Milestone tidak ditemukan'], 404);
        return response()->json(['message' => 'Milestone berhasil dihapus']);
    }

    public function updateStatus(string $id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:Planned,In Progress,Completed,Overdue,On Hold',
        ]);
        $ms = $this->service->updateMilestoneStatus($id, $request->input('status'));
        if (!$ms) return response()->json(['message' => 'Gagal update status atau milestone tidak ditemukan'], 400);
        return new MilestoneResource($ms);
    }

    public function complete(string $id)
    {
        $ms = $this->service->completeMilestone($id);
        if (!$ms) return response()->json(['message' => 'Milestone tidak ditemukan'], 404);
        return new MilestoneResource($ms);
    }
}

