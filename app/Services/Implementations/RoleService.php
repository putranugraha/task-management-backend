<?php

namespace App\Services\Implementations;

use App\Services\Contracts\RoleServiceInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

class RoleService implements RoleServiceInterface
{
    protected $roleRepository;

    const ROLES_ALL_CACHE_KEY = 'roles.all';
    const ROLES_ACTIVE_CACHE_KEY = 'roles.active';
    const ROLES_INACTIVE_CACHE_KEY = 'roles.inactive';

    /**
     * Konstruktor RoleService.
     *
     * @param RoleRepositoryInterface $roleRepository
     */
    public function __construct(RoleRepositoryInterface $roleRepository)
    {
        $this->roleRepository = $roleRepository;
    }

    /**
     * Mengambil semua roles.
     *
     * @return mixed
     */
    public function getAllRoles()
    {
    return Cache::remember(self::ROLES_ALL_CACHE_KEY, 3600, function () {
            return $this->roleRepository->getAllRoles();
        });
    }

    /**
     * Mengambil role berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getRoleById($id)
    {
        return $this->roleRepository->getRoleById($id);
    }

    /**
     * Mengambil role berdasarkan nama.
     *
     * @param string $name
     * @return mixed
     */
    public function getRoleByName($name)
    {
        return $this->roleRepository->getRoleByName($name);
    }

    /**
     * Mengambil role berdasarkan status.
     *
     * @param string $status
     * @return mixed
     */
    public function getRoleByStatus($status)
    {
        return $this->roleRepository->getRoleByStatus($status);
    }

    /**
     * Mengambil roles dengan status aktif.
     *
     * @return mixed
     */
    public function getActiveRoles()
    {
    return Cache::remember(self::ROLES_ACTIVE_CACHE_KEY, 3600, function () {
            return $this->roleRepository->getRoleByStatus('Aktif');
        });
    }

    /**
     * Mengambil roles dengan status tidak aktif.
     *
     * @return mixed
     */
    public function getInactiveRoles()
    {
    return Cache::remember(self::ROLES_INACTIVE_CACHE_KEY, 3600, function () {
            return $this->roleRepository->getRoleByStatus('Non Aktif');
        });
    }

    /**
     * Membuat role baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createRole(array $data)
    {
        $data['guard_name'] = 'web';
        $permissions = $data['permissions'] ?? [];

        // Membuat role baru
        $role = $this->roleRepository->createRole($data);

        // Sinkronisasi permissions
        if (!empty($permissions) && $role) {
            $role->syncPermissions($permissions);
            $role->loadMissing('permissions');
        }

        // Clear cache
        $this->clearRoleCaches();

        if ($role) {
            $actor = Auth::user();

            $properties = [
                'role_id' => $role->id,
                'name' => $role->name,
                'status' => $role->status ?? null,
                'permissions' => method_exists($role, 'permissions') ? $role->permissions->pluck('name')->toArray() : [],
            ];

            $activity = activity('roles')
                ->performedOn($role)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('created');
        }

        return $role;
    }

    /**
     * Memperbarui role berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updateRole($id, array $data)
    {
        $before = $this->roleRepository->getRoleById($id);

        $data['guard_name'] = 'web';
        $permissions = $data['permissions'] ?? [];

        // Memperbarui role
        $role = $this->roleRepository->updateRole($id, $data);

        // Sinkronisasi permissions
        if (!empty($permissions) && $role) {
            $role->syncPermissions($permissions);
            $role->loadMissing('permissions');
        }

        // Clear cache
        $this->clearRoleCaches();

        if ($role) {
            $actor = Auth::user();

            $beforePermissions = $before && method_exists($before, 'permissions')
                ? $before->permissions->pluck('name')->toArray()
                : [];
            $afterPermissions = method_exists($role, 'permissions')
                ? $role->permissions->pluck('name')->toArray()
                : [];

            $properties = [
                'role_id' => $role->id,
                'name_before' => $before->name ?? null,
                'name_after' => $role->name,
                'status_before' => $before->status ?? null,
                'status_after' => $role->status ?? null,
                'permissions_before' => $beforePermissions,
                'permissions_after' => $afterPermissions,
            ];

            $activity = activity('roles')
                ->performedOn($role)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('updated');
        }

        return $role;
    }

    /**
     * Menghapus role berdasarkan ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteRole($id)
    {
        $role = $this->roleRepository->getRoleById($id);

        // Menghapus role
        $result = $this->roleRepository->deleteRole($id);

        // Clear cache
        $this->clearRoleCaches();

        if ($result && $role) {
            $actor = Auth::user();

            $properties = [
                'role_id' => $role->id,
                'name' => $role->name,
                'status' => $role->status ?? null,
                'permissions' => method_exists($role, 'permissions') ? $role->permissions->pluck('name')->toArray() : [],
            ];

            $activity = activity('roles')
                ->performedOn($role)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('deleted');
        }

        return $result;
    }

    /**
     * Mengupdate status role.
     *
     * @param int $id
     * @param string $status
     * @return mixed
     */
    public function updateRoleStatus($id, $status)
    {
        $role = $this->getRoleById($id);

        if ($role) {
            $beforeStatus = $role->status ?? null;

            $result = $this->roleRepository->updateRoleStatus($id, $status);

            $this->clearRoleCaches();

            if ($result) {
                $actor = Auth::user();

                $properties = [
                    'role_id' => $result->id,
                    'name' => $result->name,
                    'status_before' => $beforeStatus,
                    'status_after' => $result->status ?? null,
                ];

                $activity = activity('roles')
                    ->performedOn($result)
                    ->withProperties($properties);

                if ($actor) {
                    $activity->causedBy($actor);
                }

                $activity->log('status_changed');
            }

            return $result;
        }

        return null;
    }

    /**
     * Menghapus semua cache role
     *
     * @return void
     */
    public function clearRoleCaches()
    {
        Cache::forget(self::ROLES_ALL_CACHE_KEY);
        Cache::forget(self::ROLES_ACTIVE_CACHE_KEY);
        Cache::forget(self::ROLES_INACTIVE_CACHE_KEY);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
