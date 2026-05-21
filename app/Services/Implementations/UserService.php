<?php

namespace App\Services\Implementations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
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

        if ($user) {
            $actor = Auth::user();

            $properties = [
                'user_id' => $user->id,
                'email' => $user->email,
                'division_id' => $user->division_id,
                'status' => $user->status,
                'is_active' => $user->is_active,
            ];

            $activity = activity('users')
                ->performedOn($user)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('created');

            if ($role) {
                $roleActivity = activity('roles')
                    ->performedOn($user)
                    ->withProperties([
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'role' => $role,
                        'action' => 'assigned_on_create',
                    ]);

                if ($actor) {
                    $roleActivity->causedBy($actor);
                }

                $roleActivity->log('role_assigned');
            }
        }

        return $user;
    }

    public function updateUser($id, array $data)
    {
        $role = $data['role'] ?? null;
        unset($data['role']);

        $before = $this->userRepository->getUserById($id);

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

        if ($user) {
            $actor = Auth::user();

            $properties = [
                'user_id' => $user->id,
                'email_before' => $before->email ?? null,
                'email_after' => $user->email,
                'division_id_before' => $before->division_id ?? null,
                'division_id_after' => $user->division_id,
                'status_before' => $before->status ?? null,
                'status_after' => $user->status,
                'is_active_before' => $before->is_active ?? null,
                'is_active_after' => $user->is_active,
            ];

            $activity = activity('users')
                ->performedOn($user)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('updated');

            if ($role) {
                $oldRoles = $before ? $before->getRoleNames()->toArray() : [];
                $newRoles = $user->getRoleNames()->toArray();

                $roleActivity = activity('roles')
                    ->performedOn($user)
                    ->withProperties([
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'old_roles' => $oldRoles,
                        'new_roles' => $newRoles,
                        'action' => 'updated',
                    ]);

                if ($actor) {
                    $roleActivity->causedBy($actor);
                }

                $roleActivity->log('role_changed');
            }
        }

        return $user;
    }

    public function deleteUser($id)
    {
        $before = $this->userRepository->getUserById($id);
        $user = $this->userRepository->updateUserStatus($id, 'Non Aktif');

        if ($user) {
            $this->clearUserCaches($id);

            $actor = Auth::user();

            $properties = [
                'user_id' => $user->id,
                'email' => $user->email,
                'division_id' => $user->division_id,
                'status_before' => $before->status ?? null,
                'status_after' => $user->status,
                'is_active_before' => $before->is_active ?? null,
                'is_active_after' => $user->is_active,
                'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [],
            ];

            $activity = activity('users')
                ->performedOn($user)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('deactivated');
        }

        return (bool) $user;
    }

    public function updateUserStatus($id, $status)
    {
        $before = $this->userRepository->getUserById($id);
        $user = $this->userRepository->updateUserStatus($id, $status);

        if ($user) {
            $this->clearUserCaches($id);

            $actor = Auth::user();

            $properties = [
                'user_id' => $user->id,
                'status_before' => $before->status ?? null,
                'status_after' => $user->status,
                'is_active_before' => $before->is_active ?? null,
                'is_active_after' => $user->is_active,
            ];

            $activity = activity('users')
                ->performedOn($user)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('status_changed');
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
