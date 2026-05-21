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
     * GET /projects/{project}/evm-cost?as_of=YYYY-MM-DD[&baseline_id=...]
     * Backward compatible: ?date=YYYY-MM-DD
     */
    public function projectEvmCost(EvmCostQueryRequest $request, Project $project)
    {
        $asOf = $request->validated('as_of') ?? $request->validated('date');
        $baselineId = $request->validated('baseline_id');

        $asOfDate = $asOf ? Carbon::parse($asOf)->toDateString() : Carbon::today()->toDateString();

        $result = $this->service->computeForProjectDate((int) $project->id, $asOfDate, $baselineId ? (int) $baselineId : null);

        // Prefer "as_of" in response for clarity and consistency with query param.
        if (is_array($result) && array_key_exists('date', $result) && ! array_key_exists('as_of', $result)) {
            $result['as_of'] = $result['date'];
            unset($result['date']);
        }

        return response()->json($result);
    }
}
