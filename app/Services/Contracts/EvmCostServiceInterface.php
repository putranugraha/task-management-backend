<?php

namespace App\Services\Contracts;

interface EvmCostServiceInterface
{
    /**
     * Compute cost-based EVM metrics for a project at a given date.
     *
     * All monetary values are returned in IDR.
     *
     * @param int $projectId
     * @param \DateTimeInterface|string $date Y-m-d or Date instance
     * @param int|null $baselineId Optional project_baselines.id
     * @return array{
     *   project_id:int,
     *   date:string,
     *   baseline_id:int|null,
     *   unit:string,
     *   bac:float,
     *   pv:float,
     *   ev:float,
     *   ac:float,
     *   sv:float,
     *   spi:float|null,
     *   cv:float,
     *   cpi:float|null,
     *   eac:float|null,
     *   etc:float|null,
     *   meta:array
     * }
     */
    public function computeForProjectDate(int $projectId, $date, ?int $baselineId = null): array;
}

