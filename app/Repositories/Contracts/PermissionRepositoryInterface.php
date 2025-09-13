<?php

namespace App\Repositories\Contracts;

interface PermissionRepositoryInterface
{
    /**
     * Mengambil semua permissions.
     *
     * @return mixed
     */
    public function getAllPermissions();

    /**
     * Mengambil permission berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getPermissionById($id);

    /**
     * Mengambil permission berdasarkan nama.
     *
     * @param string $name
     * @return mixed
     */
    public function getPermissionByName($name);

    /**
     * Mengambil permission berdasarkan status.
     *
     * @param string $status
     * @return mixed
     */
    public function getPermissionByStatus($status);

    /**
     * Membuat permission baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createPermission(array $data);

    /**
     * Memperbarui permission berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updatePermission($id, array $data);

    /**
     * Menghapus permission berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deletePermission($id);

    /**
     * Mengupdate permission status.
     *
     * @param int $id
     * @param string $status
     * @return mixed
     */
    public function updatePermissionStatus($id, $status);
}