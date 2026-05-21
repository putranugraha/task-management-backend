<?php

namespace App\Services\Contracts;

interface TaskBaselineServiceInterface
{
    public function getAllTaskBaselines();

    public function getTaskBaselineById($id);

    public function getTaskBaselinesByBaseline($baselineId);

    public function getTaskBaselinesByTask($taskId);

    public function createTaskBaseline(array $data);

    public function updateTaskBaseline($id, array $data);

    public function deleteTaskBaseline($id);

    public function deleteTaskBaselinesByBaseline($baselineId);

    public function getTotalWeightByBaseline($baselineId);
}

