<?php

namespace App\Repositories\Eloquent;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProjectRepository implements ProjectRepositoryInterface
{
    private const STATS_CACHE_TTL_SECONDS = 60;

    /** @var Project */
    protected $model;

    public function __construct(Project $model)
    {
        $this->model = $model;
    }

    public function getAllProjects()
    {
        return $this->model->with(['divisionOwner'])->get();
    }

    public function getProjectById($id)
    {
        try {
            return $this->model->with(['divisionOwner'])->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Project with ID {$id} not found.");
            return null;
        }
    }

    public function getProjectByName($name)
    {
        return $this->model->where('name', $name)->with(['divisionOwner'])->first();
    }

    public function getProjectByClient($clientName)
    {
        return $this->model->where('client_name', $clientName)->with(['divisionOwner'])->get();
    }

    public function getProjectsByDivision($divisionId)
    {
        return $this->model->where('division_owner_id', $divisionId)->with(['divisionOwner'])->get();
    }

    public function getProjectsByStatus($status)
    {
        return $this->model->where('status', $status)->with(['divisionOwner'])->get();
    }

    public function getProjectsByDateRange($startDate, $endDate)
    {
        return $this->model
            ->whereDate('start_planned', '>=', $startDate)
            ->whereDate('end_planned', '<=', $endDate)
            ->with(['divisionOwner'])
            ->get();
    }

    public function createProject(array $data)
    {
        try {
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error("Failed to create project: {$e->getMessage()}");
            return null;
        }
    }

