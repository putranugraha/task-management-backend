<?php

namespace App\Repositories\Contracts;

interface TaskDependencyRepositoryInterface
{
    /**
     * Ambil semua dependency.
     *
     * @return mixed
     */
    public function getAllDependencies();

    /**
     * Ambil dependency berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getDependencyById($id);

    /**
     * Ambil semua dependency berdasarkan task_id.
     *
     * @param int $taskId
     * @return mixed
     */
    public function getDependenciesByTask($taskId);

    /**
     * Ambil semua dependency di mana task lain tergantung pada task_id tertentu.
     *
     * @param int $dependsOnTaskId
     * @return mixed
     */
    public function getDependentsByTask($dependsOnTaskId);

    /**
     * Membuat dependency baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createDependency(array $data);

    /**
     * Update dependency berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updateDependency($id, array $data);

    /**
     * Hapus dependency berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deleteDependency($id);

    /**
     * Hapus semua dependency dari sebuah task.
     *
     * @param int $taskId
     * @return mixed
     */
    public function deleteDependenciesByTask($taskId);
}

