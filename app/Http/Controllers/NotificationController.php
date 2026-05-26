<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

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

        $data = $notifications->map(function (DatabaseNotification $notification) {
            $payload = $notification->data ?? [];

            return [
                'id' => $notification->id,
                'event' => $payload['event'] ?? null,
                'message' => $payload['message'] ?? null,
                'task_id' => $payload['task_id'] ?? null,
                'task_title' => $payload['task_title'] ?? null,
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
}
