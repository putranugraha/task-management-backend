<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProjectBaselineController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportingPeriodController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StatusHistoryController;
use App\Http\Controllers\TaskAssignmentController;
use App\Http\Controllers\TaskBaselineController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskDependencyController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\KpiSnapshotController;
use App\Http\Controllers\EvmController;
use App\Http\Controllers\EvmCostController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TaskCostEntryController;

// Public auth routes (throttled)
Route::middleware(['throttle:6,1'])->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

// Protected routes (Sanctum)
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    // Profile
    Route::get('/profile', function (Request $request) {
        $user = $request->user();
        $user->loadMissing('roles');

        $primaryRole = $user->roles->first()->name ?? null;
        $dashboardType = match ($primaryRole) {
            'Admin' => 'admin',
            'Manager' => 'manager',
            'Member' => 'member',
            default => 'member',
        };
        $homePath = match ($dashboardType) {
            'admin' => '/admin/dashboard',
            'manager' => '/manager/dashboard',
            'member' => '/member/dashboard',
            default => '/dashboard',
        };

        $payload = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'job_title' => $user->job_title,
                'is_active' => (bool) $user->is_active,
                'status' => $user->status,
                'last_login_at' => optional($user->last_login_at)->toDateTimeString(),
                'role' => $primaryRole,
            ],
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'primary_role' => $primaryRole,
            'dashboard_type' => $dashboardType,
            'home_path' => $homePath,
        ];

        return response()->json($payload);
    });

    // Logout current token
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Activity logs (for authenticated users, typically viewed by Admin via FE)
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);

    // User notifications
    Route::get('/me/notifications', [NotificationController::class, 'index']);
    Route::post('/me/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
});

// Lightweight Users options for FE (read-only)
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::get('users/options', [UserController::class, 'options']);
});

// Users API (Resource) protected by Sanctum + granular permissions
Route::middleware(['auth:sanctum', 'active', 'permission:melihat users'])->group(function () {
    Route::apiResource('users', UserController::class)->only(['index','show'])->whereNumber('user');
});
Route::middleware(['auth:sanctum', 'active', 'permission:membuat users'])->group(function () {
    Route::apiResource('users', UserController::class)->only(['store'])->whereNumber('user');
});
Route::middleware(['auth:sanctum', 'active', 'permission:mengubah users'])->group(function () {
    Route::apiResource('users', UserController::class)->only(['update'])->whereNumber('user');
});
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus users'])->group(function () {
    Route::apiResource('users', UserController::class)->only(['destroy'])->whereNumber('user');
});

