<?php

namespace App\Repositories\Contracts;

interface AttachmentRepositoryInterface
{
    /** Ambil semua attachment. */
    public function getAllAttachments();

    /** Ambil attachment berdasarkan ID. */
    public function getAttachmentById($id);

    /** Ambil semua attachment berdasarkan entity. */
    public function getAttachmentsByEntity($entityType, $entityId);

    /** Ambil semua attachment yang diupload oleh user tertentu. */
    public function getAttachmentsByUser($userId);

    /** Membuat attachment baru. */
    public function createAttachment(array $data);

    /** Update attachment berdasarkan ID. */
    public function updateAttachment($id, array $data);

    /** Hapus attachment berdasarkan ID. */
    public function deleteAttachment($id);

    /** Hapus semua attachment dari entity tertentu. */
    public function deleteAttachmentsByEntity($entityType, $entityId);

    /** Hitung total ukuran file (size) untuk entity tertentu. */
    public function getTotalSizeByEntity($entityType, $entityId);

    /**
     * Ambil attachment dengan filter sederhana dan pagination.
     *
     * $filters dapat berisi:
     * - entity_type
     * - entity_id
     * - uploaded_by
     */
    public function paginateAttachments(array $filters = [], int $perPage = 20);
}
