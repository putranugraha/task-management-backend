<?php

namespace App\Services\Implementations;

use Illuminate\Support\Facades\Cache;
use App\Services\Contracts\UserServiceInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserService implements UserServiceInterface
{
    /**
     * @var UserRepositoryInterface
     */
    protected $userRepository;

    // Cache keys for user collections
    const USERS_ALL_CACHE_KEY = 'users.all';
    const USERS_AKTIF_CACHE_KEY = 'users.aktif';
    const USERS_NONAKTIF_CACHE_KEY = 'users.nonaktif';

    // Cache duration in seconds (e.g., 3600 = 1 hour)
    const CACHE_DURATION = 3600;

    /**
     * UserService constructor.
     *
     * @param UserRepositoryInterface $userRepository
     */
    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Get all users, with caching.
     *
     * @return \Illuminate\Database\Eloquent\Collection|mixed
     */
    public function getAllUsers()
    {
        return Cache::remember(self::USERS_ALL_CACHE_KEY, self::CACHE_DURATION, function () {
            return $this->userRepository->getAllUsers();
        });
    }

    /**
     * Get a single user by ID, with caching.
     *
     * @param int $id
     * @return \App\Models\User|null
     */
    public function getUserById($id)
    {
        // Define a unique cache key for this specific user
        $cacheKey = "user.{$id}";

        // Retrieve from cache or fetch from repository and then cache it
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($id) {
            return $this->userRepository->getUserById($id);
        });
    }

    /**
     * Get a user by name. Caching is not applied here as it's less common.
     *
     * @param string $name
     * @return \App\Models\User|null
     */
    public function getUserByName($name)
    {
        return $this->userRepository->getUserByName($name);
    }

    /**
     * Get users by status.
     *
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserByStatus($status)
    {
        if (!in_array($status, ['Aktif', 'Non Aktif'])) {
            return collect(); // Return an empty collection for invalid status
        }
        return $this->userRepository->getUserByStatus($status);
    }

    /**
     * Get all active users, with caching.
     *
     * @return \Illuminate\Database\Eloquent\Collection|mixed
     */
    public function getActiveUsers()
    {
        return Cache::remember(self::USERS_AKTIF_CACHE_KEY, self::CACHE_DURATION, function () {
            return $this->userRepository->getUserByStatus('Aktif');
        });
    }

    /**
     * Get all inactive users, with caching.
     *
     * @return \Illuminate\Database\Eloquent\Collection|mixed
     */
    public function getInactiveUsers()
    {
        return Cache::remember(self::USERS_NONAKTIF_CACHE_KEY, self::CACHE_DURATION, function () {
            return $this->userRepository->getUserByStatus('Non Aktif');
        });
    }

    /**
     * Create a new user and assign a role.
     *
     * @param array $data
     * @return \App\Models\User|null
     */
    public function createUser(array $data)
    {
        $role = $data['role'] ?? null;
        unset($data['role']); // Ensure 'role' is not passed to the user creation data

        $user = $this->userRepository->createUser($data);

        if ($user && $role) {
            $user->assignRole($role);
        }

        $this->clearUserCaches();
        return $user;
    }

    /**
     * Update an existing user and sync their role.
     *
     * @param int $id
     * @param array $data
     * @return \App\Models\User|null
     */
    public function updateUser($id, array $data)
    {
        $role = $data['role'] ?? null;
        unset($data['role']);

        $user = $this->userRepository->updateUser($id, $data);

        if ($user && $role) {
            // syncRoles expects an array of roles
            $user->syncRoles([$role]);
        }

        $this->clearUserCaches($id);
        return $user;
    }

    /**
     * Delete a user by ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteUser($id)
    {
        $result = $this->userRepository->deleteUser($id);
        if ($result) {
            $this->clearUserCaches($id);
        }
        return $result;
    }

    /**
     * Update a user's status.
     *
     * @param int $id
     * @param string $status
     * @return \App\Models\User|null
     */
    public function updateUserStatus($id, $status)
    {
        $user = $this->userRepository->updateUserStatus($id, $status);

        if ($user) {
            $this->clearUserCaches($id);
        }

        return $user;
    }

    /**
     * Clear relevant user caches.
     *
     * @param int|null $id
     */
    protected function clearUserCaches($id = null)
    {
        // Clear collection caches
        Cache::forget(self::USERS_ALL_CACHE_KEY);
        Cache::forget(self::USERS_AKTIF_CACHE_KEY);
        Cache::forget(self::USERS_NONAKTIF_CACHE_KEY);

        // Clear individual user cache if an ID is provided
        if ($id) {
            Cache::forget("user.{$id}");
        }
    }
}