// Roles API
Route::middleware(['auth:sanctum', 'active', 'permission:melihat roles'])->group(function () {
    Route::apiResource('roles', RoleController::class)->only(['index','show']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:membuat roles'])->group(function () {
    Route::apiResource('roles', RoleController::class)->only(['store']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:mengubah roles'])->group(function () {
    Route::apiResource('roles', RoleController::class)->only(['update']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus roles'])->group(function () {
    Route::apiResource('roles', RoleController::class)->only(['destroy']);
});

// Permissions API
Route::middleware(['auth:sanctum', 'active', 'permission:melihat permissions'])->group(function () {
    Route::apiResource('permissions', PermissionController::class)->only(['index','show']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:membuat permissions'])->group(function () {
    Route::apiResource('permissions', PermissionController::class)->only(['store']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:mengubah permissions'])->group(function () {
    Route::apiResource('permissions', PermissionController::class)->only(['update']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus permissions'])->group(function () {
    Route::apiResource('permissions', PermissionController::class)->only(['destroy']);
});


// Divisions API
// Read-only for those with 'melihat project'
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::apiResource('divisions', DivisionController::class)->only(['index','show']);
    Route::get('divisions/{division}/users-count', [DivisionController::class, 'usersCount']);
});

// Manage divisions with project write permissions
Route::middleware(['auth:sanctum', 'active', 'permission:membuat project'])->group(function () {
    Route::apiResource('divisions', DivisionController::class)->only(['store']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:mengubah project'])->group(function () {
    Route::apiResource('divisions', DivisionController::class)->only(['update']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus project'])->group(function () {
    Route::apiResource('divisions', DivisionController::class)->only(['destroy']);
});
// Projects API as apiResource
// Read-only for those with 'melihat project'
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    // Stats route harus didefinisikan sebelum apiResource
    // agar tidak tertimpa oleh binding projects/{project}
    Route::get('projects/stats', [ProjectController::class, 'stats']);
    Route::apiResource('projects', ProjectController::class)->only(['index','show']);
    Route::apiResource('project-baselines', ProjectBaselineController::class)->only(['index','show']);
    Route::get('projects/{project}/baselines', [ProjectBaselineController::class, 'index']);
    Route::get('projects/{project}/baselines/latest', [ProjectBaselineController::class, 'latest']);
    Route::apiResource('task-baselines', TaskBaselineController::class)->only(['index','show']);
    Route::get('project-baselines/{baseline}/task-baselines', [TaskBaselineController::class, 'index']);
    Route::get('tasks/{task}/task-baselines', [TaskBaselineController::class, 'index']);
    Route::get('project-baselines/{baseline}/task-baselines/total-weight', [TaskBaselineController::class, 'totalWeight']);
    Route::apiResource('reporting-periods', ReportingPeriodController::class)->only(['index','show']);
    Route::get('projects/{project}/reporting-periods', [ReportingPeriodController::class, 'index']);
    // KPI Snapshots read-only
    Route::apiResource('kpi-snapshots', KpiSnapshotController::class)->only(['index','show']);
    Route::get('projects/{project}/kpi-snapshots', [KpiSnapshotController::class, 'index']);
    Route::get('projects/{project}/kpi-snapshots/average-cycle-time', [KpiSnapshotController::class, 'averageCycleTimeByProject']);
    // EVM real-time aggregation
    Route::get('projects/{project}/evm', [EvmController::class, 'projectEvm']);
    // EVM cost-based (IDR) aggregation (separate endpoint to avoid mixing units with effort-based EVM)
    Route::get('projects/{project}/evm-cost', [EvmCostController::class, 'projectEvmCost']);
});

// Project creation
Route::middleware(['auth:sanctum', 'active', 'permission:membuat project'])->group(function () {
    Route::apiResource('projects', ProjectController::class)->only(['store']);
    Route::apiResource('project-baselines', ProjectBaselineController::class)->only(['store']);
    Route::apiResource('task-baselines', TaskBaselineController::class)->only(['store']);
    Route::apiResource('reporting-periods', ReportingPeriodController::class)->only(['store']);
    Route::apiResource('kpi-snapshots', KpiSnapshotController::class)->only(['store']);
    Route::post('projects/{project}/kpi-snapshots/generate', [KpiSnapshotController::class, 'generateForProject']);
});

// Project updates
Route::middleware(['auth:sanctum', 'active', 'permission:mengubah project'])->group(function () {
    Route::apiResource('projects', ProjectController::class)->only(['update']);
    Route::apiResource('project-baselines', ProjectBaselineController::class)->only(['update']);
    Route::apiResource('task-baselines', TaskBaselineController::class)->only(['update']);
    Route::apiResource('reporting-periods', ReportingPeriodController::class)->only(['update']);
    Route::apiResource('kpi-snapshots', KpiSnapshotController::class)->only(['update']);
    Route::patch('projects/{project}/status', [ProjectController::class, 'updateStatus']);
});

// Project deletes
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus project'])->group(function () {
    Route::apiResource('projects', ProjectController::class)->only(['destroy']);
    Route::apiResource('project-baselines', ProjectBaselineController::class)->only(['destroy']);
    Route::apiResource('task-baselines', TaskBaselineController::class)->only(['destroy']);
    Route::delete('projects/{project}/baselines', [ProjectBaselineController::class, 'destroyByProject']);
    Route::delete('project-baselines/{baseline}/task-baselines', [TaskBaselineController::class, 'destroyByBaseline']);
    Route::apiResource('reporting-periods', ReportingPeriodController::class)->only(['destroy']);
    Route::delete('projects/{project}/reporting-periods', [ReportingPeriodController::class, 'destroyByProject']);
    Route::apiResource('kpi-snapshots', KpiSnapshotController::class)->only(['destroy']);
    Route::delete('projects/{project}/kpi-snapshots', [KpiSnapshotController::class, 'destroyByProject']);
});

// Milestones API as apiResource
// Read-only for those with 'melihat project'
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    // Stats route sebelum apiResource agar tidak tertimpa oleh binding {milestone}
    Route::get('milestones/stats', [MilestoneController::class, 'stats']);
    Route::apiResource('milestones', MilestoneController::class)->only(['index','show']);
    // Nested listing by project
    Route::get('projects/{project}/milestones', [MilestoneController::class, 'indexByProject']);
});

