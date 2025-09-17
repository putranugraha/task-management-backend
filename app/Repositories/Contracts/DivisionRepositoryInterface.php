<?php

namespace App\Repositories\Contracts;

interface DivisionRepositoryInterface
{
    /**
     * Ambil semua divisi.
     *
     * @return mixed
     */
    public function getAllDivisions();

    /**
     * Ambil divisi berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getDivisionById($id);

    /**
     * Ambil divisi berdasarkan kode unik.
     *
     * @param string $code
     * @return mixed
     */
    public function getDivisionByCode($code);

    /**
     * Ambil divisi berdasarkan nama.
     *
     * @param string $name
     * @return mixed
     */
    public function getDivisionByName($name);

    /**
     * Membuat divisi baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createDivision(array $data);

    /**
     * Update divisi berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updateDivision($id, array $data);

    /**
     * Hapus divisi berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deleteDivision($id);

    /**
     * Hitung jumlah user dalam divisi tertentu.
     *
     * @param int $divisionId
     * @return int
     */
    public function countUsersInDivision($divisionId);
}

