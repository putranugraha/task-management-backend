<?php

namespace App\Repositories\Contracts;

interface ProjectBaselineRepositoryInterface
{
    /**
     * Ambil semua baseline proyek.
     */
    public function getAllBaselines();

    /**
     * Ambil baseline berdasarkan ID.
     */
    public function getBaselineById($id);

    /**
     * Ambil semua baseline dari proyek tertentu.
     */
    public function getBaselinesByProject($projectId);

    /**
     * Ambil baseline proyek berdasarkan nama baseline.
     */
    public function getBaselineByName($projectId, $baselineName);

    /**
     * Ambil baseline terbaru dari proyek tertentu.
     */
    public function getLatestBaselineByProject($projectId);

    /**
     * Membuat baseline baru.
     */
    public function createBaseline(array $data);

    /**
     * Update baseline berdasarkan ID.
     */
    public function updateBaseline($id, array $data);

    /**
     * Hapus baseline berdasarkan ID.
     */
    public function deleteBaseline($id);

    /**
     * Hapus semua baseline dari proyek tertentu.
     */
    public function deleteBaselinesByProject($projectId);
}

