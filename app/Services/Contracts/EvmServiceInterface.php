<?php

namespace App\Services\Contracts;

interface EvmServiceInterface
{
    /**
     * Compute EVM metrics for a project at a given date.
     *
     * @param int $projectId
     * @param \DateTimeInterface|string $date Y-m-d or Date instance
     * @param int|null $baselineId Optional baseline to use; otherwise latest or task plan
     * @return array{project_id:int,date:string,baseline_id:int|null,pv:float,ev:float,ac:float,sv:float,spi:float|null,cv:float,cpi:float|null,meta:array}
     */
    public function computeForProjectDate(int $projectId, $date, ?int $baselineId = null): array;
}