    public function updateProject($id, array $data)
    {
        $project = $this->find($id);
        if (!$project) {
            return null;
        }

        try {
            $project->update($data);
            return $project->fresh(['divisionOwner']);
        } catch (\Exception $e) {
            Log::error("Failed to update project {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteProject($id)
    {
        $project = $this->find($id);
        if (!$project) {
            return false;
        }

        try {
            $project->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete project {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function getArchivedProjects(array $filters = [], int $perPage = 20)
    {
        $query = $this->model
            ->onlyTrashed()
            ->select([
                'id',
                'name',
                'client_name',
                'value_amount',
                'division_owner_id',
                'start_planned',
                'end_planned',
                'status',
                'created_at',
                'deleted_at',
            ])
            ->with(['divisionOwner:id,name,email']);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('deleted_at')
            ->paginate($perPage);
    }

    public function restoreProject($id)
    {
        $project = $this->model->onlyTrashed()->find($id);
        if (!$project) {
            return null;
        }

        try {
            $project->restore();
            return $project->fresh(['divisionOwner']);
        } catch (\Exception $e) {
            Log::error("Failed to restore project {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function forceDeleteArchivedProject($id): bool
    {
        $project = $this->model->onlyTrashed()->find($id);
        if (!$project) {
            return false;
        }

        try {
            $attachmentPaths = DB::transaction(function () use ($project) {
                $taskIds = Task::withTrashed()
                    ->where('project_id', $project->id)
                    ->pluck('id');

                $milestoneIds = Milestone::withTrashed()
                    ->where('project_id', $project->id)
                    ->pluck('id');

                $attachmentPaths = Attachment::query()
                    ->where(function ($query) use ($project, $milestoneIds, $taskIds) {
                        $query->where(function ($projectQuery) use ($project) {
                            $projectQuery
                                ->whereIn('entity_type', [Project::class, class_basename(Project::class)])
                                ->where('entity_id', $project->id);
                        });

                        if ($milestoneIds->isNotEmpty()) {
                            $query->orWhere(function ($milestoneQuery) use ($milestoneIds) {
                                $milestoneQuery
                                    ->whereIn('entity_type', [Milestone::class, class_basename(Milestone::class)])
                                    ->whereIn('entity_id', $milestoneIds->all());
                            });
                        }

                        if ($taskIds->isNotEmpty()) {
                            $query->orWhere(function ($taskQuery) use ($taskIds) {
                                $taskQuery
                                    ->whereIn('entity_type', [Task::class, class_basename(Task::class)])
                                    ->whereIn('entity_id', $taskIds->all());
                            });
                        }
                    })
                    ->whereNotNull('storage_path')
                    ->pluck('storage_path')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $this->deletePolymorphicRows(Comment::class, Project::class, [$project->id]);
                $this->deletePolymorphicRows(Attachment::class, Project::class, [$project->id]);

                if ($milestoneIds->isNotEmpty()) {
                    $this->deletePolymorphicRows(Comment::class, Milestone::class, $milestoneIds->all());
                    $this->deletePolymorphicRows(Attachment::class, Milestone::class, $milestoneIds->all());
                }

                if ($taskIds->isNotEmpty()) {
                    $this->deletePolymorphicRows(Comment::class, Task::class, $taskIds->all());
                    $this->deletePolymorphicRows(Attachment::class, Task::class, $taskIds->all());
                }

                $project->forceDelete();

                return $attachmentPaths;
            });

            if (!empty($attachmentPaths)) {
                Storage::disk('public')->delete($attachmentPaths);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to permanently delete project {$id}: {$e->getMessage()}");
            return false;
        }
    }

    private function deletePolymorphicRows(string $modelClass, string $entityClass, array $entityIds): void
    {
        if (empty($entityIds)) {
            return;
        }

        $modelClass::query()
            ->whereIn('entity_type', [$entityClass, class_basename($entityClass)])
            ->whereIn('entity_id', $entityIds)
            ->delete();
    }

    public function updateProjectStatus($id, $status)
    {
        $project = $this->find($id);
        if (!$project) {
            return null;
        }

        $project->status = $status;
        $project->save();

        return $project->fresh(['divisionOwner']);
    }

    public function paginateProjects(array $filters = [], int $perPage = 20)
    {
        $query = $this->model
            ->select([
                'id',
                'name',
                'client_name',
                'value_amount',
                'division_owner_id',
                'start_planned',
                'end_planned',
                'status',
                'created_at',
            ])
            ->with(['divisionOwner:id,name,email']);

        $this->applyFilters($query, $filters);

        // Show newest projects first so recent creations
        // (e.g. id 36) appear on the first page.
        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Hitung jumlah proyek total dan per status berdasarkan filter sederhana.
     *
     * @param array $filters
     * @return array{total:int,by_status:array<string,int>}
     */
    public function getProjectStatusCounts(array $filters = []): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $cacheKey = 'projects:status-counts:' . md5(json_encode($normalizedFilters));

        return Cache::remember($cacheKey, self::STATS_CACHE_TTL_SECONDS, function () use ($normalizedFilters) {
            $baseQuery = $this->model->newQuery();

            if (isset($normalizedFilters['status'])) {
                $baseQuery->where('status', $normalizedFilters['status']);
            }

            if (isset($normalizedFilters['division_owner_id'])) {
                $baseQuery->where('division_owner_id', $normalizedFilters['division_owner_id']);
            }

            if (isset($normalizedFilters['client_name'])) {
                $baseQuery->where('client_name', $normalizedFilters['client_name']);
            }

            if (!empty($normalizedFilters['search'])) {
                $search = mb_strtolower($normalizedFilters['search']);
                $baseQuery->where(function ($q) use ($search) {
                    $like = "%{$search}%";
                    $q->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(client_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(status) LIKE ?', [$like]);
                });
            }

            $total = (clone $baseQuery)->count();

            $byStatus = (clone $baseQuery)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->toArray();

            return [
                'total' => (int) $total,
                'by_status' => array_map('intval', $byStatus),
            ];
        });
    }

    protected function normalizeFilters(array $filters): array
    {
        ksort($filters);

        return array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $filters);
    }

    protected function applyFilters($query, array $filters): void
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['division_owner_id'])) {
            $query->where('division_owner_id', $filters['division_owner_id']);
        }

        if (isset($filters['client_name'])) {
            $query->where('client_name', $filters['client_name']);
        }

        if (!empty($filters['search'])) {
            $search = mb_strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $like = "%{$search}%";
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(client_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(status) LIKE ?', [$like]);
            });
        }
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Project with ID {$id} not found.");
            return null;
        }
    }
}
