<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectBaseline;
use App\Models\StatusHistory;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskBaseline;
use App\Models\TaskCostEntry;
use App\Models\User;
use App\Notifications\TaskActivityNotification;
use App\Repositories\Eloquent\MilestoneRepository;
use App\Repositories\Eloquent\ProjectRepository;
use App\Repositories\Eloquent\TaskRepository;
use App\Services\Contracts\EvmCostServiceInterface;
use App\Services\Contracts\EvmServiceInterface;
use App\Services\Contracts\KpiSnapshotServiceInterface;
use App\Services\Contracts\ProjectBaselineServiceInterface;
use App\Services\Contracts\TaskServiceInterface;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArchiveBehaviorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Member', 'web');
    }

    public function test_active_child_lists_hide_items_when_project_is_archived(): void
    {
        $project = Project::factory()->create();
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);
        Task::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
        ]);

        $project->delete();

        $this->assertCount(0, app(MilestoneRepository::class)->getMilestonesByProject($project->id));
        $this->assertCount(0, app(TaskRepository::class)->getTasksByProject($project->id));
    }

    public function test_child_restore_is_blocked_while_parent_is_archived(): void
    {
        $project = Project::factory()->create();
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
        ]);

        $task->delete();
        $milestone->delete();
        $project->delete();

        $this->assertNull(app(TaskRepository::class)->restoreTask($task->id));
        $this->assertNull(app(MilestoneRepository::class)->restoreMilestone($milestone->id));

        $project->restore();

        $this->assertNotNull(app(MilestoneRepository::class)->restoreMilestone($milestone->id));
        $this->assertNotNull(app(TaskRepository::class)->restoreTask($task->id));
    }

    public function test_force_delete_project_removes_related_attachment_files(): void
    {
        Storage::fake('public');

        $project = Project::factory()->create();
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
        ]);

        $path = 'attachments/testing/demo.txt';
        Storage::disk('public')->put($path, 'demo');

        Attachment::factory()->create([
            'entity_type' => 'Task',
            'entity_id' => $task->id,
            'storage_path' => $path,
        ]);

        $project->delete();

        $this->assertTrue(app(ProjectRepository::class)->forceDeleteArchivedProject($project->id));
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('attachments', ['storage_path' => $path]);
    }

    public function test_evm_excludes_tasks_under_archived_milestones(): void
    {
        $project = Project::factory()->create(['value_amount' => 1_000_000]);
        $activeMilestone = Milestone::factory()->create([
            'project_id' => $project->id,
            'created_at' => '2026-06-01 08:00:00',
        ]);
        $archivedMilestone = Milestone::factory()->create([
            'project_id' => $project->id,
            'created_at' => '2026-06-01 08:00:00',
        ]);

        Task::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $activeMilestone->id,
            'start_planned' => '2026-06-01',
            'duration_planned' => 5,
            'percent_complete' => 50,
            'budget_cost' => 500_000,
        ]);

        Task::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $archivedMilestone->id,
            'start_planned' => '2026-06-01',
            'duration_planned' => 5,
            'percent_complete' => 50,
            'budget_cost' => 500_000,
        ]);

        $archivedMilestone->delete();

        $effort = app(EvmServiceInterface::class)->computeForProjectDate($project->id, '2026-06-03');
        $cost = app(EvmCostServiceInterface::class)->computeForProjectDate($project->id, '2026-06-03');

        $this->assertSame(1, $effort['meta']['task_count']);
        $this->assertSame(1, $cost['meta']['task_count']);
    }

    public function test_kpi_generation_excludes_tasks_under_archived_milestones_as_of_snapshot_date(): void
    {
        $project = Project::factory()->create();
        $activeMilestone = Milestone::factory()->create([
            'project_id' => $project->id,
            'created_at' => '2026-06-01 08:00:00',
        ]);
        $archivedMilestone = Milestone::factory()->create([
            'project_id' => $project->id,
            'created_at' => '2026-06-01 08:00:00',
        ]);

        Task::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $activeMilestone->id,
            'created_at' => '2026-06-01 08:00:00',
            'status' => 'In Progress',
            'end_planned' => '2026-06-10',
        ]);

        Task::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $archivedMilestone->id,
            'created_at' => '2026-06-01 08:00:00',
            'status' => 'In Progress',
            'end_planned' => '2026-06-10',
        ]);

        $archivedMilestone->delete();
        $archivedMilestone->forceFill(['deleted_at' => '2026-06-02 09:00:00'])->save();

        $snapshot = app(KpiSnapshotServiceInterface::class)->generateForProjectAndDate($project->id, '2026-06-03');

        $this->assertSame(1, (int) $snapshot->tasks_total);
    }

    public function test_baseline_evm_excludes_tasks_created_after_the_baseline(): void
    {
        $project = Project::factory()->create(['value_amount' => 1_000_000]);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);

        $baselineTask = Task::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'start_planned' => '2026-06-13',
            'end_planned' => '2026-06-14',
            'duration_planned' => 2,
            'percent_complete' => 100,
            'budget_cost' => 200_000,
            'created_at' => '2026-06-13 07:00:00',
        ]);

        TaskAssignment::factory()->create([
            'task_id' => $baselineTask->id,
            'estimated_effort_hours' => 16,
        ]);

        $baseline = ProjectBaseline::create([
            'project_id' => $project->id,
            'baseline_name' => 'Initial Baseline',
            'taken_at' => '2026-06-13 08:00:00',
            'start_planned_base' => '2026-06-13',
            'end_planned_base' => '2026-06-19',
        ]);

        TaskBaseline::create([
            'baseline_id' => $baseline->id,
            'task_id' => $baselineTask->id,
            'start_planned_base' => '2026-06-13',
            'end_planned_base' => '2026-06-14',
            'duration_planned_base' => 2,
            'planned_effort_hours' => 16,
            'budget_cost_base' => 200_000,
        ]);

        Task::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'start_planned' => '2026-06-15',
            'end_planned' => '2026-06-16',
            'duration_planned' => 2,
            'percent_complete' => 0,
            'budget_cost' => 300_000,
            'created_at' => '2026-06-14 08:00:00',
        ]);

        $effort = app(EvmServiceInterface::class)->computeForProjectDate($project->id, '2026-06-16', $baseline->id);
        $cost = app(EvmCostServiceInterface::class)->computeForProjectDate($project->id, '2026-06-16', $baseline->id);

        $this->assertSame(1, $effort['meta']['task_count']);
        $this->assertSame(1, $cost['meta']['task_count']);
        $this->assertSame(200000.0, $cost['bac']);
    }

    public function test_creating_task_does_not_mutate_existing_project_baseline(): void
    {
        $project = Project::factory()->create(['value_amount' => 1_000_000]);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);

        $baselineTask = Task::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'start_planned' => '2026-06-13',
            'end_planned' => '2026-06-14',
            'duration_planned' => 2,
            'percent_complete' => 100,
            'budget_cost' => 400_000,
            'created_at' => '2026-06-13 07:00:00',
        ]);

        TaskAssignment::factory()->create([
            'task_id' => $baselineTask->id,
            'estimated_effort_hours' => 16,
        ]);

        $baseline = ProjectBaseline::create([
            'project_id' => $project->id,
            'baseline_name' => 'Baseline Sebelum Task Baru',
            'taken_at' => '2026-06-13 08:00:00',
            'start_planned_base' => '2026-06-13',
            'end_planned_base' => '2026-06-19',
        ]);

        TaskBaseline::create([
            'baseline_id' => $baseline->id,
            'task_id' => $baselineTask->id,
            'start_planned_base' => '2026-06-13',
            'end_planned_base' => '2026-06-14',
            'duration_planned_base' => 2,
            'planned_effort_hours' => 16,
            'budget_cost_base' => 400_000,
        ]);

        Carbon::setTestNow('2026-06-14 08:00:00');
        try {
            $newTask = app(TaskServiceInterface::class)->createTask([
                'project_id' => $project->id,
                'milestone_id' => $milestone->id,
                'title' => 'Task baru setelah baseline',
                'status' => 'To Do',
                'priority' => 'Medium',
                'start_planned' => '2026-06-15',
                'end_planned' => '2026-06-16',
                'duration_planned' => 2,
                'percent_complete' => 0,
                'budget_cost' => 600_000,
            ]);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertNotNull($newTask);
        $this->assertDatabaseMissing('task_baselines', [
            'baseline_id' => $baseline->id,
            'task_id' => $newTask->id,
        ]);

        $effort = app(EvmServiceInterface::class)->computeForProjectDate($project->id, '2026-06-16', $baseline->id);
        $cost = app(EvmCostServiceInterface::class)->computeForProjectDate($project->id, '2026-06-16', $baseline->id);

        $this->assertSame(1, $effort['meta']['task_count']);
        $this->assertSame(1, $cost['meta']['task_count']);
        $this->assertSame(400000.0, $cost['bac']);
    }

    public function test_new_project_baseline_uses_current_total_task_budget_for_cost_bac(): void
    {
        $project = Project::factory()->create([
            'value_amount' => 1_000_000,
            'start_planned' => '2026-06-13',
        ]);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);

        Task::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'start_planned' => '2026-06-13',
            'end_planned' => '2026-06-14',
            'duration_planned' => 2,
            'budget_cost' => 1_000_000,
            'created_at' => '2026-06-13 07:00:00',
        ]);

        Task::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'start_planned' => '2026-06-15',
            'end_planned' => '2026-06-16',
            'duration_planned' => 2,
            'budget_cost' => 300_000,
            'created_at' => '2026-06-13 07:30:00',
        ]);

        $baseline = app(ProjectBaselineServiceInterface::class)->createBaseline([
            'project_id' => $project->id,
            'baseline_name' => 'Baseline Total Task 1,3 Juta',
            'taken_at' => '2026-06-13 08:00:00',
        ]);

        $this->assertNotNull($baseline);
        $this->assertSame(1300000.0, (float) $baseline->value_amount_base);
        $this->assertSame(1300000.0, (float) TaskBaseline::where('baseline_id', $baseline->id)->sum('budget_cost_base'));

        $cost = app(EvmCostServiceInterface::class)->computeForProjectDate($project->id, '2026-06-16', $baseline->id);

        $this->assertSame(2, $cost['meta']['task_count']);
        $this->assertSame(1300000.0, $cost['bac']);
    }

    public function test_updating_task_does_not_mutate_existing_task_baseline_snapshot(): void
    {
        $project = Project::factory()->create([
            'value_amount' => 1_000_000,
            'start_planned' => '2026-06-13',
        ]);
        $milestone = Milestone::factory()->create(['project_id' => $project->id]);
        $user = User::factory()->create();

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'title' => 'Task sebelum update',
            'start_planned' => '2026-06-13',
            'end_planned' => '2026-06-14',
            'duration_planned' => 2,
            'percent_complete' => 50,
            'budget_cost' => 400_000,
            'created_at' => '2026-06-13 07:00:00',
        ]);

        $baseline = ProjectBaseline::create([
            'project_id' => $project->id,
            'baseline_name' => 'Baseline Sebelum Update Task',
            'taken_at' => '2026-06-13 08:00:00',
            'start_planned_base' => '2026-06-13',
            'end_planned_base' => '2026-06-19',
            'value_amount_base' => 400_000,
        ]);

        $taskBaseline = TaskBaseline::create([
            'baseline_id' => $baseline->id,
            'task_id' => $task->id,
            'start_planned_base' => '2026-06-13',
            'end_planned_base' => '2026-06-14',
            'duration_planned_base' => 2,
            'planned_effort_hours' => 16,
            'budget_cost_base' => 400_000,
        ]);

        app(TaskServiceInterface::class)->updateTask($task->id, [
            'title' => 'Task setelah update',
            'start_planned' => '2026-06-15',
            'end_planned' => '2026-06-17',
            'duration_planned' => 3,
            'budget_cost' => 900_000,
            'assignments' => [
                [
                    'user_id' => $user->id,
                    'role_on_task' => 'Developer',
                    'estimated_effort_hours' => 40,
                ],
            ],
        ]);

        $taskBaseline->refresh();

        $this->assertSame('2026-06-13', Carbon::parse($taskBaseline->start_planned_base)->toDateString());
        $this->assertSame('2026-06-14', Carbon::parse($taskBaseline->end_planned_base)->toDateString());
        $this->assertSame(2, (int) $taskBaseline->duration_planned_base);
        $this->assertSame(16.0, (float) $taskBaseline->planned_effort_hours);
        $this->assertSame(400000.0, (float) $taskBaseline->budget_cost_base);

        $effort = app(EvmServiceInterface::class)->computeForProjectDate($project->id, '2026-06-17', $baseline->id);
        $cost = app(EvmCostServiceInterface::class)->computeForProjectDate($project->id, '2026-06-17', $baseline->id);

        $this->assertSame(1, $effort['meta']['task_count']);
        $this->assertSame(16.0, $effort['pv']);
        $this->assertSame(1, $cost['meta']['task_count']);
        $this->assertSame(400000.0, $cost['bac']);
    }

    public function test_deadline_notifications_include_task_assignee_project_owner_and_project_members(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $taskAssignee = User::factory()->create();
        $projectMember = User::factory()->create();
        $outsideUser = User::factory()->create();

        $project = Project::factory()->create([
            'division_owner_id' => $owner->id,
        ]);

        $lateTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'In Progress',
            'end_planned' => '2026-06-10',
        ]);

        $otherProjectTask = Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'In Progress',
            'end_planned' => '2026-06-20',
        ]);

        TaskAssignment::factory()->create([
            'task_id' => $lateTask->id,
            'user_id' => $taskAssignee->id,
            'role_on_task' => 'Member',
        ]);

        TaskAssignment::factory()->create([
            'task_id' => $otherProjectTask->id,
            'user_id' => $projectMember->id,
            'role_on_task' => 'Member',
        ]);

        $this->artisan('notifications:task-deadlines --days=3 --date=2026-06-12')
            ->assertSuccessful();

        Notification::assertSentTo($owner, TaskActivityNotification::class);
        Notification::assertSentTo($taskAssignee, TaskActivityNotification::class);
        Notification::assertSentTo($projectMember, TaskActivityNotification::class);
        Notification::assertNotSentTo($outsideUser, TaskActivityNotification::class);
    }

    public function test_nested_task_status_histories_are_filtered_to_the_requested_task(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('melihat tugas', 'web'));
        Sanctum::actingAs($viewer);

        $firstTask = Task::factory()->create();
        $secondTask = Task::factory()->create();

        $matchingHistory = StatusHistory::factory()->create([
            'task_id' => $firstTask->id,
            'from_status' => 'To Do',
            'to_status' => 'In Progress',
        ]);

        StatusHistory::factory()->create([
            'task_id' => $secondTask->id,
            'from_status' => 'To Do',
            'to_status' => 'Done',
        ]);

        $response = $this->getJson("/api/tasks/{$firstTask->id}/status-histories");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingHistory->id)
            ->assertJsonPath('data.0.task_id', $firstTask->id);
    }

    public function test_task_cost_entry_endpoints_use_task_scope_and_write_history(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('melihat project', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('mengubah project', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('menghapus project', 'web'));
        Sanctum::actingAs($user);

        $task = Task::factory()->create();
        $otherTask = Task::factory()->create();

        TaskCostEntry::create([
            'task_id' => $otherTask->id,
            'incurred_on' => '2026-06-09',
            'amount' => 25000,
            'category' => 'Other',
            'note' => 'Should not appear',
        ]);

        $createResponse = $this->postJson("/api/tasks/{$task->id}/cost-entries", [
            'incurred_on' => '2026-06-10',
            'amount' => 125000,
            'category' => 'Transport',
            'note' => 'Test cost entry',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.task_id', $task->id)
            ->assertJsonPath('data.category', 'Transport');

        $this->assertDatabaseHas('task_cost_entries', [
            'task_id' => $task->id,
            'category' => 'Transport',
        ]);

        $this->assertDatabaseHas('status_histories', [
            'task_id' => $task->id,
            'changed_by' => $user->id,
            'note' => 'Cost entry ditambahkan: 2026-06-10 (amount: 125000.00) (kategori: Transport)',
        ]);

        $this->getJson("/api/tasks/{$task->id}/cost-entries")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.task_id', $task->id);

        $costEntryId = $createResponse->json('data.id');

        $this->deleteJson("/api/tasks/{$task->id}/cost-entries/{$costEntryId}")
            ->assertOk();

        $this->assertDatabaseMissing('task_cost_entries', [
            'id' => $costEntryId,
        ]);

        $this->assertDatabaseHas('status_histories', [
            'task_id' => $task->id,
            'changed_by' => $user->id,
            'note' => 'Cost entry dihapus: 2026-06-10 (amount: 125000.00) (kategori: Transport)',
        ]);
    }
}
