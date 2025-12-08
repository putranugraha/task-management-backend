<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Services\Contracts\CommentServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\Comment;

class CommentService implements CommentServiceInterface
{
    protected CommentRepositoryInterface $repository;

    const CACHE_ALL = 'comments.all';
    const CACHE_ID_PREFIX = 'comment.'; // + id
    const CACHE_ENTITY_PREFIX = 'comments.entity.'; // + entityType.entityId
    const CACHE_USER_PREFIX = 'comments.user.'; // + userId
    const CACHE_COUNT_PREFIX = 'comments.count.'; // + entityType.entityId
    const CACHE_DURATION = 900;

    public function __construct(CommentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllComments()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, fn () => $this->repository->getAllComments());
    }

    public function getCommentById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, fn () => $this->repository->getCommentById($id));
    }

    public function getCommentsByEntity($entityType, $entityId)
    {
        $key = self::CACHE_ENTITY_PREFIX.$entityType.'.'.$entityId;
        return Cache::remember($key, self::CACHE_DURATION, fn () => $this->repository->getCommentsByEntity($entityType, $entityId));
    }

    public function getCommentsByUser($userId)
    {
        return Cache::remember(self::CACHE_USER_PREFIX.$userId, self::CACHE_DURATION, fn () => $this->repository->getCommentsByUser($userId));
    }

    public function createComment(array $data)
    {
        $row = $this->repository->createComment($data);
        $this->clearCaches($row->id ?? null, $row->entity_type ?? null, $row->entity_id ?? null, $row->user_id ?? null);

        if ($row) {
            $actor = Auth::user();

            $properties = [
                'comment_id' => $row->id,
                'entity_type' => $row->entity_type,
                'entity_id' => $row->entity_id,
                'user_id' => $row->user_id,
                'content' => $row->content,
            ];

            $activity = activity('comments')
                ->performedOn($row instanceof Comment ? $row : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('created');
        }

        return $row;
    }

    public function updateComment($id, array $data)
    {
        $before = $this->repository->getCommentById($id);
        $row = $this->repository->updateComment($id, $data);
        $this->clearCaches($id, $row->entity_type ?? null, $row->entity_id ?? null, $row->user_id ?? null);

        if ($row) {
            $actor = Auth::user();

            $properties = [
                'comment_id' => $row->id,
                'entity_type' => $row->entity_type,
                'entity_id' => $row->entity_id,
                'user_id' => $row->user_id,
                'content_before' => $before->content ?? null,
                'content_after' => $row->content,
            ];

            $activity = activity('comments')
                ->performedOn($row instanceof Comment ? $row : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('updated');
        }

        return $row;
    }

    public function deleteComment($id)
    {
        $row = $this->repository->getCommentById($id);
        $result = $this->repository->deleteComment($id);
        $this->clearCaches($id, $row->entity_type ?? null, $row->entity_id ?? null, $row->user_id ?? null);

        if ($result && $row) {
            $actor = Auth::user();

            $properties = [
                'comment_id' => $row->id,
                'entity_type' => $row->entity_type,
                'entity_id' => $row->entity_id,
                'user_id' => $row->user_id,
                'content' => $row->content,
            ];

            $activity = activity('comments')
                ->performedOn($row instanceof Comment ? $row : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('deleted');
        }

        return $result;
    }

    public function deleteCommentsByEntity($entityType, $entityId)
    {
        $result = $this->repository->deleteCommentsByEntity($entityType, $entityId);
        $this->clearCaches(null, $entityType, $entityId, null);

        if ($result) {
            $actor = Auth::user();

            $activity = activity('comments')
                ->withProperties([
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'action' => 'delete_by_entity',
                ]);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('bulk_deleted');
        }

        return $result;
    }

    public function countCommentsByEntity($entityType, $entityId)
    {
        $key = self::CACHE_COUNT_PREFIX.$entityType.'.'.$entityId;
        return Cache::remember($key, self::CACHE_DURATION, fn () => $this->repository->countCommentsByEntity($entityType, $entityId));
    }

    public function paginateComments(array $filters = [], int $perPage = 20)
    {
        // Pagination tidak dicache untuk menghindari kompleksitas key per kombinasi filter + halaman.
        return $this->repository->paginateComments($filters, $perPage);
    }

    protected function clearCaches($id = null, $entityType = null, $entityId = null, $userId = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id) Cache::forget(self::CACHE_ID_PREFIX.$id);
        if ($entityType && $entityId) Cache::forget(self::CACHE_ENTITY_PREFIX.$entityType.'.'.$entityId);
        if ($userId) Cache::forget(self::CACHE_USER_PREFIX.$userId);
        if ($entityType && $entityId) Cache::forget(self::CACHE_COUNT_PREFIX.$entityType.'.'.$entityId);
    }
}
