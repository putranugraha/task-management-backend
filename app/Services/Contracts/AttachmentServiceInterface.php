<?php

namespace App\Services\Contracts;

interface AttachmentServiceInterface
{
    public function getAllAttachments();
    public function getAttachmentById($id);
    public function getAttachmentsByEntity($entityType, $entityId);
    public function getAttachmentsByUser($userId);
    public function createAttachment(array $data);
    public function updateAttachment($id, array $data);
    public function deleteAttachment($id);
    public function deleteAttachmentsByEntity($entityType, $entityId);
    public function getTotalSizeByEntity($entityType, $entityId);
}

