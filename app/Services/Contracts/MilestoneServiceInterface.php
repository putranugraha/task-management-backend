<?php

namespace App\Services\Contracts;

interface MilestoneServiceInterface
{
    /** Ambil semua milestone. */
    public function getAllMilestones();
    /** Ambil milestone by ID. */
    public function getMilestoneById($id);
    /** Ambil milestones by project. */
    public function getMilestonesByProject($projectId);
    /** Ambil milestones by status. */
    public function getMilestonesByStatus($status);
    /** Ambil milestones by date range. */
    public function getMilestonesByDateRange($startDate, $endDate);
    /** Create milestone. */
    public function createMilestone(array $data);
    /** Update milestone. */
    public function updateMilestone($id, array $data);
    /** Delete milestone. */
    public function deleteMilestone($id);
    /** Update status milestone. */
    public function updateMilestoneStatus($id, $status);
    /** Complete milestone (auto due_actual = today). */
    public function completeMilestone($id);

    /**
     * Ambil milestone dengan pagination dan filter sederhana.
     *
     * @param array $filters
     * @param int $perPage
     * @return mixed
     */
    public function paginateMilestones(array $filters = [], int $perPage = 20);
}
