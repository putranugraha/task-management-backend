<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\MilestoneController;

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

// Projects API as apiResource
// Read-only for those with 'melihat project'
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::apiResource('projects', ProjectController::class)->only(['index','show']);
});

// Management for those with 'mengelola project'
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola project'])->group(function () {
    Route::apiResource('projects', ProjectController::class)->only(['store','update','destroy']);
    Route::patch('projects/{project}/status', [ProjectController::class, 'updateStatus']);
});

// Milestones API as apiResource
// Read-only for those with 'melihat project'
Route::middleware(['auth:sanctum', 'active', 'permission:melihat project'])->group(function () {
    Route::apiResource('milestones', MilestoneController::class)->only(['index','show']);
});

// Manage milestones with 'mengelola project'
Route::middleware(['auth:sanctum', 'active', 'permission:mengelola project'])->group(function () {
    Route::apiResource('milestones', MilestoneController::class)->only(['store','update','destroy']);
    Route::patch('milestones/{milestone}/status', [MilestoneController::class, 'updateStatus']);
    Route::patch('milestones/{milestone}/complete', [MilestoneController::class, 'complete']);
});

