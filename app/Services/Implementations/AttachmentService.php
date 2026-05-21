<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\AttachmentRepositoryInterface;
use App\Services\Contracts\AttachmentServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\Attachment;

class AttachmentService implements AttachmentServiceInterface
{
    protected AttachmentRepositoryInterface $repository;

    const CACHE_ALL = 'attachments.all';
    const CACHE_ID_PREFIX = 'attachment.'; // + id
    const CACHE_ENTITY_PREFIX = 'attachments.entity.'; // + entityType.entityId
    const CACHE_USER_PREFIX = 'attachments.user.'; // + userId
    const CACHE_TOTAL_PREFIX = 'attachments.total.'; // + entityType.entityId
    const CACHE_DURATION = 900;

    public function __construct(AttachmentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllAttachments()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, fn () => $this->repository->getAllAttachments());
    }

    public function getAttachmentById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, fn () => $this->repository->getAttachmentById($id));
    }

    public function getAttachmentsByEntity($entityType, $entityId)
    {
        $key = self::CACHE_ENTITY_PREFIX.$entityType.'.'.$entityId;
        return Cache::remember($key, self::CACHE_DURATION, fn () => $this->repository->getAttachmentsByEntity($entityType, $entityId));
    }

    public function getAttachmentsByUser($userId)
    {
        return Cache::remember(self::CACHE_USER_PREFIX.$userId, self::CACHE_DURATION, fn () => $this->repository->getAttachmentsByUser($userId));
    }

    public function createAttachment(array $data)
    {
        $row = $this->repository->createAttachment($data);
        $this->clearCaches($row->id ?? null, $row->entity_type ?? null, $row->entity_id ?? null, $row->uploaded_by ?? null);

        if ($row) {
            $actor = Auth::user();

            $properties = [
                'attachment_id' => $row->id,
                'entity_type' => $row->entity_type,
                'entity_id' => $row->entity_id,
                'uploaded_by' => $row->uploaded_by,
                'filename' => $row->filename,
                'mime' => $row->mime,
                'size' => $row->size,
                'status' => $row->status,
            ];

            $activity = activity('attachments')
                ->performedOn($row instanceof Attachment ? $row : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('created');
        }

        return $row;
    }

    public function updateAttachment($id, array $data)
    {
        $before = $this->repository->getAttachmentById($id);
        $row = $this->repository->updateAttachment($id, $data);
        $this->clearCaches($id, $row->entity_type ?? null, $row->entity_id ?? null, $row->uploaded_by ?? null);

        if ($row) {
            $actor = Auth::user();

            $properties = [
                'attachment_id' => $row->id,
                'entity_type' => $row->entity_type,
                'entity_id' => $row->entity_id,
                'uploaded_by' => $row->uploaded_by,
                'filename' => $row->filename,
                'mime' => $row->mime,
                'size' => $row->size,
                'status_before' => $before->status ?? null,
                'status_after' => $row->status,
                'verified_by_before' => $before->verified_by ?? null,
                'verified_by_after' => $row->verified_by,
                'verified_at_before' => $before->verified_at ?? null,
                'verified_at_after' => $row->verified_at,
            ];

            $activity = activity('attachments')
                ->performedOn($row instanceof Attachment ? $row : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('updated');
        }

        return $row;
    }

    public function deleteAttachment($id)
    {
        $row = $this->repository->getAttachmentById($id);
        $result = $this->repository->deleteAttachment($id);
        $this->clearCaches($id, $row->entity_type ?? null, $row->entity_id ?? null, $row->uploaded_by ?? null);

        if ($result && $row) {
            $actor = Auth::user();

            $properties = [
                'attachment_id' => $row->id,
                'entity_type' => $row->entity_type,
                'entity_id' => $row->entity_id,
                'uploaded_by' => $row->uploaded_by,
                'filename' => $row->filename,
                'mime' => $row->mime,
                'size' => $row->size,
                'status' => $row->status,
            ];

            $activity = activity('attachments')
                ->performedOn($row instanceof Attachment ? $row : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('deleted');
        }

        return $result;
    }

    public function deleteAttachmentsByEntity($entityType, $entityId)
    {
        $result = $this->repository->deleteAttachmentsByEntity($entityType, $entityId);
        $this->clearCaches(null, $entityType, $entityId, null);

        if ($result) {
            $actor = Auth::user();

            $activity = activity('attachments')
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

    public function getTotalSizeByEntity($entityType, $entityId)
    {
        $key = self::CACHE_TOTAL_PREFIX.$entityType.'.'.$entityId;
        return Cache::remember($key, self::CACHE_DURATION, fn () => $this->repository->getTotalSizeByEntity($entityType, $entityId));
    }

    public function paginateAttachments(array $filters = [], int $perPage = 20)
    {
        // Pagination tidak dicache untuk menghindari kompleksitas key kombinasi filter + halaman.
        return $this->repository->paginateAttachments($filters, $perPage);
    }

    protected function clearCaches($id = null, $entityType = null, $entityId = null, $userId = null): void
    {
        Cache::forget(self::CACHE_ALL);
        if ($id) Cache::forget(self::CACHE_ID_PREFIX.$id);
        if ($entityType && $entityId) Cache::forget(self::CACHE_ENTITY_PREFIX.$entityType.'.'.$entityId);
        if ($userId) Cache::forget(self::CACHE_USER_PREFIX.$userId);
        if ($entityType && $entityId) Cache::forget(self::CACHE_TOTAL_PREFIX.$entityType.'.'.$entityId);
    }
}
