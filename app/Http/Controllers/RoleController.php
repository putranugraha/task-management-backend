<?php

namespace App\Http\Controllers;


use App\Http\Requests\RoleStoreRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Request;
use App\Services\Contracts\RoleServiceInterface;

class RoleController extends Controller
{
    /**
     * @var RoleServiceInterface $roleService
     */
    protected $roleService;

    /**
     * Konstruktor RoleController.
     */
    public function __construct(RoleServiceInterface $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $status = $request->query('status');

        if ($status === null) {
            $roles = $this->roleService->getAllRoles();
        } elseif ($status === 'Aktif') {
            $roles = $this->roleService->getActiveRoles();
        } elseif ($status === 'Non Aktif') {
            $roles = $this->roleService->getInactiveRoles();
        } else {
            return response()->json(['error' => 'Invalid status parameter'], 400);
        }

        if (!$roles) {
            return response()->json(['message' => 'Role tidak ditemukan'], 404);
        }

        return RoleResource::collection($roles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RoleStoreRequest $request)
    {
        try {
            $role = $this->roleService->createRole($request->all());
            if (!$role) {
                return response()->json(['message' => 'Gagal membuat role'], 400);
            }
            return new RoleResource($role);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $role = $this->roleService->getRoleById($id);
        if (!$role) {
            return response()->json(['message' => 'Role tidak ditemukan'], 404);
        }
        return new RoleResource($role);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RoleUpdateRequest $request, string $id)
    {
        try {
            $role = $this->roleService->updateRole($id, $request->all());
            if (!$role) {
                return response()->json(['message' => 'Role tidak ditemukan'], 404);
            }
            return new RoleResource($role);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $deleted = $this->roleService->deleteRole($id);
            if (!$deleted) {
                return response()->json(['message' => 'Role tidak ditemukan'], 404);
            }
            return response()->json(['message' => 'Role berhasil dihapus'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update Status Role.
     */
    public function updateStatus(string $id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:Aktif,Non Aktif',
        ]);

        $status = $request->input('status');
        $role = $this->roleService->updateRoleStatus($id, $status);

        if (!$role) {
            return response()->json(['message' => 'Failed to update role status'], 404);
        }
        return new RoleResource($role);
    }
}
