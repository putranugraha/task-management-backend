<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use App\Models\Division;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\TimeEntry;
use App\Models\TaskAssignment;
use App\Models\Comment;
use App\Models\Attachment;
use App\Models\TaskDependency;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\Models\Permission as SpatiePermission;
use App\Repositories\Contracts\AttachmentRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Repositories\Contracts\DivisionRepositoryInterface;
use App\Repositories\Contracts\MilestoneRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\ProjectBaselineRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\ReportingPeriodRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\StatusHistoryRepositoryInterface;
use App\Repositories\Contracts\TaskAssignmentRepositoryInterface;
use App\Repositories\Contracts\TaskBaselineRepositoryInterface;
use App\Repositories\Contracts\TaskDependencyRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\TimeEntryRepositoryInterface;
use App\Repositories\Contracts\KpiSnapshotRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\AttachmentRepository;
use App\Repositories\Eloquent\CommentRepository;
use App\Repositories\Eloquent\DivisionRepository;
use App\Repositories\Eloquent\MilestoneRepository;
use App\Repositories\Eloquent\PermissionRepository;
use App\Repositories\Eloquent\ProjectBaselineRepository;
use App\Repositories\Eloquent\ProjectRepository;
use App\Repositories\Eloquent\ReportingPeriodRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\StatusHistoryRepository;
use App\Repositories\Eloquent\TaskAssignmentRepository;
use App\Repositories\Eloquent\TaskBaselineRepository;
use App\Repositories\Eloquent\TaskDependencyRepository;
use App\Repositories\Eloquent\TaskRepository;
use App\Repositories\Eloquent\TimeEntryRepository;
use App\Repositories\Eloquent\KpiSnapshotRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Services\Contracts\AttachmentServiceInterface;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\DivisionServiceInterface;
use App\Services\Contracts\MilestoneServiceInterface;
use App\Services\Contracts\PermissionServiceInterface;
use App\Services\Contracts\ProjectBaselineServiceInterface;
use App\Services\Contracts\ProjectServiceInterface;
use App\Services\Contracts\ReportingPeriodServiceInterface;
use App\Services\Contracts\RoleServiceInterface;
use App\Services\Contracts\StatusHistoryServiceInterface;
use App\Services\Contracts\TaskAssignmentServiceInterface;
use App\Services\Contracts\TaskBaselineServiceInterface;
use App\Services\Contracts\TaskDependencyServiceInterface;
use App\Services\Contracts\TaskServiceInterface;
use App\Services\Contracts\TimeEntryServiceInterface;
use App\Services\Contracts\KpiSnapshotServiceInterface;
use App\Services\Contracts\EvmServiceInterface;
use App\Services\Contracts\EvmCostServiceInterface;
use App\Services\Contracts\UserServiceInterface;
use App\Services\Implementations\AttachmentService;
use App\Services\Implementations\CommentService;
use App\Services\Implementations\DivisionService;
use App\Services\Implementations\MilestoneService;
use App\Services\Implementations\PermissionService;
use App\Services\Implementations\ProjectBaselineService;
use App\Services\Implementations\ProjectService;
use App\Services\Implementations\ReportingPeriodService;
use App\Services\Implementations\RoleService;
use App\Services\Implementations\StatusHistoryService;
use App\Services\Implementations\TaskAssignmentService;
use App\Services\Implementations\TaskBaselineService;
use App\Services\Implementations\TaskDependencyService;
use App\Services\Implementations\TaskService;
use App\Services\Implementations\TimeEntryService;
use App\Services\Implementations\KpiSnapshotService;
use App\Services\Implementations\EvmService;
use App\Services\Implementations\EvmCostService;
use App\Services\Implementations\UserService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Division interfaces to implementations
        $this->app->bind(DivisionRepositoryInterface::class, DivisionRepository::class);
        $this->app->bind(DivisionServiceInterface::class, DivisionService::class);

        // Bind Project interfaces to implementations
        $this->app->bind(ProjectRepositoryInterface::class, ProjectRepository::class);
        $this->app->bind(ProjectServiceInterface::class, ProjectService::class);
        // Bind ReportingPeriod interfaces to implementations
        $this->app->bind(ReportingPeriodRepositoryInterface::class, ReportingPeriodRepository::class);
        $this->app->bind(ReportingPeriodServiceInterface::class, ReportingPeriodService::class);
        // Bind ProjectBaseline interfaces to implementations
        $this->app->bind(ProjectBaselineRepositoryInterface::class, ProjectBaselineRepository::class);
        $this->app->bind(ProjectBaselineServiceInterface::class, ProjectBaselineService::class);
        // Bind TaskBaseline interfaces to implementations
        $this->app->bind(TaskBaselineRepositoryInterface::class, TaskBaselineRepository::class);
        $this->app->bind(TaskBaselineServiceInterface::class, TaskBaselineService::class);
        // Bind Role interfaces to implementations
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(RoleServiceInterface::class, RoleService::class);
        // Bind Permission interfaces to implementations
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
        $this->app->bind(PermissionServiceInterface::class, PermissionService::class);
        // Bind Milestone interfaces to implementations
        $this->app->bind(MilestoneRepositoryInterface::class, MilestoneRepository::class);
        $this->app->bind(MilestoneServiceInterface::class, MilestoneService::class);
        // Bind Task interfaces to implementations
        $this->app->bind(TaskRepositoryInterface::class, TaskRepository::class);
        $this->app->bind(TaskServiceInterface::class, TaskService::class);
        // Bind TaskDependency interfaces to implementations
        $this->app->bind(TaskDependencyRepositoryInterface::class, TaskDependencyRepository::class);
        $this->app->bind(TaskDependencyServiceInterface::class, TaskDependencyService::class);
        // Bind TaskAssignment interfaces to implementations
        $this->app->bind(TaskAssignmentRepositoryInterface::class, TaskAssignmentRepository::class);
        $this->app->bind(TaskAssignmentServiceInterface::class, TaskAssignmentService::class);
        // Bind StatusHistory interfaces to implementations
        $this->app->bind(StatusHistoryRepositoryInterface::class, StatusHistoryRepository::class);
        $this->app->bind(StatusHistoryServiceInterface::class, StatusHistoryService::class);
        // Bind TimeEntry interfaces to implementations
        $this->app->bind(TimeEntryRepositoryInterface::class, TimeEntryRepository::class);
        $this->app->bind(TimeEntryServiceInterface::class, TimeEntryService::class);
        // Bind Comment interfaces to implementations
        $this->app->bind(CommentRepositoryInterface::class, CommentRepository::class);
        $this->app->bind(CommentServiceInterface::class, CommentService::class);
        // Bind Attachment interfaces to implementations
        $this->app->bind(AttachmentRepositoryInterface::class, AttachmentRepository::class);
        $this->app->bind(AttachmentServiceInterface::class, AttachmentService::class);
        // Bind KPI Snapshot interfaces to implementations
        $this->app->bind(KpiSnapshotRepositoryInterface::class, KpiSnapshotRepository::class);
        $this->app->bind(KpiSnapshotServiceInterface::class, KpiSnapshotService::class);
        // Bind EVM service
        $this->app->bind(EvmServiceInterface::class, EvmService::class);
        // Bind EVM cost-based service (IDR)
        $this->app->bind(EvmCostServiceInterface::class, EvmCostService::class);
        // Bind User interfaces to implementations
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enforce morph map so polymorphic types use stable aliases
        Relation::enforceMorphMap([
            'Task' => Task::class,
            'Project' => Project::class,
            'Milestone' => Milestone::class,
            'Division' => Division::class,
            'User' => User::class,
            'TimeEntry' => TimeEntry::class,
            'TaskAssignment' => TaskAssignment::class,
            'Comment' => Comment::class,
            'Attachment' => Attachment::class,
            'TaskDependency' => TaskDependency::class,
            'SpatieRole' => SpatieRole::class,
            'SpatiePermission' => SpatiePermission::class,
        ]);
    }
}