// Manage milestones with project write permissions
Route::middleware(['auth:sanctum', 'active', 'permission:membuat project'])->group(function () {
    Route::apiResource('milestones', MilestoneController::class)->only(['store']);
    // Nested create by project
    Route::post('projects/{project}/milestones', [MilestoneController::class, 'storeForProject']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:mengubah project'])->group(function () {
    Route::apiResource('milestones', MilestoneController::class)->only(['update']);
    Route::patch('milestones/{milestone}/status', [MilestoneController::class, 'updateStatus']);
    Route::patch('milestones/{milestone}/complete', [MilestoneController::class, 'complete']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus project'])->group(function () {
    Route::apiResource('milestones', MilestoneController::class)->only(['destroy']);
});

// Tasks API as apiResource
// Read-only for those with 'melihat tugas'
Route::middleware(['auth:sanctum', 'active', 'permission:melihat tugas'])->group(function () {
    // Stats route sebelum apiResource agar tidak tertimpa oleh binding {task}
    Route::get('tasks/stats', [TaskController::class, 'stats']);
    Route::apiResource('tasks', TaskController::class)->only(['index','show']);
    // Nested listing by project
    Route::get('projects/{project}/tasks', [TaskController::class, 'indexByProject']);
    // Nested listing by milestone
    Route::get('milestones/{milestone}/tasks', [TaskController::class, 'indexByMilestone']);
});

// Manage tasks with task write permissions
Route::middleware(['auth:sanctum', 'active', 'permission:membuat tugas'])->group(function () {
    Route::apiResource('tasks', TaskController::class)->only(['store']);
    // Nested create by project (optional convenience)
    Route::post('projects/{project}/tasks', [TaskController::class, 'storeForProject']);
    // Nested create by milestone
    Route::post('milestones/{milestone}/tasks', [TaskController::class, 'storeForMilestone']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:mengubah tugas'])->group(function () {
    Route::apiResource('tasks', TaskController::class)->only(['update']);
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus']);
    Route::patch('tasks/{task}/progress', [TaskController::class, 'updateProgress']);
    Route::patch('tasks/{task}/complete', [TaskController::class, 'complete']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus tugas'])->group(function () {
    Route::apiResource('tasks', TaskController::class)->only(['destroy']);
});

// Task Dependencies API
// Read-only for those with 'melihat tugas'
Route::middleware(['auth:sanctum', 'active', 'permission:melihat tugas'])->group(function () {
    Route::apiResource('task-dependencies', TaskDependencyController::class)->only(['index','show']);
    // Convenience endpoints by task
    Route::get('tasks/{task}/dependencies', [TaskDependencyController::class, 'index']); // use query task_id
    Route::get('tasks/{task}/dependents', [TaskDependencyController::class, 'index']); // use query depends_on_task_id
});

// Manage dependencies with task write permissions
Route::middleware(['auth:sanctum', 'active', 'permission:membuat tugas'])->group(function () {
    Route::apiResource('task-dependencies', TaskDependencyController::class)->only(['store']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:mengubah tugas'])->group(function () {
    Route::apiResource('task-dependencies', TaskDependencyController::class)->only(['update']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus tugas'])->group(function () {
    Route::apiResource('task-dependencies', TaskDependencyController::class)->only(['destroy']);
    Route::delete('tasks/{task}/dependencies', [TaskDependencyController::class, 'destroyByTask']);
});

// Task Assignments API
// Read-only
Route::middleware(['auth:sanctum', 'active', 'permission:melihat tugas'])->group(function () {
    Route::apiResource('task-assignments', TaskAssignmentController::class)->only(['index','show']);
    Route::get('tasks/{task}/assignments', [TaskAssignmentController::class, 'index']);
    Route::get('users/{user}/assignments', [TaskAssignmentController::class, 'index']);
});

// Manage assignments
Route::middleware(['auth:sanctum', 'active', 'permission:membuat tugas'])->group(function () {
    Route::apiResource('task-assignments', TaskAssignmentController::class)->only(['store']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:mengubah tugas'])->group(function () {
    Route::apiResource('task-assignments', TaskAssignmentController::class)->only(['update']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus tugas'])->group(function () {
    Route::apiResource('task-assignments', TaskAssignmentController::class)->only(['destroy']);
    Route::delete('tasks/{task}/assignments', [TaskAssignmentController::class, 'destroyByTask']);
    Route::delete('users/{user}/assignments', [TaskAssignmentController::class, 'destroyByUser']);
});

// Status Histories API
// Read-only
Route::middleware(['auth:sanctum', 'active', 'permission:melihat tugas'])->group(function () {
    Route::apiResource('status-histories', StatusHistoryController::class)->only(['index','show']);
    Route::get('tasks/{task}/status-histories', [StatusHistoryController::class, 'index']);
});

// Manage histories
Route::middleware(['auth:sanctum', 'active', 'permission:mengubah tugas'])->group(function () {
    Route::apiResource('status-histories', StatusHistoryController::class)->only(['store']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus tugas'])->group(function () {
    Route::apiResource('status-histories', StatusHistoryController::class)->only(['destroy']);
    Route::delete('status-histories/by-entity', [StatusHistoryController::class, 'destroyByEntity']);
});

// Time Entries API
// Read-only
Route::middleware(['auth:sanctum', 'active', 'permission:melihat tugas'])->group(function () {
    Route::apiResource('time-entries', TimeEntryController::class)->only(['index','show']);
    Route::get('tasks/{task}/time-entries', [TimeEntryController::class, 'index']);
    Route::get('users/{user}/time-entries', [TimeEntryController::class, 'index']);
    Route::get('tasks/{task}/time-entries/total-hours', [TimeEntryController::class, 'totalHoursByTask']);
    Route::get('users/{user}/time-entries/total-hours', [TimeEntryController::class, 'totalHoursByUser']);
    // Project-level aggregates (as-of date) for schedule/effort analysis
    Route::get('projects/{project}/time-entries/total-hours', [TimeEntryController::class, 'totalHoursByProject']);
    Route::get('projects/{project}/time-entries/top-tasks', [TimeEntryController::class, 'topTasksByProject']);
});

// Cost Entries (Actual Cost ledger)
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::get('tasks/{task}/cost-entries', [TaskCostEntryController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'active', 'permission:mengubah project'])->group(function () {
    Route::post('tasks/{task}/cost-entries', [TaskCostEntryController::class, 'store']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus project'])->group(function () {
    Route::delete('tasks/{task}/cost-entries/{costEntry}', [TaskCostEntryController::class, 'destroy']);
});

// Manage time entries
Route::middleware(['auth:sanctum', 'active', 'permission:membuat tugas'])->group(function () {
    Route::apiResource('time-entries', TimeEntryController::class)->only(['store']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:mengubah tugas'])->group(function () {
    Route::apiResource('time-entries', TimeEntryController::class)->only(['update']);
    // Upsert endpoint to simplify FE client when logging time repeatedly on same day
    Route::post('time-entries/upsert', [TimeEntryController::class, 'storeOrUpdate']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus tugas'])->group(function () {
    Route::apiResource('time-entries', TimeEntryController::class)->only(['destroy']);
});

// Log time entries for own tasks (simplified via nested routes)
Route::middleware(['auth:sanctum', 'active', 'permission:mengisi entri waktu'])->group(function () {
    Route::post('tasks/{task}/time-entries', [TimeEntryController::class, 'storeForTask']);
    Route::post('tasks/{task}/time-entries/upsert', [TimeEntryController::class, 'storeOrUpdateForTask']);
});

// Comments API
// Read-only
Route::middleware(['auth:sanctum', 'active', 'permission:melihat komentar'])->group(function () {
    Route::apiResource('comments', CommentController::class)->only(['index','show']);
    // Aliases per entity
    Route::get('tasks/{task}/comments', [CommentController::class, 'index']);
    Route::get('projects/{project}/comments', [CommentController::class, 'index']);
    Route::get('milestones/{milestone}/comments', [CommentController::class, 'index']);
    // Count per entity
    Route::get('comments/count', [CommentController::class, 'countByEntity']);
});

// Manage comments
Route::middleware(['auth:sanctum', 'active', 'permission:membuat komentar'])->group(function () {
    Route::apiResource('comments', CommentController::class)->only(['store']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:mengubah komentar'])->group(function () {
    Route::apiResource('comments', CommentController::class)->only(['update']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus komentar'])->group(function () {
    Route::apiResource('comments', CommentController::class)->only(['destroy']);
    Route::delete('comments/by-entity', [CommentController::class, 'destroyByEntity']);
});

// Attachments API
// Read-only
Route::middleware(['auth:sanctum', 'active', 'permission:melihat lampiran'])->group(function () {
    Route::apiResource('attachments', AttachmentController::class)->only(['index','show']);
    Route::get('tasks/{task}/attachments', [AttachmentController::class, 'index']);
    Route::get('projects/{project}/attachments', [AttachmentController::class, 'index']);
    Route::get('milestones/{milestone}/attachments', [AttachmentController::class, 'index']);
    Route::get('attachments/total-size', [AttachmentController::class, 'totalSizeByEntity']);
});

// Manage attachments
Route::middleware(['auth:sanctum', 'active', 'permission:membuat lampiran'])->group(function () {
    Route::apiResource('attachments', AttachmentController::class)->only(['store']);
    Route::post('tasks/{task}/attachments', [AttachmentController::class, 'storeForTask']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:mengubah lampiran'])->group(function () {
    Route::apiResource('attachments', AttachmentController::class)->only(['update']);
    Route::patch('attachments/{attachment}/approve', [AttachmentController::class, 'approve']);
    Route::patch('attachments/{attachment}/reject', [AttachmentController::class, 'reject']);
});
Route::middleware(['auth:sanctum', 'active', 'permission:menghapus lampiran'])->group(function () {
    Route::apiResource('attachments', AttachmentController::class)->only(['destroy']);
    Route::delete('attachments/by-entity', [AttachmentController::class, 'destroyByEntity']);
});











