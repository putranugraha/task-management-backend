<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Eloquent\ProjectRepository;
use App\Services\Contracts\ProjectServiceInterface;
use App\Services\Implementations\ProjectService;
use App\Repositories\Contracts\MilestoneRepositoryInterface;
use App\Repositories\Eloquent\MilestoneRepository;
use App\Services\Contracts\MilestoneServiceInterface;
use App\Services\Implementations\MilestoneService;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Eloquent\TaskRepository;
use App\Services\Contracts\TaskServiceInterface;
use App\Services\Implementations\TaskService;
use App\Repositories\Contracts\TaskDependencyRepositoryInterface;
use App\Repositories\Eloquent\TaskDependencyRepository;
use App\Services\Contracts\TaskDependencyServiceInterface;
use App\Services\Implementations\TaskDependencyService;
use App\Repositories\Contracts\TaskAssignmentRepositoryInterface;
use App\Repositories\Eloquent\TaskAssignmentRepository;
use App\Services\Contracts\TaskAssignmentServiceInterface;
use App\Services\Implementations\TaskAssignmentService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Project interfaces to implementations
        $this->app->bind(ProjectRepositoryInterface::class, ProjectRepository::class);
        $this->app->bind(ProjectServiceInterface::class, ProjectService::class);
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
