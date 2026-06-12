<?php

namespace App\Repositories\Contracts;

interface TaskCostEntryRepositoryInterface
{
    public function getCostEntriesByTask($taskId, ?string $asOfDate = null, int $limit = 200);

    public function createCostEntry(array $data);

    public function deleteCostEntry($id): bool;
}
