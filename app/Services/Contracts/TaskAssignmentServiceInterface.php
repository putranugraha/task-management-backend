<?php

namespace App\Services\Contracts;

interface TaskAssignmentServiceInterface
{
    public function getAllAssignments();
    public function getAssignmentById($id);
    public function getAssignmentsByTask($taskId);
    public function getAssignmentsByUser($userId);
    public function getAssignmentByTaskAndUser($taskId, $userId);
    public function createAssignment(array $data);
    public function updateAssignment($id, array $data);
    public function deleteAssignment($id);
    public function deleteAssignmentsByTask($taskId);
    public function deleteAssignmentsByUser($userId);
}

