<?php

namespace App\Repositories\Contracts;

interface ProjectRepositoryInterface
{
    /**
     * Ambil semua proyek.
     *
     * @return mixed
     */
    public function getAllProjects();

    /**
     * Ambil proyek berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getProjectById($id);

    /**
     * Ambil proyek berdasarkan nama.
     *
     * @param string $name
     * @return mixed
     */
    public function getProjectByName($name);

    /**
     * Ambil proyek berdasarkan nama klien.
     *
     * @param string $clientName
     * @return mixed
     */
    public function getProjectByClient($clientName);

    /**
     * Ambil proyek berdasarkan divisi yang bertanggung jawab.
     *
     * @param int $divisionId
     * @return mixed
     */
    public function getProjectsByDivision($divisionId);

    /**
     * Ambil proyek berdasarkan status (Planned, In Progress, Completed, ...).
     *
     * @param string $status
     * @return mixed
     */
    public function getProjectsByStatus($status);

    /**
     * Ambil proyek berdasarkan rentang tanggal planned.
     *
     * @param string $startDate Format: Y-m-d
     * @param string $endDate   Format: Y-m-d
     * @return mixed
     */
    public function getProjectsByDateRange($startDate, $endDate);

    /**
     * Membuat proyek baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createProject(array $data);

    /**
     * Update proyek berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updateProject($id, array $data);

    /**
     * Hapus proyek berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deleteProject($id);
    public function getArchivedProjects(array $filters = [], int $perPage = 20);
    public function restoreProject($id);
    public function forceDeleteArchivedProject($id): bool;

    /**
     * Update status proyek.
     *
     * @param int $id
     * @param string $status
     * @return mixed
     */
    public function updateProjectStatus($id, $status);

    /**
     * Ambil proyek dengan filter sederhana dan pagination.
     *
     * $filters dapat berisi:
     * - status
     * - division_owner_id
     * - client_name (exact)
     */
    public function paginateProjects(array $filters = [], int $perPage = 20);

    /**
     * Hitung statistik proyek berdasarkan filter sederhana.
     *
     * Mengembalikan array dengan kunci:
     * - total: int
     * - by_status: array<string,int> (status => jumlah)
     *
     * @param array $filters
     * @return array{total:int,by_status:array<string,int>}
     */
    public function getProjectStatusCounts(array $filters = []): array;
}
