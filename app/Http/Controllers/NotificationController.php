<?php

namespace App\Http\Controllers;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $this->pruneStaleNotifications($user);

        $onlyUnread = filter_var($request->query('only_unread', false), FILTER_VALIDATE_BOOLEAN);

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 20;
        }

        $query = $user->notifications()->latest('created_at');

        if ($onlyUnread) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate($perPage);
        $targetStatuses = $this->resolveNotificationTargetStatuses($notifications->getCollection());

        $data = $notifications->map(function (DatabaseNotification $notification) use ($targetStatuses) {
            $payload = $notification->data ?? [];
            $targetStatus = $targetStatuses[$notification->id] ?? [
                'target_type' => null,
                'target_id' => null,
                'target_archived' => false,
            ];

            return [
                'id' => $notification->id,
                'event' => $payload['event'] ?? null,
                'message' => $payload['message'] ?? null,
                'task_id' => $payload['task_id'] ?? null,
                'task_title' => $payload['task_title'] ?? null,
                'project_id' => $payload['project_id'] ?? null,
                'project_name' => $payload['project_name'] ?? null,
                'entity_type' => $payload['entity_type'] ?? null,
                'entity_id' => $payload['entity_id'] ?? null,
                'attachment_id' => $payload['attachment_id'] ?? null,
                'comment_id' => $payload['comment_id'] ?? null,
                'actor_id' => $payload['actor_id'] ?? null,
                'actor_name' => $payload['actor_name'] ?? null,
                'status_before' => $payload['status_before'] ?? null,
                'status_after' => $payload['status_after'] ?? null,
                'percent_before' => $payload['percent_before'] ?? null,
                'percent_after' => $payload['percent_after'] ?? null,
                'due_date' => $payload['due_date'] ?? null,
                'target_type' => $targetStatus['target_type'],
                'target_id' => $targetStatus['target_id'],
                'target_archived' => $targetStatus['target_archived'],
                'read_at' => optional($notification->read_at)?->toIso8601String(),
                'created_at' => optional($notification->created_at)?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $notificationId)
    {
        $user = $request->user();

        /** @var DatabaseNotification|null $notification */
        $notification = $user->notifications()->whereKey($notificationId)->first();

        if (!$notification) {
            return response()->json(['message' => 'Notifikasi tidak ditemukan'], 404);
        }

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return response()->json(['message' => 'Notifikasi ditandai sebagai dibaca']);
    }

    private function pruneStaleNotifications(User $user): void
    {
        $notifications = $user->notifications()->get();
        if ($notifications->isEmpty()) {
            return;
        }

        $taskIds = [];
        $projectIds = [];
        $milestoneIds = [];

        foreach ($notifications as $notification) {
            $payload = $notification->data ?? [];

            if (!empty($payload['task_id'])) {
                $taskIds[] = (int) $payload['task_id'];
            }

            if (!empty($payload['project_id'])) {
                $projectIds[] = (int) $payload['project_id'];
            }

            $entityType = class_basename((string) ($payload['entity_type'] ?? ''));
            $entityId = (int) ($payload['entity_id'] ?? 0);

            if ($entityType === 'Task' && $entityId > 0) {
                $taskIds[] = $entityId;
            } elseif ($entityType === 'Project' && $entityId > 0) {
                $projectIds[] = $entityId;
            } elseif ($entityType === 'Milestone' && $entityId > 0) {
                $milestoneIds[] = $entityId;
            }
        }

        $existingTaskIds = Task::withTrashed()
            ->whereIn('id', array_values(array_unique($taskIds)))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $existingProjectIds = Project::withTrashed()
            ->whereIn('id', array_values(array_unique($projectIds)))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $existingMilestoneIds = Milestone::withTrashed()
            ->whereIn('id', array_values(array_unique($milestoneIds)))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        foreach ($notifications as $notification) {
            $payload = $notification->data ?? [];

            $taskId = (int) ($payload['task_id'] ?? 0);
            if ($taskId > 0 && !$existingTaskIds->has($taskId)) {
                $notification->delete();
                continue;
            }

            $projectId = (int) ($payload['project_id'] ?? 0);
            if ($projectId > 0 && !$existingProjectIds->has($projectId)) {
                $notification->delete();
                continue;
            }

            $entityType = class_basename((string) ($payload['entity_type'] ?? ''));
            $entityId = (int) ($payload['entity_id'] ?? 0);

            if ($entityType === 'Task' && $entityId > 0 && !$existingTaskIds->has($entityId)) {
                $notification->delete();
                continue;
            }

            if ($entityType === 'Project' && $entityId > 0 && !$existingProjectIds->has($entityId)) {
                $notification->delete();
                continue;
            }

            if ($entityType === 'Milestone' && $entityId > 0 && !$existingMilestoneIds->has($entityId)) {
                $notification->delete();
            }
        }
    }

    private function resolveNotificationTargetStatuses($notifications): array
    {
        $taskIds = [];
        $projectIds = [];
        $milestoneIds = [];

        foreach ($notifications as $notification) {
            $payload = $notification->data ?? [];
            $target = $this->notificationTargetFromPayload($payload);

            if ($target['type'] === 'Task') {
                $taskIds[] = $target['id'];
            } elseif ($target['type'] === 'Project') {
                $projectIds[] = $target['id'];
            } elseif ($target['type'] === 'Milestone') {
                $milestoneIds[] = $target['id'];
            }
        }

        $tasks = Task::withTrashed()
            ->with([
                'project' => fn ($query) => $query->withTrashed()->select('id', 'deleted_at'),
                'milestone' => fn ($query) => $query->withTrashed()->select('id', 'project_id', 'deleted_at'),
            ])
            ->whereIn('id', array_values(array_unique($taskIds)))
            ->get(['id', 'project_id', 'milestone_id', 'deleted_at'])
            ->keyBy('id');

        $projects = Project::withTrashed()
            ->whereIn('id', array_values(array_unique($projectIds)))
            ->get(['id', 'deleted_at'])
            ->keyBy('id');

        $milestones = Milestone::withTrashed()
            ->with(['project' => fn ($query) => $query->withTrashed()->select('id', 'deleted_at')])
            ->whereIn('id', array_values(array_unique($milestoneIds)))
            ->get(['id', 'project_id', 'deleted_at'])
            ->keyBy('id');

        $statuses = [];
        foreach ($notifications as $notification) {
            $payload = $notification->data ?? [];
            $target = $this->notificationTargetFromPayload($payload);
            $model = null;

            if ($target['type'] === 'Task') {
                $model = $tasks->get($target['id']);
            } elseif ($target['type'] === 'Project') {
                $model = $projects->get($target['id']);
            } elseif ($target['type'] === 'Milestone') {
                $model = $milestones->get($target['id']);
            }

            $statuses[$notification->id] = [
                'target_type' => $target['type'],
                'target_id' => $target['id'],
                'target_archived' => $this->isNotificationTargetArchived($target['type'], $model),
            ];
        }

        return $statuses;
    }

    private function isNotificationTargetArchived(?string $targetType, $model): bool
    {
        if (!$model) {
            return false;
        }

        if ((bool) ($model->deleted_at ?? null)) {
            return true;
        }

        if ($targetType === 'Task') {
            return (bool) ($model->project?->deleted_at ?? null)
                || (bool) ($model->milestone?->deleted_at ?? null);
        }

        if ($targetType === 'Milestone') {
            return (bool) ($model->project?->deleted_at ?? null);
        }

        return false;
    }

    private function notificationTargetFromPayload(array $payload): array
    {
        if (!empty($payload['task_id'])) {
            return ['type' => 'Task', 'id' => (int) $payload['task_id']];
        }

        $entityType = class_basename((string) ($payload['entity_type'] ?? ''));
        $entityId = (int) ($payload['entity_id'] ?? 0);

        if ($entityType === 'Task' && $entityId > 0) {
            return ['type' => 'Task', 'id' => $entityId];
        }

        if (!empty($payload['project_id'])) {
            return ['type' => 'Project', 'id' => (int) $payload['project_id']];
        }

        if ($entityType === 'Project' && $entityId > 0) {
            return ['type' => 'Project', 'id' => $entityId];
        }

        if ($entityType === 'Milestone' && $entityId > 0) {
            return ['type' => 'Milestone', 'id' => $entityId];
        }

        return ['type' => null, 'id' => null];
    }
}
