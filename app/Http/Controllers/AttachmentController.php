<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\AttachmentStoreRequest;
use App\Http\Requests\AttachmentUpdateRequest;
use App\Http\Resources\AttachmentResource;
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

        if ($entityType && $entityId) {
            $items = $this->service->getAttachmentsByEntity($entityType, $entityId);
        } elseif ($userId) {
            $items = $this->service->getAttachmentsByUser($userId);
        } else {
            $items = $this->service->getAllAttachments();
        }

        if ($include) {
            $map = ['entity' => 'entity', 'uploader' => 'uploader'];
            $rels = collect(explode(',', $include))
                ->map(fn($s) => trim($s))
                ->filter()
                ->map(fn($key) => $map[$key] ?? null)
                ->filter()->values()->all();
            if (!empty($rels) && method_exists($items, 'load')) {
                $items->load($rels);
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

