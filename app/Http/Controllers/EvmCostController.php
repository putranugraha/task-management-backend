<?php

namespace App\Http\Controllers;

use App\Http\Requests\EvmCostQueryRequest;
use App\Models\Project;
use App\Services\Contracts\EvmCostServiceInterface;
use Carbon\Carbon;

class EvmCostController extends Controller
{
    protected EvmCostServiceInterface $service;

    public function __construct(EvmCostServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * GET /projects/{project}/evm-cost?date=YYYY-MM-DD[&baseline_id=...]
     */
    public function projectEvmCost(EvmCostQueryRequest $request, Project $project)
    {
        $date = $request->validated('date');
        $baselineId = $request->validated('baseline_id');

        $asOfDate = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();

        $result = $this->service->computeForProjectDate((int) $project->id, $asOfDate, $baselineId ? (int) $baselineId : null);

        return response()->json($result);
    }
}

