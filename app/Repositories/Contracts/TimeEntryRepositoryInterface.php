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
}

