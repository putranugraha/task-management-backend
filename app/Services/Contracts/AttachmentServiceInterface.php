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

    /**
     * Ambil attachment dengan pagination dan filter sederhana.
     *
     * @param array $filters
     * @param int $perPage
     * @return mixed
     */
    public function paginateAttachments(array $filters = [], int $perPage = 20);
}
