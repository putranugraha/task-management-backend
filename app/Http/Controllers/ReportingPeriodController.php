<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ReportingPeriodStoreRequest;
use App\Http\Requests\ReportingPeriodUpdateRequest;
use App\Http\Resources\ReportingPeriodResource;
use App\Services\Contracts\ReportingPeriodServiceInterface;

class ReportingPeriodController extends Controller
{
    protected ReportingPeriodServiceInterface $service;

    public function __construct(ReportingPeriodServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $routeProject = $request->route('project');
        $projectId = $request->query('project_id');

        if (!$projectId && $routeProject !== null) {
            if (is_object($routeProject) && method_exists($routeProject, 'getKey')) {
                $projectId = $routeProject->getKey();
            } elseif (is_scalar($routeProject)) {
                $projectId = $routeProject;
            }
        }

        if ($projectId) {
            $start = $request->query('start_date');
            $end = $request->query('end_date');
            $date = $request->query('date');

            if ($date) {
                $period = $this->service->getReportingPeriodByDate($projectId, $date);
                if (!$period) {
                    return response()->json(['message' => 'Reporting period tidak ditemukan'], 404);
                }
                return new ReportingPeriodResource($period);
            }

            if ($start && $end) {
                $items = $this->service->getReportingPeriodsByDateRange($projectId, $start, $end);
            } else {
                $items = $this->service->getReportingPeriodsByProject($projectId);
            }
        } else {
            $items = $this->service->getAllReportingPeriods();
        }

        return ReportingPeriodResource::collection($items);
    }

    public function store(ReportingPeriodStoreRequest $request)
    {
        $period = $this->service->createReportingPeriod($request->validated());
        if (!$period) {
            return response()->json(['message' => 'Gagal membuat reporting period'], 400);
        }
        $period->loadMissing('project');
        return new ReportingPeriodResource($period);
    }

    public function show(string $id)
    {
        $period = $this->service->getReportingPeriodById($id);
        if (!$period) {
            return response()->json(['message' => 'Reporting period tidak ditemukan'], 404);
        }
        return new ReportingPeriodResource($period);
    }

    public function update(ReportingPeriodUpdateRequest $request, string $id)
    {
        $period = $this->service->updateReportingPeriod($id, $request->validated());
        if (!$period) {
            return response()->json(['message' => 'Reporting period tidak ditemukan atau invalid'], 404);
        }
        $period->loadMissing('project');
        return new ReportingPeriodResource($period);
    }

    public function destroy(string $id)
    {
        $deleted = $this->service->deleteReportingPeriod($id);
        if (!$deleted) {
            return response()->json(['message' => 'Reporting period tidak ditemukan'], 404);
        }
        return response()->json(['message' => 'Reporting period berhasil dihapus']);
    }

    public function destroyByProject(string $projectId)
    {
        $deleted = $this->service->deleteReportingPeriodsByProject($projectId);
        if (!$deleted) {
            return response()->json(['message' => 'Tidak ada reporting period dihapus atau project tidak ditemukan'], 404);
        }
        return response()->json(['message' => 'Reporting period untuk proyek ini berhasil dihapus', 'deleted' => (int) $deleted]);
    }
}

