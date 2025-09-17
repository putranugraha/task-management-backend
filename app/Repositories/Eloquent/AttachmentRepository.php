<?php

namespace App\Repositories\Eloquent;

use App\Models\Attachment;
use App\Repositories\Contracts\AttachmentRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class AttachmentRepository implements AttachmentRepositoryInterface
{
    protected Attachment $model;

    public function __construct(Attachment $model)
    {
        $this->model = $model;
    }

    public function getAllAttachments()
    {
        return $this->model->latest('uploaded_at')->latest('id')->get();
    }

    public function getAttachmentById($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Attachment with ID {$id} not found.");
            return null;
        }
    }

    public function getAttachmentsByEntity($entityType, $entityId)
    {
        return $this->model
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->get();
    }

    public function getAttachmentsByUser($userId)
    {
        return $this->model
            ->where('uploaded_by', $userId)
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->get();
    }

    public function createAttachment(array $data)
    {
        try {
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error("Failed to create attachment: {$e->getMessage()}");
            return null;
        }
    }

    public function updateAttachment($id, array $data)
    {
        $row = $this->find($id);
        if (!$row) return null;
        try {
            $row->update($data);
            return $row;
        } catch (\Exception $e) {
            Log::error("Failed to update attachment {$id}: {$e->getMessage()}");
            return null;
        }
    }

    public function deleteAttachment($id)
    {
        $row = $this->find($id);
        if (!$row) return false;
        try {
            $row->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete attachment {$id}: {$e->getMessage()}");
            return false;
        }
    }

    public function deleteAttachmentsByEntity($entityType, $entityId)
    {
        try {
            return (bool) $this->model
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->delete();
        } catch (\Exception $e) {
            Log::error("Failed to delete attachments for {$entityType}#{$entityId}: {$e->getMessage()}");
            return false;
        }
    }

    public function getTotalSizeByEntity($entityType, $entityId)
    {
        return (int) $this->model
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->sum('size');
    }

    protected function find($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Attachment with ID {$id} not found.");
            return null;
        }
    }
}

