<?php

namespace App\Services\Contracts;

interface TaskDependencyServiceInterface
{
    public function getAllDependencies();
    public function getDependencyById($id);
    public function getDependenciesByTask($taskId);
    public function getDependentsByTask($dependsOnTaskId);
    public function createDependency(array $data);
    public function updateDependency($id, array $data);
    public function deleteDependency($id);
    public function deleteDependenciesByTask($taskId);
}

