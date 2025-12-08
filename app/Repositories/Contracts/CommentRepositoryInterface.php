<?php

namespace App\Repositories\Contracts;

interface CommentRepositoryInterface
{
    /** Ambil semua komentar. */
    public function getAllComments();

    /** Ambil komentar berdasarkan ID. */
    public function getCommentById($id);

    /** Ambil semua komentar berdasarkan entity. */
    public function getCommentsByEntity($entityType, $entityId);

    /** Ambil semua komentar dari user tertentu. */
    public function getCommentsByUser($userId);

    /** Membuat komentar baru. */
    public function createComment(array $data);

    /** Update komentar berdasarkan ID. */
    public function updateComment($id, array $data);

    /** Hapus komentar berdasarkan ID. */
    public function deleteComment($id);

    /** Hapus semua komentar dari entity tertentu. */
    public function deleteCommentsByEntity($entityType, $entityId);

    /** Hitung jumlah komentar pada entity tertentu. */
    public function countCommentsByEntity($entityType, $entityId);

    /**
     * Ambil komentar dengan filter sederhana dan pagination.
     *
     * $filters dapat berisi:
     * - entity_type
     * - entity_id
     * - user_id
     */
    public function paginateComments(array $filters = [], int $perPage = 20);
}
