<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

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
        $channels = ['database'];

        if (
            filter_var(config('notifications.mail_enabled', false), FILTER_VALIDATE_BOOLEAN)
            && !empty($notifiable->email)
        ) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => $this->eventType,
            'task_id' => $this->payload['task_id'] ?? null,
            'task_title' => $this->payload['task_title'] ?? null,
            'project_id' => $this->payload['project_id'] ?? null,
            'project_name' => $this->payload['project_name'] ?? null,
            'entity_type' => $this->payload['entity_type'] ?? null,
            'entity_id' => $this->payload['entity_id'] ?? null,
            'attachment_id' => $this->payload['attachment_id'] ?? null,
            'comment_id' => $this->payload['comment_id'] ?? null,
            'actor_id' => $this->payload['actor_id'] ?? null,
            'actor_name' => $this->payload['actor_name'] ?? null,
            'message' => $this->payload['message'] ?? null,
            'status_before' => $this->payload['status_before'] ?? null,
            'status_after' => $this->payload['status_after'] ?? null,
            'percent_before' => $this->payload['percent_before'] ?? null,
            'percent_after' => $this->payload['percent_after'] ?? null,
            'due_date' => $this->payload['due_date'] ?? null,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $taskTitle = $this->payload['task_title'] ?? 'Task';
        $message = $this->payload['message'] ?? $this->defaultMessage($taskTitle);
        $actorName = $this->payload['actor_name'] ?? null;
        $projectName = $this->payload['project_name'] ?? null;
        $dueDate = $this->payload['due_date'] ?? null;
        $url = $this->taskUrl();

        $mail = (new MailMessage)
            ->subject($this->mailSubject($taskTitle))
            ->greeting('Halo '.$notifiable->name.',')
            ->line($message);

        if ($projectName) {
            $mail->line('Project: '.$projectName);
        }

        if ($dueDate) {
            $mail->line('Deadline: '.$dueDate);
        }

        if ($actorName) {
            $mail->line('Dipicu oleh: '.$actorName);
        }

        if ($url) {
            $mail->action('Buka Task', $url);
        }

        return $mail->line('Email ini dikirim otomatis dari Task Management Central Saga.');
    }

    private function mailSubject(string $taskTitle): string
    {
        return match ($this->eventType) {
            'task_a+ssigned' => 'Task baru ditugaskan: '.$taskTitle,
            'comment_added' => 'Komentar baru pada task: '.$taskTitle,
            'attachment_uploaded' => 'Lampiran baru pada task: '.$taskTitle,
            'attachment_approved' => 'Lampiran disetujui: '.$taskTitle,
            'attachment_rejected' => 'Lampiran ditolak: '.$taskTitle,
            'task_status_changed' => 'Status task berubah: '.$taskTitle,
            'task_progress_updated' => 'Progress task diperbarui: '.$taskTitle,
            'task_due_soon' => 'Task mendekati deadline: '.$taskTitle,
            'task_overdue' => 'Task melewati deadline: '.$taskTitle,
            default => Str::headline(str_replace('_', ' ', $this->eventType)).': '.$taskTitle,
        };
    }

    private function defaultMessage(string $taskTitle): string
    {
        return match ($this->eventType) {
            'task_assigned' => 'Anda mendapatkan assignment baru pada task '.$taskTitle.'.',
            'comment_added' => 'Ada komentar baru pada task '.$taskTitle.'.',
            'attachment_uploaded' => 'Ada lampiran baru pada task '.$taskTitle.'.',
            'attachment_approved' => 'Lampiran pada task '.$taskTitle.' telah disetujui.',
            'attachment_rejected' => 'Lampiran pada task '.$taskTitle.' ditolak dan perlu ditinjau.',
            'task_status_changed' => 'Status task '.$taskTitle.' telah diperbarui.',
            'task_progress_updated' => 'Progress task '.$taskTitle.' telah diperbarui.',
            'task_due_soon' => 'Task '.$taskTitle.' sudah mendekati deadline.',
            'task_overdue' => 'Task '.$taskTitle.' sudah melewati deadline.',
            default => 'Ada update baru pada task '.$taskTitle.'.',
        };
    }

    private function taskUrl(): ?string
    {
        $taskId = $this->payload['task_id'] ?? null;
        if (!$taskId) {
            return null;
        }

        $frontendUrl = rtrim((string) config('notifications.frontend_url'), '/');
        if ($frontendUrl === '') {
            return null;
        }

        return $frontendUrl.'/dashboard/tasks/'.$taskId;
    }
}
