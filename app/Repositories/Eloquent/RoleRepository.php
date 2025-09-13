<?php

namespace App\Repositories\Eloquent;


use App\Repositories\Contracts\RoleRepositoryInterface;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
class RoleRepository implements RoleRepositoryInterface
{
    /**
     * @var Role
     */
    protected $role;

    /**
     * Konstruktor RoleRepository.
     *
     * @param Role $role
     */
    public function __construct(Role $role)
    {
        $this->role = $role;
    }

    /**
     * Mengambil semua roles.
     *
     * @return mixed
     */
    public function getAllRoles()
    {
        return $this->role->all();
    }

    /**
     * Mengambil role berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function getRoleById($id)
    {
        try {
            // Mengambil role berdasarkan ID, handle jika tidak ditemukan
            return $this->role->findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error("Role with ID {$id} not found.");
            return null;
        }
    }

    /**
     * Mengambil role berdasarkan nama.
     *
     * @param string $name
     * @return mixed
     */
    public function getRoleByName($name)
    {
        return $this->role->where('name', $name)->first();
    }

    /**
     * Mengambil role berdasarkan status.
     *
     * @param string $status
     * @return mixed
     */
    public function getRoleByStatus($status)
    {
        return $this->role->where('status', $status)->get();
    }

    /**
     * Membuat role baru.
     *
     * @param array $data
     * @return mixed
     */
    public function createRole(array $data)
    {
        try {
            return $this->role->create($data);
        } catch (\Exception $e) {
            Log::error("Failed to create role: {$e->getMessage()}");
            return null;
        }
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
        $role = $this->findRole($id);

        if ($role) {
            try {
                $role->update($data);
                return $role;
            } catch (\Exception $e) {
                Log::error("Failed to update role with ID {$id}: {$e->getMessage()}");
                return null;
            }
        }
        return null;
    }

    /**
     * Menghapus role berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    public function deleteRole($id)
    {
        $role = $this->findRole($id);

        if ($role) {
            try {
                $role->delete();
                return true;
            } catch (\Exception $e) {
                Log::error("Failed to delete role with ID {$id}: {$e->getMessage()}");
                return false;
            }
        }
        return false;
    }

    /**
     * Helper method untuk menemukan role berdasarkan ID.
     *
     * @param int $id
     * @return mixed
     */
    protected function findRole($id)
    {
        try {
            return $this->role->findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error("Role with ID {$id} not found.");
            return null;
        }
    }

    /**
     * Mengupdate role status.
     *
     * @param int $id
     * @param string $status
     * @return mixed
     */
    public function updateRoleStatus($id, $status)
    {
        $role = $this->findRole($id);

        if ($role) {
            $role->status = $status;
            $role->save();
            return $role;
        }
        return null;
    }
}