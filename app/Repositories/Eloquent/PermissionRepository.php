<?php

namespace App\Repositories\Eloquent;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;

class PermissionRepository implements PermissionRepositoryInterface
{
    /**
     * @var Permission
     */
    protected $model;

    /**
     * Konstruktor PermissionRepository.
     *
     * @param Permission $permission
     */
    public function __construct(Permission $model)
    {
        $this->model = $model;
    }

    /**
     * Mengambil semua permissions.
     *
     * @return mixed
     */
    public function getAllPermissions()
    {
    return $this->model->all();
    }

    /**
     * Mengambil permission berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getPermissionById($id)
    {
        try {
            // Mengambil permission berdasarkan ID, handle jika tidak ditemukan
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Permission with ID {$id} not found.");
            return null;
        }
    }

    /**
     * Mengambil permission berdasarkan nama.
     *
     * @param string $name
     * @return mixed
     */
    public function getPermissionByName($name)
    {
    return $this->model->where('name', $name)->first();
    }

    /**
     * Mengambil permission berdasarkan status.
     *
     * @param string $status
     * @return mixed
     */
    public function getPermissionByStatus($status)
    {
    return $this->model->where('status', $status)->get();
    }

    /**
     * Membuat permission baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createPermission(array $data)
    {
        try {
            return $this->model->create($data);
        } catch (\Exception $e) {
            Log::error("Failed to create permission: {$e->getMessage()}");
            return null;
        }
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
        $permission = $this->findPermission($id);
        if ($permission) {
            try {
                $permission->update($data);
                return $permission;
            } catch (\Exception $e) {
                Log::error("Failed to update permission with ID {$id}: {$e->getMessage()}");
                return null;
            }
        }
        return null;
    }

    /**
     * Menghapus permission berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deletePermission($id)
    {
        $permission = $this->findPermission($id);
        if ($permission) {
            try {
                $permission->delete();
                return true;
            } catch (\Exception $e) {
                Log::error("Failed to delete permission with ID {$id}: {$e->getMessage()}");
                return false;
            }
        }
        return false;
    }

    /**
     * Helper method untuk menemukan permission berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    protected function findPermission($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("Permission with ID {$id} not found.");
            return null;
        }
    }

    /**
     * Mengupdate permission status.
     *
     * @param int $id
     * @param string $status
     * @return mixed
     */
    public function updatePermissionStatus($id, $status)
    {
        $permission = $this->findPermission($id);
        if ($permission) {
            $permission->status = $status;
            $permission->save();
            return $permission;
        }
        return null;
    }
}