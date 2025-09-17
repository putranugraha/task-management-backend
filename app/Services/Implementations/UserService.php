<?php

namespace App\Services\Implementations;

use Illuminate\Support\Facades\Cache;
use App\Services\Contracts\UserServiceInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserService implements UserServiceInterface
{
    protected UserRepositoryInterface $userRepository;

    const USERS_ALL_CACHE_KEY = 'users.all';
    const USERS_AKTIF_CACHE_KEY = 'users.aktif';
    const USERS_NONAKTIF_CACHE_KEY = 'users.nonaktif';
    const CACHE_DURATION = 3600;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getAllUsers()
    {
        return Cache::remember(self::USERS_ALL_CACHE_KEY, self::CACHE_DURATION, function () {
            return $this->userRepository->getAllUsers();
        });
    }

    public function getUserById($id)
    {
        $cacheKey = "user.{$id}";

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($id) {
            return $this->userRepository->getUserById($id);
        });
    }

    public function getUserByName($name)
    {
        return $this->userRepository->getUserByName($name);
    }

    public function getUserByStatus($status)
    {
        if (!in_array($status, ['Aktif', 'Non Aktif'])) {
            return collect();
        }
        return $this->userRepository->getUserByStatus($status);
    }

    public function getActiveUsers()
    {
        return Cache::remember(self::USERS_AKTIF_CACHE_KEY, self::CACHE_DURATION, function () {
            return $this->userRepository->getUserByStatus('Aktif');
        });
    }

    public function getInactiveUsers()
    {
        return Cache::remember(self::USERS_NONAKTIF_CACHE_KEY, self::CACHE_DURATION, function () {
            return $this->userRepository->getUserByStatus('Non Aktif');
        });
    }

    public function createUser(array $data)
    {
        $role = $data['role'] ?? null;
        unset($data['role']);

        if (isset($data['password'])) {
            $data['password_hash'] = $data['password'];
            unset($data['password'], $data['password_confirmation']);
        }

        if (!array_key_exists('is_active', $data)) {
            $data['is_active'] = true;
        }

        if (!array_key_exists('status', $data)) {
            $data['status'] = $data['is_active'] ? 'Aktif' : 'Non Aktif';
        }

        if (!array_key_exists('last_login_at', $data)) {
            $data['last_login_at'] = null;
        }

        $user = $this->userRepository->createUser($data);

        if ($user && $role) {
            $user->assignRole($role);
        }

        if ($user) {
            $user->loadMissing('roles', 'division');
        }

        $this->clearUserCaches();
        return $user;
    }

    public function updateUser($id, array $data)
    {
        $role = $data['role'] ?? null;
        unset($data['role']);

        if (empty($data['password'])) {
            unset($data['password'], $data['password_confirmation']);
        } else {
            $data['password_hash'] = $data['password'];
            unset($data['password'], $data['password_confirmation']);
        }

        if (array_key_exists('status', $data) && !array_key_exists('is_active', $data)) {
            $data['is_active'] = $data['status'] === 'Aktif';
        }

        if (array_key_exists('is_active', $data) && !array_key_exists('status', $data)) {
            $data['status'] = $data['is_active'] ? 'Aktif' : 'Non Aktif';
        }

        $user = $this->userRepository->updateUser($id, $data);

        if ($user && $role) {
            $user->syncRoles([$role]);
        }

        if ($user) {
            $user->loadMissing('roles', 'division');
        }

        $this->clearUserCaches($id);
        return $user;
    }

    public function deleteUser($id)
    {
        $result = $this->userRepository->deleteUser($id);
        if ($result) {
            $this->clearUserCaches($id);
        }
        return $result;
    }

    public function updateUserStatus($id, $status)
    {
        $user = $this->userRepository->updateUserStatus($id, $status);

        if ($user) {
            $this->clearUserCaches($id);
        }

        return $user;
    }

    protected function clearUserCaches($id = null)
    {
        Cache::forget(self::USERS_ALL_CACHE_KEY);
        Cache::forget(self::USERS_AKTIF_CACHE_KEY);
        Cache::forget(self::USERS_NONAKTIF_CACHE_KEY);

        if ($id) {
            Cache::forget("user.{$id}");
        }
    }
}
