<?php

namespace App\Support;

use App\Models\StatusHistory;
use App\Models\Task;

class TaskHistoryLogger
{
    /**
     * Record a non-status task event into status_histories.
     *
     * We keep from_status/to_status aligned to the task's current status and
     * store the human-readable event in the note field.
     */
    public static function log(?Task $task, ?int $actorId, string $note): void
    {
        if (!$task) {
            return;
        }

        $status = $task->status ?? 'To Do';
        $note = trim((string) $note);
        if ($note === '') {
            $note = null;
        }

        StatusHistory::create([
            'task_id' => (int) $task->id,
            'from_status' => $status,
            'to_status' => $status,
            'changed_by' => $actorId,
            'note' => $note,
        ]);
    }

    public static function logByTaskId(?int $taskId, ?int $actorId, string $note): void
    {
        if (!$taskId) {
            return;
        }
        $task = Task::find($taskId);
        self::log($task, $actorId, $note);
    }
}

