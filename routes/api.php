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
use App\Http\Controllers\UserController;

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
        return response()->json([
            'user' => $user, // Optionally wrap with Resource
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    });

    // Logout current token
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Users API (Resource) protected by Sanctum + Permission
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola users'])->group(function () {
    Route::apiResource('users', UserController::class);
});

// Roles API
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola roles'])->group(function () {
    Route::apiResource('roles', RoleController::class)->only(['index','store','show','update','destroy']);
});

// Permissions API
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola permissions'])->group(function () {
    Route::apiResource('permissions', PermissionController::class)->only(['index','store','show','update','destroy']);
});


// Divisions API
// Read-only for those with 'melihat project'
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::apiResource('divisions', DivisionController::class)->only(['index','show']);
    Route::get('divisions/{division}/users-count', [DivisionController::class, 'usersCount']);
});

// Manage divisions with 'mengelola project'
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola project'])->group(function () {
    Route::apiResource('divisions', DivisionController::class)->only(['store','update','destroy']);
});
// Projects API as apiResource
// Read-only for those with 'melihat project'
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
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
});

// Management for those with 'mengelola project'
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola project'])->group(function () {
    Route::apiResource('projects', ProjectController::class)->only(['store','update','destroy']);
    Route::apiResource('project-baselines', ProjectBaselineController::class)->only(['store','update','destroy']);
    Route::apiResource('task-baselines', TaskBaselineController::class)->only(['store','update','destroy']);
    Route::delete('projects/{project}/baselines', [ProjectBaselineController::class, 'destroyByProject']);
    Route::delete('project-baselines/{baseline}/task-baselines', [TaskBaselineController::class, 'destroyByBaseline']);
    Route::apiResource('reporting-periods', ReportingPeriodController::class)->only(['store','update','destroy']);
    Route::delete('projects/{project}/reporting-periods', [ReportingPeriodController::class, 'destroyByProject']);
    // KPI Snapshots manage
    Route::apiResource('kpi-snapshots', KpiSnapshotController::class)->only(['store','update','destroy']);
    Route::delete('projects/{project}/kpi-snapshots', [KpiSnapshotController::class, 'destroyByProject']);
    Route::patch('projects/{project}/status', [ProjectController::class, 'updateStatus']);
});

// Milestones API as apiResource
// Read-only for those with 'melihat project'
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::apiResource('milestones', MilestoneController::class)->only(['index','show']);
    // Nested listing by project
    Route::get('projects/{project}/milestones', [MilestoneController::class, 'indexByProject']);
});

// Manage milestones with 'mengelola project'
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola project'])->group(function () {
    Route::apiResource('milestones', MilestoneController::class)->only(['store','update','destroy']);
    // Nested create by project
    Route::post('projects/{project}/milestones', [MilestoneController::class, 'storeForProject']);
    Route::patch('milestones/{milestone}/status', [MilestoneController::class, 'updateStatus']);
    Route::patch('milestones/{milestone}/complete', [MilestoneController::class, 'complete']);
});

// Tasks API as apiResource
// Read-only for those with 'melihat project'
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::apiResource('tasks', TaskController::class)->only(['index','show']);
    // Nested listing by project
    Route::get('projects/{project}/tasks', [TaskController::class, 'indexByProject']);
    // Nested listing by milestone
    Route::get('milestones/{milestone}/tasks', [TaskController::class, 'indexByMilestone']);
});

// Manage tasks with 'mengelola project'
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola project'])->group(function () {
    Route::apiResource('tasks', TaskController::class)->only(['store','update','destroy']);
    // Nested create by project (optional convenience)
    Route::post('projects/{project}/tasks', [TaskController::class, 'storeForProject']);
    // Nested create by milestone
    Route::post('milestones/{milestone}/tasks', [TaskController::class, 'storeForMilestone']);
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus']);
    Route::patch('tasks/{task}/progress', [TaskController::class, 'updateProgress']);
    Route::patch('tasks/{task}/complete', [TaskController::class, 'complete']);
});

