<?php

namespace App\Services\Contracts;

interface ProjectBaselineServiceInterface
{
    public function getAllBaselines();

    public function getBaselineById($id);

    public function getBaselinesByProject($projectId);

    public function getBaselineByName($projectId, $baselineName);

    public function getLatestBaselineByProject($projectId);

    public function createBaseline(array $data);

    public function updateBaseline($id, array $data);

    public function deleteBaseline($id);

    public function deleteBaselinesByProject($projectId);
}

