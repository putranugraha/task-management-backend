<?php

namespace App\Services\Contracts;

interface DivisionServiceInterface
{
    public function getAllDivisions();
    public function getDivisionById($id);
    public function getDivisionByCode($code);
    public function getDivisionByName($name);
    public function getDivisionByStatus($status);
    public function getActiveDivisions();
    public function getInactiveDivisions();
    public function createDivision(array $data);
    public function updateDivision($id, array $data);
    public function deleteDivision($id);
    public function updateDivisionStatus($id, $status);
    public function countUsersInDivision($divisionId);
}

