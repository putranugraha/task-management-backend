<?php

namespace App\Services\Contracts;

interface CommentServiceInterface
{
    public function getAllComments();
    public function getCommentById($id);
    public function getCommentsByEntity($entityType, $entityId);
    public function getCommentsByUser($userId);
    public function createComment(array $data);
    public function updateComment($id, array $data);
    public function deleteComment($id);
    public function deleteCommentsByEntity($entityType, $entityId);
    public function countCommentsByEntity($entityType, $entityId);

    /**
     * Ambil komentar dengan pagination dan filter sederhana.
     *
     * @param array $filters
     * @param int $perPage
     * @return mixed
     */
    public function paginateComments(array $filters = [], int $perPage = 20);
}
