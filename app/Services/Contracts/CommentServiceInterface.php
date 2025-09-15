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
}

