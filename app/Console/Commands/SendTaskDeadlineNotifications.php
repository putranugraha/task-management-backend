<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Notifications\TaskActivityNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendTaskDeadlineNotifications extends Command
{
    protected $signature = 'notifications:task-deadlines
        {--days=3 : Jumlah hari ke depan untuk task_due_soon}
        {--date= : Tanggal simulasi/debug format Y-m-d}';

    protected $description = 'Send task due soon and overdue notifications to assignees, project owner, project members, and admins.';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $today = $this->resolveDate($this->option('date'));
        $until = $today->copy()->addDays($days);

        $tasks = Task::query()
            ->with(['assignments.user', 'project.divisionOwner'])
            ->whereNotNull('end_planned')
            ->whereNotIn('status', ['Done', 'Cancelled'])
            ->whereHas('project')
            ->where(function ($query) {
                $query->whereNull('milestone_id')
                    ->orWhereHas('milestone');
            })
            ->whereDate('end_planned', '<=', $until->toDateString())
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($tasks as $task) {
            $dueDate = Carbon::parse($task->end_planned)->startOfDay();
            $eventType = $dueDate->lt($today) ? 'task_overdue' : 'task_due_soon';

            foreach ($this->recipientsForTask($task) as $recipient) {
                if ($this->alreadySent($recipient, $eventType, (int) $task->id, $dueDate->toDateString())) {
                    $skipped++;
                    continue;
                }

                $recipient->notify(new TaskActivityNotification($eventType, [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'entity_type' => 'Task',
                    'entity_id' => $task->id,
                    'project_id' => $task->project_id,
                    'project_name' => $task->project?->name,
                    'due_date' => $dueDate->toDateString(),
                    'message' => $this->messageFor($eventType, $task->title, $dueDate, $today),
                ]));

                $sent++;
            }
        }

        $this->info("Task deadline notifications sent: {$sent}, skipped duplicate: {$skipped}");

        return self::SUCCESS;
    }

    private function resolveDate(mixed $date): Carbon
    {
        if (is_string($date) && trim($date) !== '') {
            return Carbon::createFromFormat('Y-m-d', trim($date))->startOfDay();
        }

        return Carbon::today()->startOfDay();
    }

    /**
     * @return Collection<int,User>
     */
    private function recipientsForTask(Task $task): Collection
    {
        $assignees = collect($task->assignments ?? [])
            ->map(fn ($assignment) => $assignment->user ?? null)
            ->filter(fn ($user) => $user instanceof User);

        $projectOwner = $task->project?->divisionOwner instanceof User
            ? collect([$task->project->divisionOwner])
            : collect();

        $projectMembers = TaskAssignment::query()
            ->with('user')
            ->whereHas('task', function ($query) use ($task) {
                $query->where('project_id', $task->project_id);
            })
            ->get()
            ->map(fn (TaskAssignment $assignment) => $assignment->user)
            ->filter(fn ($user) => $user instanceof User);

        $admins = User::query()
            ->where('status', 'Aktif')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['Admin', 'Super Admin']))
            ->get();

        return $assignees
            ->merge($projectOwner)
            ->merge($projectMembers)
            ->merge($admins)
            ->filter(fn (User $user) => ($user->status ?? 'Aktif') === 'Aktif' && (bool) ($user->is_active ?? true))
            ->unique('id')
            ->values();
    }

    private function alreadySent(User $user, string $eventType, int $taskId, string $dueDate): bool
    {
        return $user->notifications()
            ->where('type', TaskActivityNotification::class)
            ->where('created_at', '>=', now()->subDays(45))
            ->get()
            ->contains(function ($notification) use ($eventType, $taskId, $dueDate) {
                $data = $notification->data ?? [];

                return ($data['event'] ?? null) === $eventType
                    && (int) ($data['task_id'] ?? 0) === $taskId
                    && ($data['due_date'] ?? null) === $dueDate;
            });
    }

    private function messageFor(string $eventType, string $title, Carbon $dueDate, Carbon $today): string
    {
        if ($eventType === 'task_overdue') {
            $daysLate = $dueDate->diffInDays($today);

            return 'Task '.$title.' sudah melewati deadline '.$dueDate->toDateString().' selama '.$daysLate.' hari.';
        }

        $daysLeft = $today->diffInDays($dueDate);
        if ($daysLeft === 0) {
            return 'Task '.$title.' jatuh tempo hari ini.';
        }

        return 'Task '.$title.' akan jatuh tempo pada '.$dueDate->toDateString().' ('.$daysLeft.' hari lagi).';
    }
}
