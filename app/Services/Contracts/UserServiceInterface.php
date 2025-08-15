<?php

namespace App\Services\Contracts;

interface UserServiceInterface
{
    /**
     * Mengambil semua users.
     *
     * @return mixed
     */
    public function getAllUsers();

    /**
     * Mengambil user berdasarkan ID.
     *
     * @param int $id The ID of the user.
     * @return mixed
     */
    public function getUserById($id);


    /**
     * Mengambil user berdasarkan status.
     *
     * @param string $status
     * @return mixed
     */
    public function getUserByStatus($status);

    /**
     * Mengambil semua users yang aktif.
     *
     * @return mixed
     */
    public function getActiveUsers();

    /**
     * Mengambil semua users yang tidak aktif.
     *
     * @return mixed
     */
    public function getInactiveUsers();

    /**
     * Membuat user baru.
     *
     * @param array $data The data for the new user.
     * @return mixed
     */
    public function createUser(array $data);

    /**
     * Memperbarui user berdasarkan ID.
     *
     * @param int $id The ID of the user.
     * @param array $data The data to update the user with.
     * @return mixed
     */
    public function updateUser($id, array $data);

    /**
     * Menghapus user berdasarkan ID.
     *
     * @param int $id The ID of the user.
     * @return mixed
     */
    public function deleteUser($id);

    /**
     * Mengupdate status user.
     *
     * @param int $id The ID of the user.
     * @param string $status New status value.
     * @return mixed
     */
    public function updateUserStatus($id, $status);
}