// Task Dependencies API
// Read-only for those with 'melihat project'
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::apiResource('task-dependencies', TaskDependencyController::class)->only(['index','show']);
    // Convenience endpoints by task
    Route::get('tasks/{task}/dependencies', [TaskDependencyController::class, 'index']); // use query task_id
    Route::get('tasks/{task}/dependents', [TaskDependencyController::class, 'index']); // use query depends_on_task_id
});

// Manage dependencies with 'mengelola project'
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola project'])->group(function () {
    Route::apiResource('task-dependencies', TaskDependencyController::class)->only(['store','update','destroy']);
    Route::delete('tasks/{task}/dependencies', [TaskDependencyController::class, 'destroyByTask']);
});

// Task Assignments API
// Read-only
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::apiResource('task-assignments', TaskAssignmentController::class)->only(['index','show']);
    Route::get('tasks/{task}/assignments', [TaskAssignmentController::class, 'index']);
    Route::get('users/{user}/assignments', [TaskAssignmentController::class, 'index']);
});

// Manage assignments
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola project'])->group(function () {
    Route::apiResource('task-assignments', TaskAssignmentController::class)->only(['store','update','destroy']);
    Route::delete('tasks/{task}/assignments', [TaskAssignmentController::class, 'destroyByTask']);
    Route::delete('users/{user}/assignments', [TaskAssignmentController::class, 'destroyByUser']);
});

// Status Histories API
// Read-only
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::apiResource('status-histories', StatusHistoryController::class)->only(['index','show']);
    Route::get('tasks/{task}/status-histories', [StatusHistoryController::class, 'index']);
});

// Manage histories
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola project'])->group(function () {
    Route::apiResource('status-histories', StatusHistoryController::class)->only(['store','destroy']);
    Route::delete('status-histories/by-entity', [StatusHistoryController::class, 'destroyByEntity']);
});

// Time Entries API
// Read-only
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::apiResource('time-entries', TimeEntryController::class)->only(['index','show']);
    Route::get('tasks/{task}/time-entries', [TimeEntryController::class, 'index']);
    Route::get('users/{user}/time-entries', [TimeEntryController::class, 'index']);
    Route::get('tasks/{task}/time-entries/total-hours', [TimeEntryController::class, 'totalHoursByTask']);
    Route::get('users/{user}/time-entries/total-hours', [TimeEntryController::class, 'totalHoursByUser']);
});

// Manage time entries
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola project'])->group(function () {
    Route::apiResource('time-entries', TimeEntryController::class)->only(['store','update','destroy']);
});

// Comments API
// Read-only
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::apiResource('comments', CommentController::class)->only(['index','show']);
    // Aliases per entity
    Route::get('tasks/{task}/comments', [CommentController::class, 'index']);
    Route::get('projects/{project}/comments', [CommentController::class, 'index']);
    Route::get('milestones/{milestone}/comments', [CommentController::class, 'index']);
    // Count per entity
    Route::get('comments/count', [CommentController::class, 'countByEntity']);
});

// Manage comments
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola project'])->group(function () {
    Route::apiResource('comments', CommentController::class)->only(['store','update','destroy']);
    Route::delete('comments/by-entity', [CommentController::class, 'destroyByEntity']);
});

// Attachments API
// Read-only
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::apiResource('attachments', AttachmentController::class)->only(['index','show']);
    Route::get('tasks/{task}/attachments', [AttachmentController::class, 'index']);
    Route::get('projects/{project}/attachments', [AttachmentController::class, 'index']);
    Route::get('milestones/{milestone}/attachments', [AttachmentController::class, 'index']);
    Route::get('attachments/total-size', [AttachmentController::class, 'totalSizeByEntity']);
});

// Manage attachments
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola project'])->group(function () {
    Route::apiResource('attachments', AttachmentController::class)->only(['store','update','destroy']);
    Route::delete('attachments/by-entity', [AttachmentController::class, 'destroyByEntity']);
});


























