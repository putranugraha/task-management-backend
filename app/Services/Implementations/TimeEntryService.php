<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\TimeEntryRepositoryInterface;
use App\Services\Contracts\TaskServiceInterface;
use App\Services\Contracts\TimeEntryServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\TimeEntry;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class TimeEntryService implements TimeEntryServiceInterface
{
    protected TimeEntryRepositoryInterface $repository;
    protected TaskServiceInterface $taskService;

    const CACHE_ALL = 'time_entries.all';
    const CACHE_ID_PREFIX = 'time_entry.'; // + id
    const CACHE_TASK_PREFIX = 'time_entries.task.'; // + taskId
    const CACHE_USER_PREFIX = 'time_entries.user.'; // + userId
    const CACHE_RANGE_PREFIX = 'time_entries.range.'; // + start_end
    const CACHE_TOTAL_TASK_PREFIX = 'time_entries.total.task.'; // + taskId
    const CACHE_TOTAL_USER_PREFIX = 'time_entries.total.user.'; // + userId
    const CACHE_DURATION = 900; // 15 minutes

    public function __construct(TimeEntryRepositoryInterface $repository, TaskServiceInterface $taskService)
    {
        $this->repository = $repository;
        $this->taskService = $taskService;
    }

    public function getAllTimeEntries()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, fn () => $this->repository->getAllTimeEntries());
    }

    public function getTimeEntryById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, fn () => $this->repository->getTimeEntryById($id));
    }

    public function getTimeEntriesByTask($taskId)
    {
        return Cache::remember(self::CACHE_TASK_PREFIX.$taskId, self::CACHE_DURATION, fn () => $this->repository->getTimeEntriesByTask($taskId));
    }

    public function getTimeEntriesByUser($userId)
    {
        return Cache::remember(self::CACHE_USER_PREFIX.$userId, self::CACHE_DURATION, fn () => $this->repository->getTimeEntriesByUser($userId));
    }

    public function getTimeEntriesByTaskAndUser($taskId, $userId)
    {
        return $this->repository->getTimeEntriesByTaskAndUser($taskId, $userId);
    }

    public function getTimeEntriesByDateRange($startDate, $endDate)
    {
        $key = self::CACHE_RANGE_PREFIX.$startDate.'_'.$endDate;
        return Cache::remember($key, self::CACHE_DURATION, fn () => $this->repository->getTimeEntriesByDateRange($startDate, $endDate));
    }

    public function paginateTimeEntries(array $filters = [], int $perPage = 20)
    {
        // Pagination tidak dicache agar sederhana dan menghindari kompleksitas key.
        return $this->repository->paginateTimeEntries($filters, $perPage);
    }

    public function createTimeEntry(array $data)
    {
         $taskId = (int) ($data['task_id'] ?? 0);

    $data['user_id'] = $this->authorizeTaskTimeEntry(
        $taskId,
        isset($data['user_id']) ? (int) $data['user_id'] : null
    );

    $data = $this->appendProgressToNote($data);

        
        if (isset($data['task_id'], $data['date'])) {
            $this->prepareTaskForTimeEntry((int) $data['task_id'], (string) $data['date']);
        }
        $row = $this->repository->createTimeEntry($data);
        $this->clearCaches($row->id ?? null, $row->task_id ?? null, $row->user_id ?? null);

        if ($row) {
            $actor = Auth::user();

            $properties = [
                'time_entry_id' => $row->id,
                'task_id' => $row->task_id,
                'user_id' => $row->user_id,
                'date' => $row->date,
                'hours' => $row->hours,
                'note' => $row->note,
            ];

            $activity = activity('time_entries')
                ->performedOn($row instanceof TimeEntry ? $row : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('created');
        }

        return $row;
    }

    /**
     * Create or update a time entry uniquely identified by (task_id, user_id, date).
     * Does not change table structure; leverages the unique index at DB level.
     */
    public function createOrUpdate(array $data)
    {
        // Normalise payload
        $taskId = (int) $data['task_id'];

        $userId = $this->authorizeTaskTimeEntry(
            $taskId,
            isset($data['user_id']) ? (int) $data['user_id'] : null
        );

        $data['user_id'] = $userId;

        $date = $data['date'];
        $hours = $data['hours'];
        $note = ($this->appendProgressToNote($data)['note']
            ?? ($data['note'] ?? null));

        $this->prepareTaskForTimeEntry((int) $taskId, (string) $date);

        $before = TimeEntry::where('task_id', $taskId)
            ->where('user_id', $userId)
            ->where('date', $date)
            ->first();

        $row = TimeEntry::updateOrCreate(
            [
                'task_id' => $taskId,
                'user_id' => $userId,
                'date' => $date,
            ],
            [
                'hours' => $hours,
                'note' => $note,
            ]
        );

        $this->clearCaches($row->id ?? null, $taskId, $userId);

        if ($row) {
            $actor = Auth::user();

            $properties = [
                'time_entry_id' => $row->id,
                'task_id' => $row->task_id,
                'user_id' => $row->user_id,
                'date' => $row->date,
                'hours_before' => $before->hours ?? null,
                'hours_after' => $row->hours,
                'note_before' => $before->note ?? null,
                'note_after' => $row->note,
            ];

            $activity = activity('time_entries')
                ->performedOn($row instanceof TimeEntry ? $row : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log($before ? 'upsert_updated' : 'upsert_created');
        }

        return $row;
    }

    public function updateTimeEntry($id, array $data)
    {
        $data = $this->appendProgressToNote($data);
        $before = $this->repository->getTimeEntryById($id);
        $row = $this->repository->updateTimeEntry($id, $data);
        $this->clearCaches($id, $row->task_id ?? null, $row->user_id ?? null);

        if ($row) {
            $actor = Auth::user();

            $properties = [
                'time_entry_id' => $row->id,
                'task_id' => $row->task_id,
                'user_id' => $row->user_id,
                'date' => $row->date,
                'hours_before' => $before->hours ?? null,
                'hours_after' => $row->hours,
                'note_before' => $before->note ?? null,
                'note_after' => $row->note,
            ];

            $activity = activity('time_entries')
                ->performedOn($row instanceof TimeEntry ? $row : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('updated');
        }

        return $row;
    }

    public function deleteTimeEntry($id)
    {
        $row = $this->repository->getTimeEntryById($id);
        $result = $this->repository->deleteTimeEntry($id);
        $this->clearCaches($id, $row->task_id ?? null, $row->user_id ?? null);

        if ($result && $row) {
            $actor = Auth::user();

            $properties = [
                'time_entry_id' => $row->id,
                'task_id' => $row->task_id,
                'user_id' => $row->user_id,
                'date' => $row->date,
                'hours' => $row->hours,
                'note' => $row->note,
            ];

            $activity = activity('time_entries')
                ->performedOn($row instanceof TimeEntry ? $row : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('deleted');
        }

        return $result;
    }

    public function getTotalHoursByTask($taskId)
    {
        return Cache::remember(self::CACHE_TOTAL_TASK_PREFIX.$taskId, self::CACHE_DURATION, fn () => $this->repository->getTotalHoursByTask($taskId));
    }

    public function getTotalHoursByUser($userId)
    {
        return Cache::remember(self::CACHE_TOTAL_USER_PREFIX.$userId, self::CACHE_DURATION, fn () => $this->repository->getTotalHoursByUser($userId));
    }

    public function getTotalHoursByProjectAsOf(int $projectId, string $asOfDate)
    {
        // Do not cache project aggregates to avoid stale results across tasks/time entries.
        return $this->repository->getTotalHoursByProjectAsOf($projectId, $asOfDate);
    }

    public function getTopTasksByHoursAsOf(int $projectId, string $asOfDate, int $limit = 5)
    {
        // Do not cache project aggregates to avoid stale results across tasks/time entries.
        return $this->repository->getTopTasksByHoursAsOf($projectId, $asOfDate, $limit);
    }

    public function startTaskForTimeEntry(
        int $taskId,
        string $entryDate
    ): void {
        $this->authorizeTaskTimeEntry($taskId);

        $this->prepareTaskForTimeEntry($taskId, $entryDate);
    }

    protected function clearCaches($id = null, $taskId = null, $userId = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id) Cache::forget(self::CACHE_ID_PREFIX.$id);
        if ($taskId) Cache::forget(self::CACHE_TASK_PREFIX.$taskId);
        if ($userId) Cache::forget(self::CACHE_USER_PREFIX.$userId);
        if ($taskId) Cache::forget(self::CACHE_TOTAL_TASK_PREFIX.$taskId);
        if ($userId) Cache::forget(self::CACHE_TOTAL_USER_PREFIX.$userId);
    }

    /**
     * Append a "progress XX%" tag into note when optional progress fields provided.
     * Does not alter structure; only enriches the note string.
     */
    protected function appendProgressToNote(array $data): array
    {
        $progress = $data['progress'] ?? $data['percent'] ?? $data['percent_complete'] ?? null;
        if ($progress === null) {
            return $data;
        }
        if (!is_numeric($progress)) {
            return $data;
        }
        $p = (int) $progress;
        if ($p < 0) $p = 0;
        if ($p > 100) $p = 100;

        $note = $data['note'] ?? '';
        $tag = "progress {$p}%";

        // Avoid duplicate tag if already present (case-insensitive contains)
        if ($note === null || $note === '') {
            $note = $tag;
        } else {
            $exists = stripos($note, 'progress') !== false && stripos($note, '%') !== false;
            if (!$exists) {
                $note = rtrim($note);
                $note .= (substr($note, -1) === '.' || substr($note, -1) === ';') ? ' ' : ' | ';
                $note .= $tag;
            }
        }

        $data['note'] = $note;
        return $data;
    }

    protected function authorizeTaskTimeEntry(
    int $taskId,
    ?int $requestedUserId = null): int {
        $actor = Auth::user();

        if (!$actor) {
            throw new AuthorizationException('User tidak terautentik.');
        }

        $actorId = (int) $actor->id;

        if (!$actor->can('mengisi entri waktu')) {
            throw new AuthorizationException(
                'Kamu tidak memiliki izin untuk mengisi entri waktu.'
            );
        }

        if ($requestedUserId !== null && $requestedUserId !== $actorId) {
            throw new AuthorizationException(
                'Kamu hanya dapat mencatat waktu untuk akunmu sendiri.'
            );
        }

        $isAssigned = DB::table('task_assignments')
            ->where('task_id', $taskId)
            ->where('user_id', $actorId)
            ->exists();

        if (!$isAssigned) {
            throw new AuthorizationException(
                'Kamu bukan assignee pada task ini, sehingga tidak dapat mencatat waktu.'
            );
        }

        return $actorId;
    }

    protected function prepareTaskForTimeEntry(int $taskId, string $entryDate): void
    {
        $task = Task::find($taskId);
        if (!$task) {
            return;
        }

        $terminal = ['Done', 'Cancelled', 'On Hold'];
        $dirty = false;

        if (!in_array($task->status, $terminal, true) && $task->status !== 'In Progress') {
            $this->taskService->updateTaskStatus($taskId, 'In Progress');
            $task->refresh();
        }

        if ($entryDate) {
            $entry = Carbon::parse($entryDate)->toDateString();
            if (!$task->start_actual) {
                $task->start_actual = $entry;
                $dirty = true;
            } else {
                $current = Carbon::parse($task->start_actual)->toDateString();
                if ($entry < $current) {
                    $task->start_actual = $entry;
                    $dirty = true;
                }
            }
        }

        if ($dirty) {
            $task->save();
        }
    }
}
