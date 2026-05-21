<?php

namespace App\Services\Implementations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Services\Contracts\PermissionServiceInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;


class PermissionService implements PermissionServiceInterface
{
    protected $permissionRepository;

    const PERMISSIONS_ALL_CACHE_KEY = 'permissions.all';
    const PERMISSIONS_ACTIVE_CACHE_KEY = 'permissions.active';
    const PERMISSIONS_INACTIVE_CACHE_KEY = 'permissions.inactive';

    /**
     * Konstruktor PermissionService.
     *
     * @param PermissionRepositoryInterface $permissionRepository
     */
    public function __construct(PermissionRepositoryInterface $permissionRepository)
    {
        $this->permissionRepository = $permissionRepository;
    }

    /**
     * Mengambil semua permissions.
     *
     * @return mixed
     */
    public function getAllPermissions()
    {
        return Cache::remember(self::PERMISSIONS_ALL_CACHE_KEY, 3600, function () {
            return $this->permissionRepository->getAllPermissions();
        });
    }

    /**
     * Mengambil permission berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getPermissionById($id)
    {
        return $this->permissionRepository->getPermissionById($id);
    }

    /**
     * Mengambil permission berdasarkan nama.
     *
     * @param string $name
     * @return mixed
     */
    public function getPermissionByName($name)
    {
        return $this->permissionRepository->getPermissionByName($name);
    }

    /**
     * Mengambil permission berdasarkan status.
     *
     * @param string $status
     * @return mixed
     */
    public function getPermissionByStatus($status)
    {
        return $this->permissionRepository->getPermissionByStatus($status);
    }

    /**
     * Mengambil permissions dengan status aktif.
     *
     * @return mixed
     */
    public function getActivePermissions()
    {
        return Cache::remember(self::PERMISSIONS_ACTIVE_CACHE_KEY, 3600, function () {
            return $this->permissionRepository->getPermissionByStatus('Aktif');
        });
    }

    /**
     * Mengambil permissions dengan status tidak aktif.
     *
     * @return mixed
     */
    public function getInactivePermissions()
    {
        return Cache::remember(self::PERMISSIONS_INACTIVE_CACHE_KEY, 3600, function () {
            return $this->permissionRepository->getPermissionByStatus('Non Aktif');
        });
    }

    /**
     * Membuat permission baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createPermission(array $data)
    {
        $data['guard_name'] = 'web';
        $result = $this->permissionRepository->createPermission($data);
        $this->clearPermissionCaches();

        if ($result) {
            $actor = Auth::user();

            $properties = [
                'permission_id' => $result->id,
                'name' => $result->name,
                'status' => $result->status ?? null,
            ];

            $activity = activity('permissions')
                ->performedOn($result)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('created');
        }

        return $result;
    }

    /**
     * Memperbarui permission berdasarkan ID.
     *
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function updatePermission($id, array $data)
    {
        $before = $this->permissionRepository->getPermissionById($id);

        $data['guard_name'] = 'web';
        $result = $this->permissionRepository->updatePermission($id, $data);
        $this->clearPermissionCaches();

        if ($result) {
            $actor = Auth::user();

            $properties = [
                'permission_id' => $result->id,
                'name_before' => $before->name ?? null,
                'name_after' => $result->name,
                'status_before' => $before->status ?? null,
                'status_after' => $result->status ?? null,
            ];

            $activity = activity('permissions')
                ->performedOn($result)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('updated');
        }

        return $result;
    }

    /**
     * Menghapus permission berdasarkan ID.
     *
     * @param int $id
     * @return bool
     */
    public function deletePermission($id)
    {
        $permission = $this->permissionRepository->getPermissionById($id);

        $result = $this->permissionRepository->deletePermission($id);
        $this->clearPermissionCaches();

        if ($result && $permission) {
            $actor = Auth::user();

            $properties = [
                'permission_id' => $permission->id,
                'name' => $permission->name,
                'status' => $permission->status ?? null,
            ];

            $activity = activity('permissions')
                ->performedOn($permission)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('deleted');
        }

        return $result;
    }

    public function updatePermissionStatus($id, $status)
    {
        $permission = $this->getPermissionById($id);

        if ($permission) {
            $beforeStatus = $permission->status ?? null;

            $result = $this->permissionRepository->updatePermissionStatus($id, $status);

            $this->clearPermissionCaches($id);

            if ($result) {
                $actor = Auth::user();

                $properties = [
                    'permission_id' => $result->id,
                    'name' => $result->name,
                    'status_before' => $beforeStatus,
                    'status_after' => $result->status ?? null,
                ];

                $activity = activity('permissions')
                    ->performedOn($result)
                    ->withProperties($properties);

                if ($actor) {
                    $activity->causedBy($actor);
                }

                $activity->log('status_changed');
            }

            return $result;
        }
    }

    /**
     * Menghapus semua cache permission
     *
     * @return void
     */
    public function clearPermissionCaches()
    {
        Cache::forget(self::PERMISSIONS_ALL_CACHE_KEY);
        Cache::forget(self::PERMISSIONS_ACTIVE_CACHE_KEY);
        Cache::forget(self::PERMISSIONS_INACTIVE_CACHE_KEY);
    }
}
