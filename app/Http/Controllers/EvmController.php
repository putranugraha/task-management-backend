<?php

namespace App\Http\Controllers;

use App\Http\Requests\EvmQueryRequest;
use App\Models\Project;
use App\Services\Contracts\EvmServiceInterface;

class EvmController extends Controller
{
    protected EvmServiceInterface $service;

    public function __construct(EvmServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * GET /projects/{project}/evm?date=YYYY-MM-DD[&baseline_id=...]
     */
    public function projectEvm(EvmQueryRequest $request, $project)
    {
        $projectModel = Project::findOrFail($project);
        $date = $request->query('date');
        $baselineId = $request->query('baseline_id');

        $result = $this->service->computeForProjectDate((int) $projectModel->id, $date, $baselineId ? (int) $baselineId : null);
        return response()->json($result);
    }
}

