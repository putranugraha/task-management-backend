<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CommentStoreRequest;
use App\Http\Requests\CommentUpdateRequest;
use App\Http\Resources\CommentResource;
use App\Services\Contracts\CommentServiceInterface;

class CommentController extends Controller
{
    protected CommentServiceInterface $service;

    public function __construct(CommentServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        // Support route-based entity aliases
        $routeTask = $request->route('task');
        $entityMap = ['tasks' => 'Task', 'projects' => 'Project', 'milestones' => 'Milestone'];
        $entityType = $request->query('entity_type');
        $entityId = $request->query('entity_id');
        $userId = $request->query('user_id');
        $include = $request->query('include'); // entity,user

        // Resolve route aliases to entity_type/entity_id
        foreach ($entityMap as $segment => $type) {
            if ($request->is("api/{$segment}/*/comments")) {
                $entityType = $type;
                $entityId = $request->route(substr($segment, 0, -1)); // task|project|milestone
                break;
            }
        }

        if ($entityType && $entityId) {
            $items = $this->service->getCommentsByEntity($entityType, $entityId);
        } elseif ($userId) {
            $items = $this->service->getCommentsByUser($userId);
        } else {
            $items = $this->service->getAllComments();
        }

        if ($include) {
            $map = ['entity' => 'entity', 'user' => 'user'];
            $rels = collect(explode(',', $include))
                ->map(fn($s) => trim($s))
                ->filter()
                ->map(fn($key) => $map[$key] ?? null)
                ->filter()->values()->all();
            if (!empty($rels) && method_exists($items, 'load')) {
                // For morphTo, load('entity') is fine
                $items->load($rels);
            }
        }

        return CommentResource::collection($items);
    }

    public function store(CommentStoreRequest $request)
    {
        $row = $this->service->createComment($request->validated());
        if (!$row) return response()->json(['message' => 'Gagal membuat komentar'], 400);
        return new CommentResource($row);
    }

    public function show(string $id)
    {
        $row = $this->service->getCommentById($id);
        if (!$row) return response()->json(['message' => 'Komentar tidak ditemukan'], 404);
        $include = request()->query('include');
        if ($include) {
            $map = ['entity' => 'entity', 'user' => 'user'];
            $rels = collect(explode(',', $include))->map(fn($s) => trim($s))->filter()->map(fn($key) => $map[$key] ?? null)->filter()->values()->all();
            if (!empty($rels)) $row->load($rels);
        }
        return new CommentResource($row);
    }

    public function update(CommentUpdateRequest $request, string $id)
    {
        $row = $this->service->updateComment($id, $request->validated());
        if (!$row) return response()->json(['message' => 'Komentar tidak ditemukan atau invalid'], 404);
        return new CommentResource($row);
    }

    public function destroy(string $id)
    {
        $deleted = $this->service->deleteComment($id);
        if (!$deleted) return response()->json(['message' => 'Komentar tidak ditemukan'], 404);
        return response()->json(['message' => 'Komentar berhasil dihapus']);
    }

    public function destroyByEntity(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:Task,Project,Milestone',
            'entity_id' => 'required|integer',
        ]);
        $deleted = $this->service->deleteCommentsByEntity($request->input('entity_type'), (int) $request->input('entity_id'));
        if (!$deleted) return response()->json(['message' => 'Tidak ada komentar dihapus atau entity tidak ditemukan'], 404);
        return response()->json(['message' => 'Seluruh komentar untuk entity dihapus']);
    }

    public function countByEntity(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:Task,Project,Milestone',
            'entity_id' => 'required|integer',
        ]);
        $count = $this->service->countCommentsByEntity($request->input('entity_type'), (int) $request->input('entity_id'));
        return response()->json([
            'entity_type' => $request->input('entity_type'),
            'entity_id' => (int) $request->input('entity_id'),
            'count' => $count,
        ]);
    }
}
