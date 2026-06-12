<?php

namespace App\Services\Contracts;

use App\Models\Task;
use App\Models\TaskCostEntry;

interface TaskCostEntryServiceInterface
{
    public function getCostEntriesByTask(Task $task, ?string $asOfDate = null, int $limit = 200);

    public function createCostEntry(Task $task, array $data, ?int $actorId = null);

    public function deleteCostEntry(Task $task, TaskCostEntry $costEntry, ?int $actorId = null): bool;
}
