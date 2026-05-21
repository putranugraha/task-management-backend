<?php

namespace App\Repositories\Eloquent;

use App\Models\Comment;
use App\Repositories\Contracts\CommentRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class CommentRepository implements CommentRepositoryInterface
{
    protected Comment $model;

    public function __construct(Comment $model)
    {
        $this->model = $model;
    }

    public function getAllComments()
    {
        return $this->model->latest('id')->get();
    }

    public function getCommentById($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Comment with ID {$id} not found.");
            return null;
        }
    }

    public function getCommentsByEntity($entityType, $entityId)
    {
        return $this->model
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->latest('id')
            ->get();
    }

    public function getCommentsByUser($userId)
    {
        return $this->model
            ->where('user_id', $userId)
            ->latest('id')
            ->get();
    }

    public function createComment(array $data)
    {
        try {
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error("Failed to create comment: {$e->getMessage()}");
            return null;
        }
    }

    public function updateComment($id, array $data)
    {
        $row = $this->find($id);
        if (!$row) return null;
        try {
            $row->update($data);
            return $row;
        } catch (\Exception $e) {
            Log::error("Failed to update comment {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteComment($id)
    {
        $row = $this->find($id);
        if (!$row) return false;
        try {
            $row->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete comment {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function deleteCommentsByEntity($entityType, $entityId)
    {
        try {
            return (bool) $this->model
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->delete();
        } catch (\Exception $e) {
            Log::error("Failed to delete comments for {$entityType}#{$entityId}: {$e->getMessage()}");
            return false;
        }
    }

    public function countCommentsByEntity($entityType, $entityId)
    {
        return (int) $this->model
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->count();
    }

    public function paginateComments(array $filters = [], int $perPage = 20)
    {
        $query = $this->model->latest('id');

        if (isset($filters['entity_type']) && isset($filters['entity_id'])) {
            $query->where('entity_type', $filters['entity_type'])
                ->where('entity_id', $filters['entity_id']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->paginate($perPage);
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Comment with ID {$id} not found.");
            return null;
        }
    }
}
