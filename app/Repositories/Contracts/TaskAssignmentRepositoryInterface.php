<?php

namespace App\Repositories\Contracts;

interface TaskAssignmentRepositoryInterface
{
    /**
     * Ambil semua assignment.
     *
     * @return mixed
     */
    public function getAllAssignments();

    /**
     * Ambil assignment berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getAssignmentById($id);

    /**
     * Ambil semua assignment berdasarkan task.
     *
     * @param int $taskId
     * @return mixed
     */
    public function getAssignmentsByTask($taskId);

    /**
     * Ambil semua assignment berdasarkan user.
     *
     * @param int $userId
     * @return mixed
     */
    public function getAssignmentsByUser($userId);

    /**
     * Ambil assignment berdasarkan kombinasi task dan user.
     *
     * @param int $taskId
     * @param int $userId
     * @return mixed
     */
    public function getAssignmentByTaskAndUser($taskId, $userId);

    /**
     * Membuat assignment baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createAssignment(array $data);

    /**
     * Update assignment berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updateAssignment($id, array $data);

    /**
     * Hapus assignment berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deleteAssignment($id);

    /**
     * Hapus semua assignment pada sebuah task.
     *
     * @param int $taskId
     * @return mixed
     */
    public function deleteAssignmentsByTask($taskId);

    /**
     * Hapus semua assignment dari user tertentu.
     *
     * @param int $userId
     * @return mixed
     */
    public function deleteAssignmentsByUser($userId);
}
