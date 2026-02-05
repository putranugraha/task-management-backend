<?php

namespace App\Services\Contracts;

interface KpiSnapshotServiceInterface
{
    public function getAllKpiSnapshots();
    public function getKpiSnapshotById($id);
    public function getKpiSnapshotsByProject($projectId);
    public function getKpiSnapshotsByPeriod($periodId);
    public function getKpiSnapshotByProjectAndPeriod($projectId, $periodId);
    public function createKpiSnapshot(array $data);
    public function updateKpiSnapshot($id, array $data);
    public function deleteKpiSnapshot($id);
    public function deleteKpiSnapshotsByProject($projectId);
    public function getAverageCycleTimeByProject($projectId);
    public function generateForProjectAndDate($projectId, $periodDate, ?string $note = null);
}
