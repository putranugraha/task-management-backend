<?php

namespace App\Services\Implementations;

use App\Services\Contracts\RoleServiceInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Facades\Cache;

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
        }

        // Clear cache
        $this->clearRoleCaches();

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
        $data['guard_name'] = 'web';
        $permissions = $data['permissions'] ?? [];

        // Memperbarui role
        $role = $this->roleRepository->updateRole($id, $data);

        // Sinkronisasi permissions
        if (!empty($permissions) && $role) {
            $role->syncPermissions($permissions);
        }

        // Clear cache
        $this->clearRoleCaches();

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
        // Menghapus role
        $result = $this->roleRepository->deleteRole($id);

        // Clear cache
        $this->clearRoleCaches();

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
            $result = $this->roleRepository->updateRoleStatus($id, $status);

            $this->clearRoleCaches();

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
    }
}
