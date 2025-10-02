<?php

namespace App\Repositories\Contracts;

interface KpiSnapshotRepositoryInterface
{
    /**
     * Ambil semua snapshot KPI.
     *
     * @return mixed
     */
    public function getAllKpiSnapshots();

    /**
     * Ambil snapshot KPI berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getKpiSnapshotById($id);

    /**
     * Ambil semua snapshot KPI untuk proyek tertentu.
     *
     * @param int $projectId
     * @return mixed
     */
    public function getKpiSnapshotsByProject($projectId);

    /**
     * Ambil semua snapshot KPI untuk periode tertentu.
     *
     * @param int $periodId
     * @return mixed
     */
    public function getKpiSnapshotsByPeriod($periodId);

    /**
     * Ambil snapshot KPI untuk proyek & periode tertentu.
     *
     * @param int $projectId
     * @param int $periodId
     * @return mixed
     */
    public function getKpiSnapshotByProjectAndPeriod($projectId, $periodId);

    /**
     * Membuat snapshot KPI baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createKpiSnapshot(array $data);

    /**
     * Update snapshot KPI berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updateKpiSnapshot($id, array $data);

    /**
     * Hapus snapshot KPI berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deleteKpiSnapshot($id);

    /**
     * Hapus semua snapshot KPI dari proyek tertentu.
     *
     * @param int $projectId
     * @return mixed
     */
    public function deleteKpiSnapshotsByProject($projectId);

    /**
     * Hitung rata-rata cycle time untuk proyek tertentu.
     *
     * @param int $projectId
     * @return mixed
     */
    public function getAverageCycleTimeByProject($projectId);
}

