<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\AttachmentStoreRequest;
use App\Http\Requests\AttachmentUpdateRequest;
use App\Http\Requests\AttachmentUploadRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Notifications\TaskActivityNotification;
use App\Services\Contracts\AttachmentServiceInterface;

class AttachmentController extends Controller
{
    protected AttachmentServiceInterface $service;

    public function __construct(AttachmentServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        // Resolve alias routes to entity_type/entity_id
        $entityMap = ['tasks' => 'Task', 'projects' => 'Project', 'milestones' => 'Milestone'];
        $entityType = $request->query('entity_type');
        $entityId = $request->query('entity_id');
        $userId = $request->query('user_id');
        $include = $request->query('include'); // entity,uploader

        foreach ($entityMap as $segment => $type) {
            if ($request->is("api/{$segment}/*/attachments")) {
                $entityType = $type;
                $entityId = $request->route(substr($segment, 0, -1));
                break;
            }
        }

        $filters = [];

        if ($entityType && $entityId) {
            $filters['entity_type'] = $entityType;
            $filters['entity_id'] = $entityId;
        }

        if ($userId) {
            $filters['uploaded_by'] = $userId;
        }

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0) {
            $perPage = 20;
        }

        $items = $this->service->paginateAttachments($filters, $perPage);

        if ($include) {
            $map = ['entity' => 'entity', 'uploader' => 'uploader'];
            $rels = collect(explode(',', $include))
                ->map(fn ($s) => trim($s))
                ->filter()
                ->map(fn ($key) => $map[$key] ?? null)
                ->filter()
                ->values()
                ->all();

            if (!empty($rels)) {
                $items->getCollection()->load($rels);
            }
        }

