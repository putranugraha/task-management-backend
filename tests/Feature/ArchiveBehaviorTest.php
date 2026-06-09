<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Repositories\Eloquent\MilestoneRepository;
use App\Repositories\Eloquent\ProjectRepository;
use App\Repositories\Eloquent\TaskRepository;
use App\Services\Contracts\EvmCostServiceInterface;
use App\Services\Contracts\EvmServiceInterface;
use App\Services\Contracts\KpiSnapshotServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
}
