<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\TaskAssignmentRepositoryInterface;
use App\Services\Contracts\TaskAssignmentServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\TaskAssignment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskActivityNotification;

class TaskAssignmentService implements TaskAssignmentServiceInterface
{
    protected TaskAssignmentRepositoryInterface $repository;

    const CACHE_ALL = 'task_assignments.all';
    const CACHE_ID_PREFIX = 'task_assignment.'; // + id
    const CACHE_TASK_PREFIX = 'task_assignments.task.'; // + taskId
    const CACHE_USER_PREFIX = 'task_assignments.user.'; // + userId
    const CACHE_DURATION = 1800; // 30 minutes

    public function __construct(TaskAssignmentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllAssignments()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, fn () => $this->repository->getAllAssignments());
    }

    public function getAssignmentById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, fn () => $this->repository->getAssignmentById($id));
    }

    public function getAssignmentsByTask($taskId)
    {
        return Cache::remember(self::CACHE_TASK_PREFIX.$taskId, self::CACHE_DURATION, fn () => $this->repository->getAssignmentsByTask($taskId));
    }

    public function getAssignmentsByUser($userId)
    {
        return Cache::remember(self::CACHE_USER_PREFIX.$userId, self::CACHE_DURATION, fn () => $this->repository->getAssignmentsByUser($userId));
    }

    public function getAssignmentByTaskAndUser($taskId, $userId)
    {
        return $this->repository->getAssignmentByTaskAndUser($taskId, $userId);
    }

    public function createAssignment(array $data)
    {
        $hadAssignmentForUser = false;
        if (!empty($data['task_id']) && !empty($data['user_id'])) {
            $hadAssignmentForUser = TaskAssignment::where('task_id', $data['task_id'])
                ->where('user_id', $data['user_id'])
                ->exists();
        }

        $assignment = $this->repository->createAssignment($data);
        $this->clearCaches($assignment->id ?? null, $assignment->task_id ?? null, $assignment->user_id ?? null);

        if ($assignment) {
            $actor = Auth::user();

            $properties = [
                'assignment_id' => $assignment->id,
                'task_id' => $assignment->task_id,
                'user_id' => $assignment->user_id,
                'role_on_task' => $assignment->role_on_task,
                'estimated_effort_hours' => $assignment->estimated_effort_hours,
                'assigned_at' => $assignment->assigned_at,
            ];

            $activity = activity('assignments')
                ->performedOn($assignment instanceof TaskAssignment ? $assignment : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('created');

            $task = Task::find($assignment->task_id);
            $assignee = User::find($assignment->user_id);

            $isSelfAssignment = $actor && (int) $actor->id === (int) $assignee?->id;
            if ($task && $assignee && ! $hadAssignmentForUser && ! $isSelfAssignment) {
                $payload = [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'entity_type' => 'Task',
                    'entity_id' => $task->id,
                    'actor_id' => $actor?->id,
                    'actor_name' => $actor?->name,
                    'message' => 'Anda ditugaskan pada task '.$task->title.' sebagai '.$assignment->role_on_task,
                ];

                $assignee->notify(new TaskActivityNotification('task_assigned', $payload));
            }
        }

        return $assignment;
    }

    public function updateAssignment($id, array $data)
    {
        $before = $this->repository->getAssignmentById($id);
        $assignment = $this->repository->updateAssignment($id, $data);
        $this->clearCaches($id, $assignment->task_id ?? null, $assignment->user_id ?? null);

        if ($assignment) {
            $actor = Auth::user();

            $properties = [
                'assignment_id' => $assignment->id,
                'task_id' => $assignment->task_id,
                'user_id' => $assignment->user_id,
                'role_on_task_before' => $before->role_on_task ?? null,
                'role_on_task_after' => $assignment->role_on_task,
                'estimated_effort_hours_before' => $before->estimated_effort_hours ?? null,
                'estimated_effort_hours_after' => $assignment->estimated_effort_hours,
            ];

            $activity = activity('assignments')
                ->performedOn($assignment instanceof TaskAssignment ? $assignment : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('updated');
        }

        return $assignment;
    }

    public function deleteAssignment($id)
    {
        $assignment = $this->getAssignmentById($id);
        $result = $this->repository->deleteAssignment($id);
        $this->clearCaches($id, $assignment->task_id ?? null, $assignment->user_id ?? null);

        if ($result && $assignment) {
            $actor = Auth::user();

            $properties = [
                'assignment_id' => $assignment->id,
                'task_id' => $assignment->task_id,
                'user_id' => $assignment->user_id,
                'role_on_task' => $assignment->role_on_task,
                'estimated_effort_hours' => $assignment->estimated_effort_hours,
            ];

            $activity = activity('assignments')
                ->performedOn($assignment instanceof TaskAssignment ? $assignment : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('deleted');
        }

        return $result;
    }

    public function deleteAssignmentsByTask($taskId)
    {
        $actor = Auth::user();

        $result = $this->repository->deleteAssignmentsByTask($taskId);
        $this->clearCaches(null, $taskId, null);

        if ($result) {
            $activity = activity('assignments')
                ->withProperties([
                    'task_id' => $taskId,
                    'action' => 'delete_by_task',
                ]);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('bulk_deleted');
        }

        return $result;
    }

    public function deleteAssignmentsByUser($userId)
    {
        $actor = Auth::user();

        $result = $this->repository->deleteAssignmentsByUser($userId);
        $this->clearCaches(null, null, $userId);

        if ($result) {
            $activity = activity('assignments')
                ->withProperties([
                    'user_id' => $userId,
                    'action' => 'delete_by_user',
                ]);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('bulk_deleted');
        }

        return $result;
    }

    protected function clearCaches($id = null, $taskId = null, $userId = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id) Cache::forget(self::CACHE_ID_PREFIX.$id);
        if ($taskId) Cache::forget(self::CACHE_TASK_PREFIX.$taskId);
        if ($userId) Cache::forget(self::CACHE_USER_PREFIX.$userId);
    }
}
