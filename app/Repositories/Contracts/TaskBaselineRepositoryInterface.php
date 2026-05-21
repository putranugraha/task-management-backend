<?php

namespace App\Repositories\Contracts;

interface TaskBaselineRepositoryInterface
{
    /** Ambil semua baseline task. */
    public function getAllTaskBaselines();

    /** Ambil baseline task berdasarkan ID. */
    public function getTaskBaselineById($id);

    /** Ambil semua baseline task berdasarkan baseline proyek. */
    public function getTaskBaselinesByBaseline($baselineId);

    /** Ambil baseline task berdasarkan task tertentu. */
    public function getTaskBaselinesByTask($taskId);

    /** Membuat baseline task baru. */
    public function createTaskBaseline(array $data);

    /** Update baseline task berdasarkan ID. */
    public function updateTaskBaseline($id, array $data);

    /** Hapus baseline task berdasarkan ID. */
    public function deleteTaskBaseline($id);

    /** Hapus semua baseline task dari baseline proyek tertentu. */
    public function deleteTaskBaselinesByBaseline($baselineId);

    /** Hitung total bobot task untuk baseline tertentu. */
    public function getTotalWeightByBaseline($baselineId);
}

