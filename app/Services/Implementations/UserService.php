<?php

namespace App\Services\Implementations;

use Illuminate\Support\Facades\Cache;
use App\Services\Contracts\UserServiceInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserService implements UserServiceInterface
{
    protected $userRepository;

    const USERS_ALL_CACHE_KEY = 'users.all';
    const USERS_AKTIF_CACHE_KEY = 'users.Aktif';
    const USERS_NONAKTIF_CACHE_KEY = 'users.NonAktif';

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getAllUsers()
    {
        return Cache::remember(self::USERS_ALL_CACHE_KEY, 3600, function () {
            return $this->userRepository->getAllUsers();
        });
    }

    public function getUserById($id)
    {
        return $this->userRepository->getUserById($id);
    }

    public function getUserByStatus($status)
    {
        // Hanya izinkan status 'Aktif' dan 'Non Aktif'
        if (!in_array($status, ['Aktif', 'Non Aktif'])) {
            return collect();
        }
        return $this->userRepository->getUserByStatus($status);
    }

    public function getActiveUsers()
    {
        return Cache::remember(self::USERS_AKTIF_CACHE_KEY, 3600, function () {
            return $this->userRepository->getUserByStatus('Aktif');
        });
    }

    public function getInactiveUsers()
    {
        return Cache::remember(self::USERS_NONAKTIF_CACHE_KEY, 3600, function () {
            return $this->userRepository->getUserByStatus('Non Aktif');
        });
    }

    public function createUser(array $data)
    {
        $role = $data['role'] ?? null;
        $user = $this->userRepository->createUser($data);

        if ($user && $role) {
            $user->assignRole($role);
        }

        $this->clearUserCaches();
        return $user;
    }

    public function updateUser($id, array $data)
    {
        $role = $data['role'] ?? null;
        $user = $this->userRepository->updateUser($id, $data);

        if ($user && $role) {
            $user->syncRoles([$role]);
        }
        $this->clearUserCaches($id);
        return $user;
    }

    public function deleteUser($id)
    {
        $result = $this->userRepository->deleteUser($id);
        $this->clearUserCaches($id);
        return $result;
    }

    public function updateUserStatus($id, $status)
    {
        $user = $this->getUserById($id);

        if ($user) {
            $result = $this->userRepository->updateUserStatus($id, $status);
            $this->clearUserCaches($id);
            return $result;
        }

        return null;
    }

    public function clearUserCaches($id = null)
    {
        Cache::forget(self::USERS_ALL_CACHE_KEY);
        Cache::forget(self::USERS_AKTIF_CACHE_KEY);
        Cache::forget(self::USERS_NONAKTIF_CACHE_KEY);

        if ($id) {
            Cache::forget("user_{$id}_with_roles");
        }
    }
}