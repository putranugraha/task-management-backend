<?php

namespace App\Services\Contracts;

interface TaskServiceInterface
{
    public function getAllTasks();
    public function getTaskById($id);
    public function getTasksByProject($projectId);
    public function getTasksByStatus($status);
    public function getTasksByPriority($priority);
    public function getTasksByPlannedDateRange($startDate, $endDate);
    public function getTasksByActualDateRange($startDate, $endDate);
    public function getTasksByDependsOnTask($dependsOnTaskId);
    public function createTask(array $data);
    public function updateTask($id, array $data);
    public function deleteTask($id);
    public function updateTaskStatus($id, $status);
    public function updateTaskProgress($id, $percent);
    public function completeTask($id);
}
