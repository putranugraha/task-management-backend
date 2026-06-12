<?php

namespace App\Services\Implementations;

use App\Models\Task;
use App\Models\TaskCostEntry;
use App\Repositories\Contracts\TaskCostEntryRepositoryInterface;
use App\Services\Contracts\TaskCostEntryServiceInterface;
use App\Support\TaskHistoryLogger;

class TaskCostEntryService implements TaskCostEntryServiceInterface
{
    protected TaskCostEntryRepositoryInterface $repository;

    public function __construct(TaskCostEntryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getCostEntriesByTask(Task $task, ?string $asOfDate = null, int $limit = 200)
    {
        return $this->repository->getCostEntriesByTask($task->id, $asOfDate, $limit);
    }

    public function createCostEntry(Task $task, array $data, ?int $actorId = null)
    {
        $data['task_id'] = $task->id;

        $row = $this->repository->createCostEntry($data);
        if ($row instanceof TaskCostEntry) {
            TaskHistoryLogger::log($task, $actorId, $this->historyNote('Cost entry ditambahkan', $row));
        }

        return $row;
    }

    public function deleteCostEntry(Task $task, TaskCostEntry $costEntry, ?int $actorId = null): bool
    {
        if ((int) $costEntry->task_id !== (int) $task->id) {
            return false;
        }

        $note = $this->historyNote('Cost entry dihapus', $costEntry);
        $deleted = $this->repository->deleteCostEntry($costEntry->id);

        if ($deleted) {
            TaskHistoryLogger::log($task, $actorId, $note);
        }

        return $deleted;
    }

    protected function historyNote(string $prefix, TaskCostEntry $row): string
    {
        $date = $row->incurred_on ? $row->incurred_on->format('Y-m-d') : null;
        $amount = $row->amount ?? null;
        $category = $row->category ?? null;

        $note = $prefix.($date ? (': '.$date) : '');
        if ($amount !== null) {
            $note .= ' (amount: '.$amount.')';
        }
        if ($category) {
            $note .= ' (kategori: '.$category.')';
        }

        return $note;
    }
}
