<?php

namespace App\Repositories\Contracts;

interface TimeEntryRepositoryInterface
{
    /** Ambil semua time entry. */
    public function getAllTimeEntries();

    /** Ambil time entry berdasarkan ID. */
    public function getTimeEntryById($id);

    /** Ambil semua time entry berdasarkan task. */
    public function getTimeEntriesByTask($taskId);

    /** Ambil semua time entry berdasarkan user. */
    public function getTimeEntriesByUser($userId);

    /** Ambil time entry berdasarkan task dan user. */
    public function getTimeEntriesByTaskAndUser($taskId, $userId);

    /** Ambil semua time entry dalam rentang tanggal. */
    public function getTimeEntriesByDateRange($startDate, $endDate);

    /** Membuat time entry baru. */
    public function createTimeEntry(array $data);

    /** Update time entry berdasarkan ID. */
    public function updateTimeEntry($id, array $data);

    /** Hapus time entry berdasarkan ID. */
    public function deleteTimeEntry($id);

    /** Hitung total jam kerja pada sebuah task. */
    public function getTotalHoursByTask($taskId);

    /** Hitung total jam kerja dari seorang user. */
    public function getTotalHoursByUser($userId);

    /**
     * Hitung total jam kerja pada sebuah project sampai tanggal tertentu (as-of).
     *
     * @param int $projectId
     * @param string $asOfDate Y-m-d
     * @return float
     */
    public function getTotalHoursByProjectAsOf(int $projectId, string $asOfDate): float;

    /**
     * Ambil daftar task dengan total jam terbesar pada sebuah project sampai tanggal tertentu (as-of).
     *
     * @param int $projectId
     * @param string $asOfDate Y-m-d
     * @param int $limit
     * @return array<int, array{task_id:int,task_title:string|null,total_hours:float}>
     */
    public function getTopTasksByHoursAsOf(int $projectId, string $asOfDate, int $limit = 5): array;

    /**
     * Ambil time entry dengan filter sederhana dan pagination.
     *
     * $filters dapat berisi:
     * - task_id
     * - user_id
     * - start_date
     * - end_date
     */
    public function paginateTimeEntries(array $filters = [], int $perPage = 20);
}
