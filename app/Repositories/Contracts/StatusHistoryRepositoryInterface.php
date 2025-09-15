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
}

