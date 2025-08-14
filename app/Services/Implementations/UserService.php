<?php

namespace App\Services\Implementations;

use App\Services\Contracts\UserServiceInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class UserService implements UserServiceInterface
{
    protected $repository;

    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Mengambil semua users.
     *
     * @return mixed
     */
    public function getAllUsers()
    {
        try {
            return $this->repository->getAllUsers();
        } catch (\Exception $e) {
            Log::error("Error getting all users: {$e->getMessage()}", ['exception' => $e]);
            return collect();
        }
    }

    /**
     * Mengambil user berdasarkan ID.
     *
     * @param int $id The ID of the user.
     * @return mixed
     */
    public function getUserById($id)
    {
        try {
            return $this->repository->getUserById($id);
        } catch (\Exception $e) {
            Log::error("Error getting user by ID {$id}: {$e->getMessage()}", ['exception' => $e]);
            return null;
        }
    }

    /**
     * Mengambil user berdasarkan status.
     *
     * @param string $status
     * @return mixed
     */
    public function getUserByStatus($status)
    {
        try {
            return $this->repository->getUserByStatus($status);
        } catch (\Exception $e) {
            Log::error("Error getting users by status {$status}: {$e->getMessage()}", ['exception' => $e]);
            return collect();
        }
    }

    /**
     * Membuat user baru.
     *
     * @param array $data The data for the new user.
     * @return mixed
     */
    public function createUser(array $data)
    {
        try {
            // Validasi data sebelum membuat user
            $this->validateUserData($data);

            return $this->repository->createUser($data);
        } catch (\Exception $e) {
            Log::error("Error creating user: {$e->getMessage()}", ['exception' => $e, 'data' => $data]);
            return null;
        }
    }

    /**
     * Memperbarui user berdasarkan ID.
     *
     * @param int $id The ID of the user.
     * @param array $data The data to update the user with.
     * @return mixed
     */
    public function updateUser($id, array $data)
    {
        try {
            // Validasi data sebelum update
            $this->validateUserData($data, $id);

            return $this->repository->updateUser($id, $data);
        } catch (\Exception $e) {
            Log::error("Error updating user with ID {$id}: {$e->getMessage()}", ['exception' => $e, 'data' => $data]);
            return null;
        }
    }

    /**
     * Menghapus user berdasarkan ID.
     *
     * @param int $id The ID of the user.
     * @return mixed
     */
    public function deleteUser($id)
    {
        try {
            return $this->repository->deleteUser($id);
        } catch (\Exception $e) {
            Log::error("Error deleting user with ID {$id}: {$e->getMessage()}", ['exception' => $e]);
            return false;
        }
    }

    /**
     * Mengupdate status user.
     *
     * @param int $id The ID of the user.
     * @param string $status New status value.
     * @return mixed
     */
    public function updateUserStatus($id, $status)
    {
        try {
            // Validasi status
            $this->validateStatus($status);

            return $this->repository->updateUserStatus($id, $status);
        } catch (\Exception $e) {
            Log::error("Error updating user status with ID {$id}: {$e->getMessage()}", ['exception' => $e, 'status' => $status]);
            return null;
        }
    }

    /**
     * Validasi data user.
     *
     * @param array $data
     * @param int|null $userId
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function validateUserData(array $data, $userId = null)
    {
        // Validasi email unik
        if (isset($data['email'])) {
            $query = User::where('email', $data['email']);
            if ($userId) {
                $query->where('id', '!=', $userId);
            }

            if ($query->exists()) {
                throw new \InvalidArgumentException('Email sudah digunakan.');
            }
        }

        // Validasi username unik jika ada
        if (isset($data['username'])) {
            $query = User::where('username', $data['username']);
            if ($userId) {
                $query->where('id', '!=', $userId);
            }

            if ($query->exists()) {
                throw new \InvalidArgumentException('Username sudah digunakan.');
            }
        }
    }

    /**
     * Validasi status user.
     *
     * @param string $status
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function validateStatus($status)
    {
        $validStatuses = ['active', 'inactive', 'suspended', 'pending'];

        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Status tidak valid. Status yang diizinkan: ' . implode(', ', $validStatuses));
        }
    }
}