<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskActivityNotification extends Notification
{
    use Queueable;

    protected string $eventType;
    protected array $payload;

    public function __construct(string $eventType, array $payload = [])
    {
        $this->eventType = $eventType;
        $this->payload = $payload;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => $this->eventType,
            'task_id' => $this->payload['task_id'] ?? null,
            'task_title' => $this->payload['task_title'] ?? null,
            'entity_type' => $this->payload['entity_type'] ?? null,
            'entity_id' => $this->payload['entity_id'] ?? null,
            'attachment_id' => $this->payload['attachment_id'] ?? null,
            'comment_id' => $this->payload['comment_id'] ?? null,
            'actor_id' => $this->payload['actor_id'] ?? null,
            'actor_name' => $this->payload['actor_name'] ?? null,
            'message' => $this->payload['message'] ?? null,
        ];
    }
}

