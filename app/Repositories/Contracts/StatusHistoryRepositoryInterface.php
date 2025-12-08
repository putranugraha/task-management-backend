<?php

namespace App\Repositories\Contracts;

interface StatusHistoryRepositoryInterface
{
    /** Ambil semua histori status. */
    public function getAllHistories();

    /** Ambil histori berdasarkan ID. */
    public function getHistoryById($id);

    /** Ambil semua histori berdasarkan actor (user yang melakukan aksi). */
    public function getHistoriesByActor($actorId);

    /** Ambil semua histori berdasarkan jenis entity. */
    public function getHistoriesByEntityType($entityType);

    /** Ambil semua histori berdasarkan entity tertentu. */
    public function getHistoriesByEntity($entityType, $entityId);

    /** Membuat histori baru. */
    public function createHistory(array $data);

    /** Hapus histori berdasarkan ID. */
    public function deleteHistory($id);

    /** Hapus semua histori untuk entity tertentu. */
    public function deleteHistoriesByEntity($entityType, $entityId);

    /** Ambil histori dalam rentang tanggal tertentu (berdasarkan created_at). */
    public function getHistoriesByDateRange($startDate, $endDate);

    /**
     * Ambil histori status dengan filter sederhana dan pagination.
     *
     * $filters dapat berisi:
     * - actor_id (changed_by)
     * - entity_type (saat ini hanya Task)
     * - entity_id (task_id ketika entity_type=Task)
     */
    public function paginateHistories(array $filters = [], int $perPage = 20);
}
