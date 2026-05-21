<?php

namespace App\Services\Contracts;

interface StatusHistoryServiceInterface
{
    public function getAllHistories();
    public function getHistoryById($id);
    public function getHistoriesByActor($actorId);
    public function getHistoriesByEntityType($entityType);
    public function getHistoriesByEntity($entityType, $entityId);
    public function createHistory(array $data);
    public function deleteHistory($id);
    public function deleteHistoriesByEntity($entityType, $entityId);
    public function getHistoriesByDateRange($startDate, $endDate);

    /**
     * Ambil histori status dengan pagination dan filter sederhana.
     *
     * @param array $filters
     * @param int $perPage
     * @return mixed
     */
    public function paginateHistories(array $filters = [], int $perPage = 20);
}
