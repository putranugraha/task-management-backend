<?php

namespace App\Repositories\Contracts;

interface MilestoneRepositoryInterface
{
    /**
     * Ambil semua milestone.
     *
     * @return mixed
     */
    public function getAllMilestones();

    /**
     * Ambil milestone berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getMilestoneById($id);

    /**
     * Ambil semua milestone berdasarkan ID proyek.
     *
     * @param int $projectId
     * @return mixed
     */
    public function getMilestonesByProject($projectId);

    /**
     * Ambil milestone berdasarkan status (Planned, Completed, Overdue, ...).
     *
     * @param string $status
     * @return mixed
     */
    public function getMilestonesByStatus($status);

    /**
     * Ambil milestone yang jatuh tempo di rentang tanggal tertentu.
     *
     * @param string $startDate Format: Y-m-d
     * @param string $endDate   Format: Y-m-d
     * @return mixed
     */
    public function getMilestonesByDateRange($startDate, $endDate);

    /**
     * Membuat milestone baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createMilestone(array $data);

    /**
     * Update milestone berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updateMilestone($id, array $data);

    /**
     * Hapus milestone berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deleteMilestone($id);
    public function getArchivedMilestones(array $filters = [], int $perPage = 20);
    public function restoreMilestone($id);

    /**
     * Update status milestone.
     *
     * @param int $id
     * @param string $status
     * @return mixed
     */
    public function updateMilestoneStatus($id, $status);

    /**
     * Tandai milestone selesai (isi due_actual otomatis dengan tanggal sekarang).
     *
     * @param int $id
     * @return mixed
     */
    public function completeMilestone($id);

    /**
     * Ambil milestone dengan filter sederhana dan pagination.
     *
     * $filters dapat berisi:
     * - project_id
     * - status
     */
    public function paginateMilestones(array $filters = [], int $perPage = 20);

    /**
     * Hitung jumlah milestone total dan per status berdasarkan filter sederhana.
     *
     * Mengembalikan array dengan kunci:
     * - total: int
     * - by_status: array<string,int> (status => jumlah)
     *
     * @param array $filters
     * @return array{total:int,by_status:array<string,int>}
     */
    public function getMilestoneStatusCounts(array $filters = []): array;
}
