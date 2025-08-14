<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class UserRepository implements UserRepositoryInterface
{
    /**
     * @var User
     */
    protected $user;

    /**
     * Konstruktor UserRepository.
     *
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Mengambil semua users beserta relasi roles.
     *
     * @return mixed
     */
    public function getAllUsers()
    {
        return $this->user->with('roles')->get();
    }

    /**
     * Mengambil user berdasarkan ID beserta relasi roles.
     *
     * @param int $id
     * @return mixed
     */
    public function getUserById($id)
    {
        try {
            return $this->user->with('roles')->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("User with ID {$id} not found.");
            return null;
        }
    }

    /**
     * Mengambil user berdasarkan status beserta relasi roles.
     *
     * @param string $status
     * @return mixed
     */
    public function getUserByStatus($status)
    {
        // Hanya izinkan status 'Aktif' dan 'Non Aktif'
        if (!in_array($status, ['Aktif', 'Non Aktif'])) {
            return collect();
        }
        return $this->user->where('status', $status)->with('roles')->get();
    }

    /**
     * Membuat user baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createUser(array $data)
    {
        try {
            $user = $this->user->create($data);
            return $this->user->with('roles')->find($user->id);
        } catch (\Exception $e) {
            Log::error("Failed to create user: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Memperbarui user berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updateUser($id, array $data)
    {
        $user = $this->findUser($id);

        if ($user) {
            try {
                $user->update($data);
                return $this->user->with('roles')->find($user->id);
            } catch (\Exception $e) {
                Log::error("Failed to update user with ID {$id}: {$e->getMessage()}");
                return null;
            }
        }
        return null;
    }

    /**
     * Menghapus user berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deleteUser($id)
    {
        $user = $this->findUser($id);

        if ($user) {
            try {
                $user->delete();
                return true;
            } catch (\Exception $e) {
                Log::error("Failed to delete user with ID {$id}: {$e->getMessage()}");
                return false;
            }
        }
        return false;
    }

    /**
     * Helper method untuk menemukan user berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    protected function findUser($id)
    {
        try {
            return $this->user->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("User with ID {$id} not found.");
            return null;
        }
    }

    /**
     * Mengupdate user status.
     *
     * @param int $id
     * @param string $status
     * @return mixed
     */
    public function updateUserStatus($id, $status)
    {
        // Hanya izinkan status 'Aktif' dan 'Non Aktif'
        if (!in_array($status, ['Aktif', 'Non Aktif'])) {
            return null;
        }
        $user = $this->findUser($id);

        if ($user) {
            $user->status = $status;
            $user->save();
            return $user;
        }
        return null;
    }
}