        return AttachmentResource::collection($items);
    }

    public function store(AttachmentStoreRequest $request)
    {
        $row = $this->service->createAttachment($request->validated());
        if (!$row) return response()->json(['message' => 'Gagal membuat attachment'], 400);
        return new AttachmentResource($row);
    }

    /**
     * Upload a file and create an attachment for a specific Task.
     * Route: POST /tasks/{task}/attachments
     */
    public function storeForTask(Task $task, AttachmentUploadRequest $request)
    {
        $file = $request->file('file');

        // Store file on the configured public disk under attachments/Y/m
        $directory = 'attachments/'.now()->format('Y/m');
        $storedPath = $file->store($directory, 'public');

        $data = [
            'entity_type' => 'Task',
            'entity_id' => $task->id,
            'uploaded_by' => $request->user()?->id,
            'filename' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'storage_path' => $storedPath,
            'size' => $file->getSize(),
            'uploaded_at' => now(),
        ];

        $row = $this->service->createAttachment($data);
        if (!$row) return response()->json(['message' => 'Gagal mengunggah attachment'], 400);

        $actor = $request->user();
        $payload = [
            'task_id' => $task->id,
            'task_title' => $task->title,
            'entity_type' => 'Task',
            'entity_id' => $task->id,
            'attachment_id' => $row->id,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'message' => 'Attachment baru di-upload pada task '.$task->title,
        ];

        $managerAssignments = TaskAssignment::where('task_id', $task->id)
            ->where('role_on_task', 'Manager')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        $admins = User::role('Admin')->get();

        $targets = $managerAssignments->merge($admins)->unique('id');

        foreach ($targets as $target) {
            if ($actor && $target->id === $actor->id) {
                continue;
            }

            $target->notify(new TaskActivityNotification('attachment_uploaded', $payload));
        }

        return new AttachmentResource($row);
    }

    public function show(string $id)
    {
        $row = $this->service->getAttachmentById($id);
        if (!$row) return response()->json(['message' => 'Attachment tidak ditemukan'], 404);
        $include = request()->query('include');
        if ($include) {
            $map = ['entity' => 'entity', 'uploader' => 'uploader'];
            $rels = collect(explode(',', $include))->map(fn($s) => trim($s))->filter()->map(fn($key) => $map[$key] ?? null)->filter()->values()->all();
            if (!empty($rels)) $row->load($rels);
        }
        return new AttachmentResource($row);
    }

    public function update(AttachmentUpdateRequest $request, string $id)
    {
        $row = $this->service->updateAttachment($id, $request->validated());
        if (!$row) return response()->json(['message' => 'Attachment tidak ditemukan atau invalid'], 404);
        return new AttachmentResource($row);
    }

    public function destroy(string $id)
    {
        $deleted = $this->service->deleteAttachment($id);
        if (!$deleted) return response()->json(['message' => 'Attachment tidak ditemukan'], 404);
        return response()->json(['message' => 'Attachment berhasil dihapus']);
    }

    /**
     * Approve an attachment (mark as verified by current user).
     * Route: PATCH /attachments/{attachment}/approve
     */
    public function approve(string $id, Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'User tidak terautentik'], 401);
        }

        if (!$user->hasAnyRole(['Admin', 'Manager', 'Super Admin'])) {
            return response()->json(['message' => 'Anda tidak memiliki hak untuk meng-approve attachment'], 403);
        }

        $current = $this->service->getAttachmentById($id);
        if (!$current) {
            return response()->json(['message' => 'Attachment tidak ditemukan'], 404);
        }

        if (in_array($current->status, ['Approved', 'Rejected'], true)) {
            return response()->json(['message' => 'Attachment sudah memiliki status final'], 400);
        }

        $row = $this->service->updateAttachment($id, [
            'status' => 'Approved',
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        if (!$row) {
            return response()->json(['message' => 'Attachment tidak ditemukan atau gagal di-approve'], 404);
        }

        $actor = $user;
        $row->loadMissing('uploader', 'entity');

        $task = null;
        if ($row->entity_type === 'Task') {
            $task = $row->entity instanceof Task ? $row->entity : Task::find($row->entity_id);
        }

        $payload = [
            'task_id' => $task?->id,
            'task_title' => $task?->title,
            'entity_type' => $row->entity_type,
            'entity_id' => $row->entity_id,
            'attachment_id' => $row->id,
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'message' => $task
                ? 'Attachment pada task '.$task->title.' telah di-approve.'
                : 'Attachment telah di-approve.',
        ];

        $targets = collect();

        if ($row->uploader) {
            $targets->push($row->uploader);
        } elseif ($row->uploaded_by) {
            $uploader = User::find($row->uploaded_by);
            if ($uploader) {
                $targets->push($uploader);
            }
        }

        if ($task) {
            $assignedUsers = TaskAssignment::where('task_id', $task->id)
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter();

            $targets = $targets->merge($assignedUsers);
        }

        $admins = User::role('Admin')->get();
        $targets = $targets->merge($admins)->filter()->unique('id');

        foreach ($targets as $target) {
            if ($target->id === $actor->id) {
                continue;
            }

            $target->notify(new TaskActivityNotification('attachment_approved', $payload));
        }

        return new AttachmentResource($row->loadMissing('verifier'));
    }

    /**
     * Reject an attachment (mark as rejected by current user).
     * Route: PATCH /attachments/{attachment}/reject
     */
    public function reject(string $id, Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'User tidak terautentik'], 401);
        }

        if (!$user->hasAnyRole(['Admin', 'Manager', 'Super Admin'])) {
            return response()->json(['message' => 'Anda tidak memiliki hak untuk meng-reject attachment'], 403);
        }

        $current = $this->service->getAttachmentById($id);
        if (!$current) {
            return response()->json(['message' => 'Attachment tidak ditemukan'], 404);
        }

        if (in_array($current->status, ['Approved', 'Rejected'], true)) {
            return response()->json(['message' => 'Attachment sudah memiliki status final'], 400);
        }

        $row = $this->service->updateAttachment($id, [
            'status' => 'Rejected',
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        if (!$row) {
            return response()->json(['message' => 'Attachment tidak ditemukan atau gagal di-reject'], 404);
        }

        $actor = $user;
        $row->loadMissing('uploader', 'entity');

        $task = null;
        if ($row->entity_type === 'Task') {
            $task = $row->entity instanceof Task ? $row->entity : Task::find($row->entity_id);
        }

        $payload = [
            'task_id' => $task?->id,
            'task_title' => $task?->title,
            'entity_type' => $row->entity_type,
            'entity_id' => $row->entity_id,
            'attachment_id' => $row->id,
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'message' => $task
                ? 'Attachment pada task '.$task->title.' telah di-reject.'
                : 'Attachment telah di-reject.',
        ];

        $targets = collect();

        if ($row->uploader) {
            $targets->push($row->uploader);
        } elseif ($row->uploaded_by) {
            $uploader = User::find($row->uploaded_by);
            if ($uploader) {
                $targets->push($uploader);
            }
        }

        if ($task) {
            $assignedUsers = TaskAssignment::where('task_id', $task->id)
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter();

            $targets = $targets->merge($assignedUsers);
        }

        $admins = User::role('Admin')->get();
        $targets = $targets->merge($admins)->filter()->unique('id');

        foreach ($targets as $target) {
            if ($target->id === $actor->id) {
                continue;
            }

            $target->notify(new TaskActivityNotification('attachment_rejected', $payload));
        }

        return new AttachmentResource($row->loadMissing('verifier'));
    }

    public function destroyByEntity(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:Task,Project,Milestone',
            'entity_id' => 'required|integer',
        ]);
        $deleted = $this->service->deleteAttachmentsByEntity($request->input('entity_type'), (int) $request->input('entity_id'));
        if (!$deleted) return response()->json(['message' => 'Tidak ada attachment dihapus atau entity tidak ditemukan'], 404);
        return response()->json(['message' => 'Seluruh attachment untuk entity dihapus']);
    }

    public function totalSizeByEntity(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:Task,Project,Milestone',
            'entity_id' => 'required|integer',
        ]);
        $total = $this->service->getTotalSizeByEntity($request->input('entity_type'), (int) $request->input('entity_id'));
        return response()->json([
            'entity_type' => $request->input('entity_type'),
            'entity_id' => (int) $request->input('entity_id'),
            'total_size' => (int) $total,
        ]);
    }
}
