<?php

namespace App\Services\Contracts;

interface RoleServiceInterface
{
    /**
     * Mengambil semua roles.
     *
     * @return mixed
     */
    public function getAllRoles();

    /**
     * Mengambil role berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getRoleById($id);

    /**
     * Mengambil role berdasarkan nama.
     *
     * @param string $name
     * @return mixed
     */
    public function getRoleByName($name);

    /**
     * Mengambil role berdasarkan status.
     *
     * @param string $status
     * @return mixed
     */
    public function getRoleByStatus($status);

    /**
     * Mengambil semua roles yang aktif.
     *
     * @return mixed
     */
    public function getActiveRoles();

    /**
     * Mengambil semua roles yang tidak aktif.
     *
     * @return mixed
     */
    public function getInactiveRoles();

    /**
     * Membuat role baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createRole(array $data);

    /**
     * Memperbarui role berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updateRole($id, array $data);

    /**
     * Menghapus role berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deleteRole($id);

    /**
     * Mengupdate status role.
     *
     * @param int $id
     * @param string $status
     * @return mixed
     */
    public function updateRoleStatus($id, $status);
}