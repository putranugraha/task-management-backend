<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\Contracts\ProjectBaselineServiceInterface;
use App\Services\Contracts\TaskServiceInterface;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Models\StatusHistory;
use App\Models\Task;
use App\Models\TaskProgressEntry;
use App\Models\Milestone;
use App\Notifications\TaskActivityNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;
use Carbon\Carbon;

class TaskService implements TaskServiceInterface
{
    protected TaskRepositoryInterface $repository;
    protected ProjectBaselineServiceInterface $projectBaselineService;

    const CACHE_ALL = 'tasks.all';
    const CACHE_ID_PREFIX = 'task.'; // + id
    const CACHE_STATUS_PREFIX = 'tasks.status.'; // + status
    const CACHE_PROJECT_PREFIX = 'tasks.project.'; // + projectId
    const CACHE_MILESTONE_PREFIX = 'tasks.milestone.'; // + milestoneId
    const CACHE_PRIORITY_PREFIX = 'tasks.priority.'; // + priority
    const CACHE_DURATION = 1800; // 30 minutes

    // Allowed statuses for tasks
    const ALLOWED_STATUSES = ['To Do', 'In Progress', 'Done', 'On Hold', 'Cancelled'];
    const ALLOWED_PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];
    /**
     * Statuses considered "done" for milestone auto-completion checks.
     *
     * @var list<string>
     */
    const DONE_STATUSES_FOR_MILESTONE = ['Done'];

    public function __construct(TaskRepositoryInterface $repository, ProjectBaselineServiceInterface $projectBaselineService)
    {
        $this->repository = $repository;
        $this->projectBaselineService = $projectBaselineService;
    }

    protected function recordProgressEntryForToday(Task $task): void
    {
        $date = Carbon::today()->toDateString();
        $pct = (int) ($task->percent_complete ?? 0);
        if ($pct < 0) $pct = 0;
        if ($pct > 100) $pct = 100;

        TaskProgressEntry::updateOrCreate(
            ['task_id' => (int) $task->id, 'progress_date' => $date],
            ['percent_complete' => $pct, 'changed_by' => Auth::id()]
        );
    }

    public function getAllTasks()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, fn () => $this->repository->getAllTasks());
    }

    public function getTaskById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, fn () => $this->repository->getTaskById($id));
    }

    public function getTasksByProject($projectId)
    {
        return Cache::remember(self::CACHE_PROJECT_PREFIX.$projectId, self::CACHE_DURATION, fn () => $this->repository->getTasksByProject($projectId));
    }

    public function getTasksByMilestone($milestoneId)
    {
        return Cache::remember(self::CACHE_MILESTONE_PREFIX.$milestoneId, self::CACHE_DURATION, fn () => $this->repository->getTasksByMilestone($milestoneId));
    }

    public function getTasksByStatus($status)
    {
        return Cache::remember(self::CACHE_STATUS_PREFIX.$status, self::CACHE_DURATION, fn () => $this->repository->getTasksByStatus($status));
    }

    public function getTasksByPriority($priority)
    {
        return Cache::remember(self::CACHE_PRIORITY_PREFIX.$priority, self::CACHE_DURATION, fn () => $this->repository->getTasksByPriority($priority));
    }

    public function getTasksByPlannedDateRange($startDate, $endDate)
    {
        return $this->repository->getTasksByPlannedDateRange($startDate, $endDate);
    }

    public function getTasksByActualDateRange($startDate, $endDate)
    {
        return $this->repository->getTasksByActualDateRange($startDate, $endDate);
    }

    public function getTasksByDependsOnTask($dependsOnTaskId)
    {
        return $this->repository->getTasksByDependsOnTask($dependsOnTaskId);
    }

    public function paginateTasks(array $filters = [], int $perPage = 20)
    {
        // Untuk saat ini pagination tidak dicache supaya sederhana dan
        // menghindari kompleksitas key per kombinasi filter/page.
        return $this->repository->paginateTasks($filters, $perPage);
    }

    public function getArchivedTasks(array $filters = [], int $perPage = 20)
    {
        return $this->repository->getArchivedTasks($filters, $perPage);
    }

    /**
     * Hitung statistik task (total, completed, in_progress) berdasarkan filter sederhana.
     *
     * Completed didefinisikan sebagai status "Done".
     * In progress didefinisikan sebagai status "In Progress".
     *
     * @param array $filters
     * @return array{total:int,completed:int,in_progress:int}
     */
    public function getTaskStats(array $filters = []): array
    {
        $counts = $this->repository->getTaskStatusCounts($filters);

        $total = $counts['total'] ?? 0;
        $byStatus = $counts['by_status'] ?? [];

        $completed = $byStatus['Done'] ?? 0;
        $inProgress = $byStatus['In Progress'] ?? 0;

        return [
            'total' => (int) $total,
            'completed' => (int) $completed,
            'in_progress' => (int) $inProgress,
        ];
    }

    public function createTask(array $data)
    {
        $requestedStatus = isset($data['status']) && is_string($data['status'])
            ? $data['status']
            : null;
        $assignments = $data['assignments'] ?? null;
        $dependencies = $data['dependencies'] ?? null;
        unset($data['assignments']);
        unset($data['dependencies']);

        if ($requestedStatus !== null && in_array($requestedStatus, ['In Progress', 'Done'], true)) {
            $this->assertDependencyPayloadTransitionAllowed($dependencies, $requestedStatus);
        }

        $this->assertDependencyScheduleAllowed(
            $dependencies,
            $data['start_planned'] ?? null,
            $data['end_planned'] ?? null
        );

        $task = null;
        DB::transaction(function () use (&$task, $data, $assignments, $dependencies) {
            $task = $this->repository->createTask($data);
            if ($task && is_array($assignments) && !empty($assignments)) {
                $rows = [];
                foreach ($assignments as $a) {
                    $rows[] = [
                        'user_id' => $a['user_id'],
                        'role_on_task' => $a['role_on_task'] ?? null,
                        'estimated_effort_hours' => $a['estimated_effort_hours'] ?? null,
                        'assigned_at' => now(),
                    ];
                }
                if (!empty($rows)) {
                    $task->assignments()->createMany($rows);
                }
            }

            if ($task && is_array($dependencies) && !empty($dependencies)) {
                $depRows = [];
                foreach ($dependencies as $d) {
                    $dependsId = $d['depends_on_task_id'] ?? null;
                    if (!$dependsId || $dependsId == $task->id) continue; // prevent self-dependency
                    $depRows[] = [
                        'depends_on_task_id' => $dependsId,
                        'type' => $d['type'] ?? 'FS',
                        'lag_days' => $d['lag_days'] ?? 0,
                    ];
                }
                if (!empty($depRows)) {
                    $task->dependencies()->createMany($depRows);
                }
            }
        });

        // Eager-load minimal relations for immediate response
        if ($task) {
            $task->loadMissing(['project', 'milestone', 'assignments.user', 'dependencies.dependsOn']);

            // Ensure progress history has a baseline snapshot for today (even when created with non-zero progress).
            $this->recordProgressEntryForToday($task);

            // Ensure a project baseline exists for brand-new projects only.
            // Existing baselines are immutable snapshots; new tasks must not be inserted into them automatically.
            $latest = $this->projectBaselineService->getLatestBaselineByProject($task->project_id);
            if (!$latest) {
                $this->projectBaselineService->createBaseline([
                    'project_id' => $task->project_id,
                    'baseline_name' => 'Initial Baseline',
                    'taken_at' => Carbon::now(),
                ]);
            }
        }

        $this->clearCaches($task->id ?? null, $task->status ?? null, $task->project_id ?? null, $task->priority ?? null, $task->milestone_id ?? null);

        if ($task) {
            // Notify all assignees for a freshly created task.
            $this->notifyAssigneesForTask($task, []);

            $actor = Auth::user();

            $properties = [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'milestone_id' => $task->milestone_id,
                'title' => $task->title,
                'priority' => $task->priority,
                'status' => $task->status,
                'percent_complete' => $task->percent_complete,
            ];

            $activity = activity('tasks')
                ->performedOn($task instanceof Task ? $task : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('created');
        }

        return $task;
    }

    public function updateTask($id, array $data)
    {
        $currentTask = null;
        if (array_key_exists('status', $data) && is_string($data['status'])) {
            $currentTask = $this->repository->getTaskById($id);
            if ($currentTask instanceof Task) {
                $requestedDependencies = $data['dependencies'] ?? null;
                if ($requestedDependencies !== null) {
                    $this->assertDependencyPayloadTransitionAllowed($requestedDependencies, $data['status']);
                } else {
                    $this->assertDependencyStatusTransitionAllowed($currentTask, $data['status']);
                }
            }
        }

        if (
            array_key_exists('dependencies', $data) ||
            array_key_exists('start_planned', $data) ||
            array_key_exists('end_planned', $data)
        ) {
            $currentTask = $currentTask instanceof Task ? $currentTask : $this->repository->getTaskById($id);
            if ($currentTask instanceof Task) {
                $dependencyPayload = $data['dependencies'] ?? null;
                if ($dependencyPayload === null) {
                    $currentTask->loadMissing('dependencies');
                    $dependencyPayload = $currentTask->dependencies ?? [];
                }

                $this->assertDependencyScheduleAllowed(
                    $dependencyPayload,
                    array_key_exists('start_planned', $data) ? $data['start_planned'] : $currentTask->start_planned,
                    array_key_exists('end_planned', $data) ? $data['end_planned'] : $currentTask->end_planned,
                    (int) $currentTask->id
                );
            }
        }

        $assignments = $data['assignments'] ?? null;
        $dependencies = $data['dependencies'] ?? null;
        unset($data['assignments']);
        unset($data['dependencies']);

        $before = $this->repository->getTaskById($id);
        $previousUserIds = [];
        if ($before && method_exists($before, 'relationLoaded') && $before->relationLoaded('assignments')) {
            foreach ($before->assignments as $a) {
                if ($a && $a->user_id) {
                    $previousUserIds[] = (int) $a->user_id;
                }
            }
        }

        $task = null;
        DB::transaction(function () use (&$task, $id, $data, $assignments, $dependencies) {
            $task = $this->repository->updateTask($id, $data);
            if ($task !== null && $assignments !== null) {
                // Replace strategy: delete existing, insert provided
                $task->assignments()->delete();
                if (is_array($assignments) && !empty($assignments)) {
                    $rows = [];
                    foreach ($assignments as $a) {
                        $rows[] = [
                            'user_id' => $a['user_id'],
                            'role_on_task' => $a['role_on_task'] ?? null,
                            'estimated_effort_hours' => $a['estimated_effort_hours'] ?? null,
                            'assigned_at' => now(),
                        ];
                    }
                    if (!empty($rows)) {
                        $task->assignments()->createMany($rows);
                    }
                }
            }

            if ($task !== null && $dependencies !== null) {
                // Replace dependencies if key provided
                $task->dependencies()->delete();
                if (is_array($dependencies) && !empty($dependencies)) {
                    $depRows = [];
                    foreach ($dependencies as $d) {
                        $dependsId = $d['depends_on_task_id'] ?? null;
                        if (!$dependsId || $dependsId == $task->id) continue; // prevent self-dependency
                        $depRows[] = [
                            'depends_on_task_id' => $dependsId,
                            'type' => $d['type'] ?? 'FS',
                            'lag_days' => $d['lag_days'] ?? 0,
                        ];
                    }
                    if (!empty($depRows)) {
                        $task->dependencies()->createMany($depRows);
                    }
                }
            }
        });

        if ($task) {
            $task->loadMissing(['project', 'milestone', 'assignments.user', 'dependencies.dependsOn']);
        }

        $this->clearCaches($id, $task->status ?? null, $task->project_id ?? null, $task->priority ?? null, $task->milestone_id ?? null);

        if ($task) {
            // Create status history entry when task is updated (status change OR details change).
            if ($before) {
                $noteParts = [];

                $beforeStatus = $before->status ?? null;
                $afterStatus = $task->status ?? null;

                $stringifyDate = function ($value) {
                    if ($value instanceof \Carbon\Carbon) {
                        return $value->toDateString();
                    }
                    if ($value instanceof \DateTimeInterface) {
                        return $value->format('Y-m-d');
                    }
                    if (is_string($value) && $value !== '') {
                        // Some fields may already be strings (e.g., from JSON resource).
                        return $value;
                    }
                    return $value;
                };

                $norm = function ($value) use ($stringifyDate) {
                    $value = $stringifyDate($value);
                    if (is_bool($value)) return $value ? '1' : '0';
                    if ($value === null) return null;
                    if (is_numeric($value)) {
                        // Normalize numeric strings like "10.00" to "10" for comparisons.
                        $s = (string) $value;
                        if (str_contains($s, '.')) {
                            $s = rtrim(rtrim($s, '0'), '.');
                        }
                        return $s;
                    }
                    if (is_string($value)) {
                        $t = trim($value);
                        return $t === '' ? null : $t;
                    }
                    return (string) $value;
                };

                $pushChange = function (string $label, $beforeVal, $afterVal) use (&$noteParts, $norm) {
                    $b = $norm($beforeVal);
                    $a = $norm($afterVal);
                    if ($b === $a) return;

                    // Avoid storing full description diffs in history notes.
                    if (strtolower($label) === 'deskripsi') {
                        $noteParts[] = 'Deskripsi diperbarui';
                        return;
                    }

                    $short = function ($v) {
                        if ($v === null) return '-';
                        $s = (string) $v;
                        $s = preg_replace('/\s+/', ' ', $s ?? '');
                        if (strlen($s) > 80) {
                            $s = substr($s, 0, 77).'...';
                        }
                        return $s;
                    };

                    $noteParts[] = $label.': '.$short($b).' -> '.$short($a);
                };

                $pushChange('Project', $before->project_id ?? null, $task->project_id ?? null);
                $pushChange('Milestone', $before->milestone_id ?? null, $task->milestone_id ?? null);
                $pushChange('Judul', $before->title ?? null, $task->title ?? null);
                $pushChange('Deskripsi', $before->description ?? null, $task->description ?? null);
                $pushChange('Prioritas', $before->priority ?? null, $task->priority ?? null);
                $pushChange('Start Planned', $before->start_planned ?? null, $task->start_planned ?? null);
                $pushChange('End Planned', $before->end_planned ?? null, $task->end_planned ?? null);
                $pushChange('Progress', (int) ($before->percent_complete ?? 0), (int) ($task->percent_complete ?? 0));
                $pushChange('Budget', $before->budget_cost ?? null, $task->budget_cost ?? null);

                if ($assignments !== null) {
                    $beforeAssignees = collect($before->assignments ?? [])
                        ->map(function ($a) {
                            return [
                                'user_id' => (int) ($a->user_id ?? 0),
                                'role_on_task' => $a->role_on_task ?? null,
                                'estimated_effort_hours' => $a->estimated_effort_hours ?? null,
                            ];
                        })
                        ->filter(fn ($a) => ($a['user_id'] ?? 0) > 0)
                        ->sortBy('user_id')
                        ->values()
                        ->all();

                    $afterAssignees = collect($task->assignments ?? [])
                        ->map(function ($a) {
                            return [
                                'user_id' => (int) ($a->user_id ?? 0),
                                'role_on_task' => $a->role_on_task ?? null,
                                'estimated_effort_hours' => $a->estimated_effort_hours ?? null,
                            ];
                        })
                        ->filter(fn ($a) => ($a['user_id'] ?? 0) > 0)
                        ->sortBy('user_id')
                        ->values()
                        ->all();

                    if (json_encode($beforeAssignees) !== json_encode($afterAssignees)) {
                        $beforeIds = collect($beforeAssignees)->pluck('user_id')->values()->all();
                        $afterIds = collect($afterAssignees)->pluck('user_id')->values()->all();
                        $noteParts[] = 'Assignee: ['.implode(',', $beforeIds).'] -> ['.implode(',', $afterIds).']';
                    }
                }

                if ($dependencies !== null) {
                    $beforeDeps = collect($before->dependencies ?? [])
                        ->map(function ($d) {
                            return [
                                'depends_on_task_id' => (int) ($d->depends_on_task_id ?? ($d->dependsOn->id ?? 0)),
                                'type' => (string) ($d->type ?? 'FS'),
                                'lag_days' => (int) ($d->lag_days ?? 0),
                            ];
                        })
                        ->filter(fn ($d) => ($d['depends_on_task_id'] ?? 0) > 0)
                        ->sortBy(function ($d) {
                            return sprintf('%010d|%s|%06d', (int) ($d['depends_on_task_id'] ?? 0), (string) ($d['type'] ?? ''), (int) ($d['lag_days'] ?? 0));
                        })
                        ->values()
                        ->all();

                    $afterDeps = collect($task->dependencies ?? [])
                        ->map(function ($d) {
                            return [
                                'depends_on_task_id' => (int) ($d->depends_on_task_id ?? ($d->dependsOn->id ?? 0)),
                                'type' => (string) ($d->type ?? 'FS'),
                                'lag_days' => (int) ($d->lag_days ?? 0),
                            ];
                        })
                        ->filter(fn ($d) => ($d['depends_on_task_id'] ?? 0) > 0)
                        ->sortBy(function ($d) {
                            return sprintf('%010d|%s|%06d', (int) ($d['depends_on_task_id'] ?? 0), (string) ($d['type'] ?? ''), (int) ($d['lag_days'] ?? 0));
                        })
                        ->values()
                        ->all();

                    if (json_encode($beforeDeps) !== json_encode($afterDeps)) {
                        $beforeIds = collect($beforeDeps)->pluck('depends_on_task_id')->values()->all();
                        $afterIds = collect($afterDeps)->pluck('depends_on_task_id')->values()->all();
                        $noteParts[] = 'Dependency: ['.implode(',', $beforeIds).'] -> ['.implode(',', $afterIds).']';
                    }
                }

                $statusChanged = ($beforeStatus !== $afterStatus);
                $hasDetailChanges = !empty($noteParts) || $statusChanged;

                if ($hasDetailChanges) {
                    $note = null;
                    if (!empty($noteParts)) {
                        $note = implode('; ', $noteParts);
                        if (strlen($note) > 1500) {
                            $note = substr($note, 0, 1497).'...';
                        }
                    }

                    StatusHistory::create([
                        'task_id' => $task->id,
                        'from_status' => $statusChanged ? $beforeStatus : ($afterStatus ?? $beforeStatus),
                        'to_status' => $afterStatus ?? ($beforeStatus ?? 'To Do'),
                        'changed_by' => Auth::id(),
                        'note' => $note,
                    ]);
                }
            }

            // Notify only users who are newly assigned compared to the previous snapshot.
            $this->notifyAssigneesForTask($task, $previousUserIds);

            if ((int) ($before->percent_complete ?? 0) !== (int) ($task->percent_complete ?? 0)) {
                $this->recordProgressEntryForToday($task);
            }

            $actor = Auth::user();

            $properties = [
                'task_id' => $task->id,
                'project_id_before' => $before->project_id ?? null,
                'project_id_after' => $task->project_id,
                'milestone_id_before' => $before->milestone_id ?? null,
                'milestone_id_after' => $task->milestone_id,
                'title_before' => $before->title ?? null,
                'title_after' => $task->title,
                'priority_before' => $before->priority ?? null,
                'priority_after' => $task->priority,
                'status_before' => $before->status ?? null,
                'status_after' => $task->status,
                'percent_complete_before' => $before->percent_complete ?? null,
                'percent_complete_after' => $task->percent_complete,
            ];

            $activity = activity('tasks')
                ->performedOn($task instanceof Task ? $task : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('updated');

            $this->syncMilestoneCompletion($task);
        }

        return $task;
    }

    public function deleteTask($id)
    {
        $task = $this->getTaskById($id);
        $result = $this->repository->deleteTask($id);
        $this->clearCaches($id, $task->status ?? null, $task->project_id ?? null, $task->priority ?? null, $task->milestone_id ?? null);

        if ($result && $task) {
            $actor = Auth::user();

            $properties = [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'milestone_id' => $task->milestone_id,
                'title' => $task->title,
                'priority' => $task->priority,
                'status' => $task->status,
                'percent_complete' => $task->percent_complete,
            ];

            $activity = activity('tasks')
                ->performedOn($task instanceof Task ? $task : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('archived');
        }

        return $result;
    }

    public function restoreTask($id)
    {
        $task = $this->repository->restoreTask($id);
        $this->clearCaches($id, $task->status ?? null, $task->project_id ?? null, $task->priority ?? null, $task->milestone_id ?? null);

        if ($task) {
            $actor = Auth::user();

            $properties = [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'milestone_id' => $task->milestone_id,
                'title' => $task->title,
                'priority' => $task->priority,
                'status' => $task->status,
                'percent_complete' => $task->percent_complete,
            ];

            $activity = activity('tasks')
                ->performedOn($task instanceof Task ? $task : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('restored');
        }

        return $task;
    }

    public function updateTaskStatus($id, $status)
    {
        if (!in_array($status, self::ALLOWED_STATUSES)) return null;
        $before = $this->getTaskById($id);
        if ($before instanceof Task) {
            $this->assertDependencyStatusTransitionAllowed($before, $status);
        }
        $task = $this->repository->updateTaskStatus($id, $status);
        $this->clearCaches(
            $id,
            $status,
            $task->project_id ?? null,
            $task->priority ?? null,
            $task->milestone_id ?? null,
        );
        if ($task) {
            $changedBy = Auth::id();

            StatusHistory::create([
                'task_id' => $task->id,
                'from_status' => $before->status ?? null,
                'to_status' => $task->status,
                'changed_by' => $changedBy,
                'note' => null,
            ]);

            $actor = Auth::user();

            $properties = [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'milestone_id' => $task->milestone_id,
                'status_before' => $before->status ?? null,
                'status_after' => $task->status,
            ];

            $activity = activity('tasks')
                ->performedOn($task instanceof Task ? $task : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('status_changed');

            if (($before->status ?? null) !== ($task->status ?? null)) {
                $this->notifyTaskWatchers($task, 'task_status_changed', [
                    'status_before' => $before->status ?? null,
                    'status_after' => $task->status,
                    'message' => 'Status task '.$task->title.' berubah dari '.($before->status ?? '-').' menjadi '.$task->status.'.',
                ]);
            }

            $this->syncMilestoneCompletion($task);
        }
        return $task;
    }

    public function updateTaskProgress($id, $percent)
    {
        if (!is_numeric($percent) || $percent < 0 || $percent > 100) return null;

        $nextPercent = (int) $percent;
        $before = $this->getTaskById($id);
        if ($before instanceof Task) {
            $currentPercent = (int) ($before->percent_complete ?? 0);
            if ($nextPercent >= 100 && $before->status !== 'Done') {
                $this->assertDependencyStatusTransitionAllowed($before, 'Done');
            } elseif ($nextPercent > $currentPercent && $nextPercent > 0) {
                $this->assertDependencyStatusTransitionAllowed($before, 'In Progress');
            }
        }

        // Finishing progress must use the same domain operation as the complete
        // action so status, end_actual, project completion, and dependencies
        // remain consistent.
        $task = $nextPercent >= 100
            ? $this->repository->completeTask($id)
            : $this->repository->updateTaskProgress($id, $nextPercent);

        $this->clearCaches(
            $id,
            $task->status ?? null,
            $task->project_id ?? null,
            $task->priority ?? null,
            $task->milestone_id ?? null,
        );

        if ($task) {
            $this->recordProgressEntryForToday($task);

            // Record progress update in status history so it appears in "Status History" feed.
            $beforePct = (int) ($before->percent_complete ?? 0);
            $afterPct = (int) ($task->percent_complete ?? 0);
            $beforeStatus = $before->status ?? null;
            $afterStatus = $task->status ?? $beforeStatus;
            if ($before && ($beforePct !== $afterPct || $beforeStatus !== $afterStatus)) {
                StatusHistory::create([
                    'task_id' => $task->id,
                    'from_status' => $beforeStatus,
                    'to_status' => $afterStatus,
                    'changed_by' => Auth::id(),
                    'note' => 'Progress: '.$beforePct.' -> '.$afterPct,
                ]);
            }

            $actor = Auth::user();

            $properties = [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'milestone_id' => $task->milestone_id,
                'percent_complete_before' => $before->percent_complete ?? null,
                'percent_complete_after' => $task->percent_complete,
                'status_before' => $beforeStatus,
                'status_after' => $afterStatus,
            ];

            $activity = activity('tasks')
                ->performedOn($task instanceof Task ? $task : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('progress_updated');

            if ($before && $beforePct !== $afterPct) {
                $this->notifyTaskWatchers($task, 'task_progress_updated', [
                    'percent_before' => $beforePct,
                    'percent_after' => $afterPct,
                    'message' => 'Progress task '.$task->title.' berubah dari '.$beforePct.'% menjadi '.$afterPct.'%.',
                ]);
            }

            if ($before && $beforeStatus !== $afterStatus) {
                $this->notifyTaskWatchers($task, 'task_status_changed', [
                    'status_before' => $beforeStatus,
                    'status_after' => $afterStatus,
                    'message' => 'Status task '.$task->title.' berubah dari '.($beforeStatus ?? '-').' menjadi '.$afterStatus.'.',
                ]);
            }

            $this->syncMilestoneCompletion($task);
        }

        return $task;
    }

    public function completeTask($id)
    {
        $before = $this->getTaskById($id);
        if ($before instanceof Task) {
            $this->assertDependencyStatusTransitionAllowed($before, 'Done');
        }
        $task = $this->repository->completeTask($id);
        $this->clearCaches(
            $id,
            $task->status ?? null,
            $task->project_id ?? null,
            $task->priority ?? null,
            $task->milestone_id ?? null,
        );
        if ($task) {
            $this->recordProgressEntryForToday($task);

            StatusHistory::create([
                'task_id' => $task->id,
                'from_status' => $before->status ?? null,
                'to_status' => $task->status,
                'changed_by' => Auth::id(),
                'note' => 'Completed via action',
            ]);

            $actor = Auth::user();

            $properties = [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'milestone_id' => $task->milestone_id,
                'status_before' => $before->status ?? null,
                'status_after' => $task->status,
            ];

            $activity = activity('tasks')
                ->performedOn($task instanceof Task ? $task : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('completed');

            if (($before->status ?? null) !== ($task->status ?? null)) {
                $this->notifyTaskWatchers($task, 'task_status_changed', [
                    'status_before' => $before->status ?? null,
                    'status_after' => $task->status,
                    'percent_before' => $before->percent_complete ?? null,
                    'percent_after' => $task->percent_complete,
                    'message' => 'Task '.$task->title.' sudah diselesaikan.',
                ]);
            }

            $this->syncMilestoneCompletion($task);
        }
        return $task;
    }

    /**
     * Enforce dependency rules consistently across all completion/status update paths.
     *
     * FE already applies a similar check in the edit form, but backend enforcement is
     * still required because task completion can also happen from other flows such as
     * attachment approval and direct status endpoints.
     */
    protected function assertDependencyStatusTransitionAllowed(Task $task, string $targetStatus): void
    {
        $targetStatus = trim($targetStatus);
        if (!in_array($targetStatus, ['In Progress', 'Done'], true)) {
            return;
        }

        $task->loadMissing('dependencies.dependsOn');
        $dependencies = collect($task->dependencies ?? []);
        if ($dependencies->isEmpty()) {
            return;
        }

        $unmet = [];

        foreach ($dependencies as $dependency) {
            $type = strtoupper((string) ($dependency->type ?? 'FS'));
            $predecessor = $dependency->dependsOn;
            $predecessorId = (int) ($dependency->depends_on_task_id ?? 0);
            $predecessorTitle = $predecessor->title ?? ('#'.$predecessorId);
            $predecessorStatus = trim((string) ($predecessor->status ?? ''));

            $isDone = $predecessorStatus === 'Done';
            $isStarted = $predecessorStatus !== '' && $predecessorStatus !== 'To Do';

            if ($targetStatus === 'Done') {
                if (in_array($type, ['FS', 'FF'], true) && !$isDone) {
                    $unmet[] = $predecessorTitle.' ('.$type.': harus selesai lebih dulu)';
                    continue;
                }
                if ($type === 'SF' && !$isStarted) {
                    $unmet[] = $predecessorTitle.' ('.$type.': harus sudah mulai lebih dulu)';
                    continue;
                }
            }

            if ($targetStatus === 'In Progress') {
                if ($type === 'FS' && !$isDone) {
                    $unmet[] = $predecessorTitle.' ('.$type.': harus selesai lebih dulu)';
                    continue;
                }
                if ($type === 'SS' && !$isStarted) {
                    $unmet[] = $predecessorTitle.' ('.$type.': harus sudah mulai lebih dulu)';
                    continue;
                }
            }

            $lagViolation = $this->dependencyLagStatusViolation($type, (int) ($dependency->lag_days ?? 0), $predecessor, $targetStatus);
            if ($lagViolation !== null) {
                $unmet[] = $predecessorTitle.' ('.$type.': '.$lagViolation.')';
                continue;
            }
        }

        if (empty($unmet)) {
            return;
        }

        $message = 'Tidak bisa mengubah status task karena masih menunggu dependency: '.implode(', ', $unmet);

        throw new HttpResponseException(
            response()->json([
                'message' => $message,
                'errors' => [
                    'status' => [$message],
                ],
            ], 422)
        );
    }

    /**
     * Validate status transition against a dependency payload that will replace
     * the current dependency set in the same request.
     *
     * @param mixed $dependencies
     */
    protected function assertDependencyPayloadTransitionAllowed($dependencies, string $targetStatus): void
    {
        $targetStatus = trim($targetStatus);
        if (!in_array($targetStatus, ['In Progress', 'Done'], true)) {
            return;
        }

        if (!is_array($dependencies) || empty($dependencies)) {
            return;
        }

        $dependsOnIds = collect($dependencies)
            ->map(fn ($d) => (int) ($d['depends_on_task_id'] ?? 0))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($dependsOnIds->isEmpty()) {
            return;
        }

        $predecessors = Task::query()
            ->whereIn('id', $dependsOnIds->all())
            ->get(['id', 'title', 'status', 'start_planned', 'end_planned', 'start_actual', 'end_actual'])
            ->keyBy('id');

        $unmet = [];

        foreach ($dependencies as $dependency) {
            $dependsOnId = (int) ($dependency['depends_on_task_id'] ?? 0);
            if ($dependsOnId <= 0) {
                continue;
            }

            $type = strtoupper((string) ($dependency['type'] ?? 'FS'));
            $predecessor = $predecessors->get($dependsOnId);
            $predecessorTitle = $predecessor?->title ?? ('#'.$dependsOnId);
            $predecessorStatus = trim((string) ($predecessor?->status ?? ''));
            $isDone = $predecessorStatus === 'Done';
            $isStarted = $predecessorStatus !== '' && $predecessorStatus !== 'To Do';

            if ($targetStatus === 'Done') {
                if (in_array($type, ['FS', 'FF'], true) && !$isDone) {
                    $unmet[] = $predecessorTitle.' ('.$type.': harus selesai lebih dulu)';
                    continue;
                }
                if ($type === 'SF' && !$isStarted) {
                    $unmet[] = $predecessorTitle.' ('.$type.': harus sudah mulai lebih dulu)';
                    continue;
                }
            }

            if ($targetStatus === 'In Progress') {
                if ($type === 'FS' && !$isDone) {
                    $unmet[] = $predecessorTitle.' ('.$type.': harus selesai lebih dulu)';
                    continue;
                }
                if ($type === 'SS' && !$isStarted) {
                    $unmet[] = $predecessorTitle.' ('.$type.': harus sudah mulai lebih dulu)';
                    continue;
                }
            }

            $lagViolation = $this->dependencyLagStatusViolation(
                $type,
                (int) ($dependency['lag_days'] ?? 0),
                $predecessor,
                $targetStatus
            );
            if ($lagViolation !== null) {
                $unmet[] = $predecessorTitle.' ('.$type.': '.$lagViolation.')';
                continue;
            }
        }

        if (empty($unmet)) {
            return;
        }

        $message = 'Tidak bisa mengubah status task karena masih menunggu dependency: '.implode(', ', $unmet);

        throw new HttpResponseException(
            response()->json([
                'message' => $message,
                'errors' => [
                    'status' => [$message],
                ],
            ], 422)
        );
    }

    protected function dependencyLagStatusViolation(string $type, int $lagDays, ?Task $predecessor, string $targetStatus): ?string
    {
        if (!$predecessor || $lagDays === 0 || !in_array($targetStatus, ['In Progress', 'Done'], true)) {
            return null;
        }

        $type = strtoupper($type);
        $anchor = null;
        $anchorLabel = null;

        if (in_array($type, ['FS', 'FF'], true)) {
            $anchor = $this->parseDependencyDate($predecessor->end_actual)
                ?? $this->parseDependencyDate($predecessor->end_planned);
            $anchorLabel = 'finish predecessor';
        } elseif (in_array($type, ['SS', 'SF'], true)) {
            $anchor = $this->parseDependencyDate($predecessor->start_actual)
                ?? $this->parseDependencyDate($predecessor->start_planned);
            $anchorLabel = 'start predecessor';
        }

        if (!$anchor) {
            return null;
        }

        $allowedAt = $anchor->copy()->addDays($lagDays);
        if (Carbon::today()->lt($allowedAt)) {
            return sprintf(
                'lag %d hari dari %s belum terpenuhi, minimal %s',
                $lagDays,
                $anchorLabel,
                $allowedAt->toDateString()
            );
        }

        return null;
    }

    /**
     * Validate planned dates against dependency relation type and lag.
     *
     * FS: successor start >= predecessor finish + lag
     * SS: successor start >= predecessor start + lag
     * FF: successor finish >= predecessor finish + lag
     * SF: successor finish >= predecessor start + lag
     *
     * @param mixed $dependencies
     */
    protected function assertDependencyScheduleAllowed($dependencies, $successorStart, $successorEnd, ?int $successorId = null): void
    {
        if (!is_array($dependencies) && !($dependencies instanceof \Illuminate\Support\Collection)) {
            return;
        }

        $items = collect($dependencies)->filter();
        if ($items->isEmpty()) {
            return;
        }

        $successorStartDate = $this->parseDependencyDate($successorStart);
        $successorEndDate = $this->parseDependencyDate($successorEnd);

        if (!$successorStartDate && !$successorEndDate) {
            return;
        }

        $dependsOnIds = $items
            ->map(function ($dependency) {
                if (is_array($dependency)) {
                    return (int) ($dependency['depends_on_task_id'] ?? 0);
                }
                return (int) ($dependency->depends_on_task_id ?? 0);
            })
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($dependsOnIds->isEmpty()) {
            return;
        }

        $predecessors = Task::query()
            ->whereIn('id', $dependsOnIds->all())
            ->get(['id', 'title', 'start_planned', 'end_planned'])
            ->keyBy('id');

        $violations = [];

        foreach ($items as $dependency) {
            $dependsOnId = is_array($dependency)
                ? (int) ($dependency['depends_on_task_id'] ?? 0)
                : (int) ($dependency->depends_on_task_id ?? 0);

            if ($dependsOnId <= 0 || ($successorId !== null && $dependsOnId === $successorId)) {
                continue;
            }

            $type = strtoupper((string) (is_array($dependency) ? ($dependency['type'] ?? 'FS') : ($dependency->type ?? 'FS')));
            $lagDays = (int) (is_array($dependency) ? ($dependency['lag_days'] ?? 0) : ($dependency->lag_days ?? 0));
            $predecessor = $predecessors->get($dependsOnId);
            if (!$predecessor) {
                continue;
            }

            $predecessorStart = $this->parseDependencyDate($predecessor->start_planned);
            $predecessorEnd = $this->parseDependencyDate($predecessor->end_planned);
            $predecessorTitle = $predecessor->title ?? ('#'.$dependsOnId);

            $required = null;
            $actual = null;
            $anchor = '';

            if ($type === 'FS' && $predecessorEnd && $successorStartDate) {
                $required = $predecessorEnd->copy()->addDays($lagDays);
                $actual = $successorStartDate;
                $anchor = 'start harus >= finish predecessor';
            } elseif ($type === 'SS' && $predecessorStart && $successorStartDate) {
                $required = $predecessorStart->copy()->addDays($lagDays);
                $actual = $successorStartDate;
                $anchor = 'start harus >= start predecessor';
            } elseif ($type === 'FF' && $predecessorEnd && $successorEndDate) {
                $required = $predecessorEnd->copy()->addDays($lagDays);
                $actual = $successorEndDate;
                $anchor = 'finish harus >= finish predecessor';
            } elseif ($type === 'SF' && $predecessorStart && $successorEndDate) {
                $required = $predecessorStart->copy()->addDays($lagDays);
                $actual = $successorEndDate;
                $anchor = 'finish harus >= start predecessor';
            }

            if ($required && $actual && $actual->lt($required)) {
                $violations[] = sprintf(
                    '%s (%s + lag %d hari: %s, minimal %s)',
                    $predecessorTitle,
                    $type,
                    $lagDays,
                    $anchor,
                    $required->toDateString()
                );
            }
        }

        if (empty($violations)) {
            return;
        }

        $message = 'Tanggal rencana task tidak sesuai dependency: '.implode(', ', $violations);

        throw new HttpResponseException(
            response()->json([
                'message' => $message,
                'errors' => [
                    'dependencies' => [$message],
                ],
            ], 422)
        );
    }

    protected function parseDependencyDate($value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }
        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value)->startOfDay();
        }
        return null;
    }

    /**
     * Automatically sync milestone status based on all its tasks.
     *
     * - If all tasks in the milestone are in a "done" status, mark milestone as Completed.
     * - If milestone is Completed but there is at least one non-done task, reopen it to In Progress.
     */
    protected function syncMilestoneCompletion(?Task $task): void
    {
        if (!$task || !$task->milestone_id) {
            return;
        }

        $milestoneId = $task->milestone_id;

        $milestone = Milestone::find($milestoneId);
        if (!$milestone) {
            return;
        }

        $remaining = Task::where('milestone_id', $milestoneId)
            ->whereNotIn('status', self::DONE_STATUSES_FOR_MILESTONE)
            ->count();

        if ($remaining === 0) {
            if ($milestone->status !== 'Completed') {
                $endActualMax = Task::where('milestone_id', $milestoneId)->max('end_actual');
                $endPlannedMax = Task::where('milestone_id', $milestoneId)->max('end_planned');

                if ($endActualMax) {
                    $milestone->due_actual = $endActualMax;
                } elseif ($endPlannedMax) {
                    $milestone->due_actual = $endPlannedMax;
                } elseif (!$milestone->due_actual) {
                    $milestone->due_actual = Carbon::now();
                }

                $milestone->status = 'Completed';
                $milestone->save();
                $this->clearMilestoneCaches($milestone);
            }
        } else {
            if ($milestone->status === 'Completed') {
                $milestone->status = 'In Progress';
                $milestone->save();
                $this->clearMilestoneCaches($milestone);
            }
        }
    }

    /**
     * Clear milestone-related caches when its status changes.
     *
     * Mirrors the keys used in MilestoneService without changing its structure.
     */
    protected function clearMilestoneCaches(Milestone $milestone): void
    {
        Cache::forget('milestones.all');
        if ($milestone->project_id) {
            Cache::forget('milestones.project.'.$milestone->project_id);
        }
        if ($milestone->status) {
            Cache::forget('milestones.status.'.$milestone->status);
        }
        Cache::forget('milestone.'.$milestone->id);
    }

    /**
     * Kirim notifikasi ke user yang baru ditugaskan pada task.
     *
     * @param Task $task Task yang memiliki assignments ter-load.
     * @param array<int,int> $previousUserIds Daftar user_id yang sudah pernah ter-assign sebelumnya.
     */
    protected function notifyAssigneesForTask(Task $task, array $previousUserIds = []): void
    {
        $actor = Auth::user();
        $alreadyNotified = [];

        foreach ($task->assignments as $assignment) {
            $userId = (int) ($assignment->user_id ?? 0);
            if ($userId <= 0) {
                continue;
            }
            if (in_array($userId, $previousUserIds, true)) {
                continue;
            }
            if (isset($alreadyNotified[$userId])) {
                continue;
            }
            $alreadyNotified[$userId] = true;
      
            $assignee = $assignment->relationLoaded('user') ? $assignment->user : null;
            if (!$assignee instanceof User) {
                $assignee = User::find($userId);
            }
            if (!$assignee instanceof User) {
                continue;
            }
            if ($actor && (int) $assignee->id === (int) $actor->id) {
                continue;
            }

            $role = $assignment->role_on_task ?: 'Member';

            $payload = [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'entity_type' => 'Task',
                'entity_id' => $task->id,
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'message' => 'Anda ditugaskan pada task '.$task->title.' sebagai '.$role,
            ];

            $assignee->notify(new TaskActivityNotification('task_assigned', $payload));
        }
    }

    /**
     * Kirim notifikasi task update ke assignee dan pengelola task, tanpa mengirim balik ke actor.
     *
     * @param array<string,mixed> $extraPayload
     */
    protected function notifyTaskWatchers(Task $task, string $eventType, array $extraPayload = []): void
    {
        $actor = Auth::user();
        $task->loadMissing(['assignments.user', 'project.divisionOwner']);

        $recipients = collect();

        foreach ($task->assignments as $assignment) {
            $assignee = $assignment->relationLoaded('user') ? $assignment->user : null;
            if ($assignee instanceof User) {
                $recipients->push($assignee);
            }
        }

        $projectOwner = $task->project?->divisionOwner;
        if (
            $projectOwner instanceof User
            && $projectOwner->hasPermissionTo('mengubah tugas')
        ) {
            $recipients->push($projectOwner);
        }

        $recipients = $recipients
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id')
            ->values();

        foreach ($recipients as $recipient) {
            if ($actor && (int) $recipient->id === (int) $actor->id) {
                continue;
            }

            $payload = array_merge([
                'task_id' => $task->id,
                'task_title' => $task->title,
                'entity_type' => 'Task',
                'entity_id' => $task->id,
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name,
            ], $extraPayload);

            $recipient->notify(new TaskActivityNotification($eventType, $payload));
        }
    }

    protected function clearCaches($id = null, $status = null, $projectId = null, $priority = null, $milestoneId = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id) Cache::forget(self::CACHE_ID_PREFIX.$id);
        if ($status) Cache::forget(self::CACHE_STATUS_PREFIX.$status);
        if ($projectId) Cache::forget(self::CACHE_PROJECT_PREFIX.$projectId);
        if ($priority) Cache::forget(self::CACHE_PRIORITY_PREFIX.$priority);
        if ($milestoneId) Cache::forget(self::CACHE_MILESTONE_PREFIX.$milestoneId);
    }
}
