<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\TimeEntryRepositoryInterface;
use App\Services\Contracts\TimeEntryServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\TimeEntry;

class TimeEntryService implements TimeEntryServiceInterface
{
    protected TimeEntryRepositoryInterface $repository;

    const CACHE_ALL = 'time_entries.all';
    const CACHE_ID_PREFIX = 'time_entry.'; // + id
    const CACHE_TASK_PREFIX = 'time_entries.task.'; // + taskId
    const CACHE_USER_PREFIX = 'time_entries.user.'; // + userId
    const CACHE_RANGE_PREFIX = 'time_entries.range.'; // + start_end
    const CACHE_TOTAL_TASK_PREFIX = 'time_entries.total.task.'; // + taskId
    const CACHE_TOTAL_USER_PREFIX = 'time_entries.total.user.'; // + userId
    const CACHE_DURATION = 900; // 15 minutes

    public function __construct(TimeEntryRepositoryInterface $repository)
    {
        $this->repository = $repository;
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
        $data = $this->appendProgressToNote($data);
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
        $taskId = $data['task_id'];
        $userId = $data['user_id'];
        $date = $data['date'];
        $hours = $data['hours'];
        $note = ($this->appendProgressToNote($data)['note'] ?? ($data['note'] ?? null));

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
}
