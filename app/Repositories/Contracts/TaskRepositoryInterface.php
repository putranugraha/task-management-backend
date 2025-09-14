<?php

namespace App\Repositories\Contracts;

interface TaskRepositoryInterface
{
    /** Ambil semua task. */
    public function getAllTasks();

    /** Ambil task berdasarkan ID. */
    public function getTaskById($id);

    /** Ambil semua task berdasarkan ID proyek. */
    public function getTasksByProject($projectId);

    /** Ambil task berdasarkan status (To Do, In Progress, Done). */
    public function getTasksByStatus($status);

    /** Ambil task berdasarkan prioritas. */
    public function getTasksByPriority($priority);

    /** Ambil task yang jatuh tempo dalam rentang tanggal planned. */
    public function getTasksByPlannedDateRange($startDate, $endDate);

    /** Ambil task yang selesai dalam rentang tanggal actual. */
    public function getTasksByActualDateRange($startDate, $endDate);

    /** Membuat task baru. */
    public function createTask(array $data);

    /** Update task berdasarkan ID. */
    public function updateTask($id, array $data);

    /** Hapus task berdasarkan ID. */
    public function deleteTask($id);

    /** Update status task (misalnya To Do → In Progress → Done). */
    public function updateTaskStatus($id, $status);

    /** Update persentase progres task. */
    public function updateTaskProgress($id, $percent);

    /** Tandai task selesai (set status = Done, end_actual = now, percent_complete = 100). */
    public function completeTask($id);
}
