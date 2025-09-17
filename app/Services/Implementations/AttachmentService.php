<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\AttachmentRepositoryInterface;
use App\Services\Contracts\AttachmentServiceInterface;
use Illuminate\Support\Facades\Cache;

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
        return $row;
    }

    public function updateAttachment($id, array $data)
    {
        $row = $this->repository->updateAttachment($id, $data);
        $this->clearCaches($id, $row->entity_type ?? null, $row->entity_id ?? null, $row->uploaded_by ?? null);
        return $row;
    }

    public function deleteAttachment($id)
    {
        $row = $this->repository->getAttachmentById($id);
        $result = $this->repository->deleteAttachment($id);
        $this->clearCaches($id, $row->entity_type ?? null, $row->entity_id ?? null, $row->uploaded_by ?? null);
        return $result;
    }

    public function deleteAttachmentsByEntity($entityType, $entityId)
    {
        $result = $this->repository->deleteAttachmentsByEntity($entityType, $entityId);
        $this->clearCaches(null, $entityType, $entityId, null);
        return $result;
    }

    public function getTotalSizeByEntity($entityType, $entityId)
    {
        $key = self::CACHE_TOTAL_PREFIX.$entityType.'.'.$entityId;
        return Cache::remember($key, self::CACHE_DURATION, fn () => $this->repository->getTotalSizeByEntity($entityType, $entityId));
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

