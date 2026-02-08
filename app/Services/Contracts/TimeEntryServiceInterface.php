<?php

namespace App\Services\Contracts;

interface TimeEntryServiceInterface
{
    public function getAllTimeEntries();
    public function getTimeEntryById($id);
    public function getTimeEntriesByTask($taskId);
    public function getTimeEntriesByUser($userId);
    public function getTimeEntriesByTaskAndUser($taskId, $userId);
    public function getTimeEntriesByDateRange($startDate, $endDate);
    public function createTimeEntry(array $data);
    public function updateTimeEntry($id, array $data);
    public function deleteTimeEntry($id);
    public function getTotalHoursByTask($taskId);
    public function getTotalHoursByUser($userId);
    public function getTotalHoursByProjectAsOf(int $projectId, string $asOfDate);
    public function getTopTasksByHoursAsOf(int $projectId, string $asOfDate, int $limit = 5);

    /**
     * Ambil time entry dengan pagination dan filter sederhana.
     *
     * @param array $filters
     * @param int $perPage
     * @return mixed
     */
    public function paginateTimeEntries(array $filters = [], int $perPage = 20);
}
