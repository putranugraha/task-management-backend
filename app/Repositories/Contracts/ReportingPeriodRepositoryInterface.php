<?php

namespace App\Repositories\Contracts;

interface ReportingPeriodRepositoryInterface
{
    /** Ambil semua reporting period. */
    public function getAllReportingPeriods();

    /** Ambil reporting period berdasarkan ID. */
    public function getReportingPeriodById($id);

    /** Ambil semua reporting period dari proyek tertentu. */
    public function getReportingPeriodsByProject($projectId);

    /** Ambil reporting period berdasarkan tanggal spesifik. */
    public function getReportingPeriodByDate($projectId, $date);

    /** Ambil reporting period dalam rentang tanggal tertentu. */
    public function getReportingPeriodsByDateRange($projectId, $startDate, $endDate);

    /** Membuat reporting period baru. */
    public function createReportingPeriod(array $data);

    /** Update reporting period berdasarkan ID. */
    public function updateReportingPeriod($id, array $data);

    /** Hapus reporting period berdasarkan ID. */
    public function deleteReportingPeriod($id);

    /** Hapus semua reporting period dari proyek tertentu. */
    public function deleteReportingPeriodsByProject($projectId);
}

