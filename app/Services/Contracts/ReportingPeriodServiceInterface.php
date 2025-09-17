<?php

namespace App\Services\Contracts;

interface ReportingPeriodServiceInterface
{
    public function getAllReportingPeriods();

    public function getReportingPeriodById($id);

    public function getReportingPeriodsByProject($projectId);

    public function getReportingPeriodByDate($projectId, $date);

    public function getReportingPeriodsByDateRange($projectId, $startDate, $endDate);

    public function createReportingPeriod(array $data);

    public function updateReportingPeriod($id, array $data);

    public function deleteReportingPeriod($id);

    public function deleteReportingPeriodsByProject($projectId);
}

