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
     * Get the middleware the controller should use.
     *
     * @return array
     */

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
        // Ambil parameter status dari query string
        $status = $request->query('status');

        if ($status === null) {
            // Jika tidak ada query parameter, ambil semua role
            $roles = $this->roleService->getAllRoles();
        } elseif ($status == 1) {
            // Jika status = 1, ambil role dengan status aktif
            $roles = $this->roleService->getActiveRoles();
        } elseif ($status == 0) {
            // Jika status = 0 ambil role dengan status tidak aktif
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
        $role = $this->roleService->createRole($request->all());
        if (!$role) {
            return response()->json(['message' => 'Gagal membuat role'], 400);
        }
        return new RoleResource($role);
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
        $role = $this->roleService->updateRole($id, $request->all());
        if (!$role) {
            return response()->json(['message' => 'Role tidak ditemukan'], 404);
        }
        return new RoleResource($role);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        if ($this->roleIsAssignedToCurrentUser($request, $id)) {
            return response()->json([
                'message' => 'Anda tidak dapat menonaktifkan role yang sedang digunakan akun Anda.',
            ], 403);
        }

        $deactivated = $this->roleService->deleteRole($id);

        if (!$deactivated) {
            return response()->json(['message' => 'Role tidak ditemukan'], 404);
        }

        return response()->json(['message' => 'Role berhasil dinonaktifkan'], 200);
    }

    /**
     * Update Status Role.
     */
    public function updateStatus(string $id, Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|in:Aktif,Non Aktif',
        ]);

        if ($validated['status'] === 'Non Aktif' && $this->roleIsAssignedToCurrentUser($request, $id)) {
            return response()->json([
                'message' => 'Anda tidak dapat menonaktifkan role yang sedang digunakan akun Anda.',
            ], 403);
        }

        $role = $this->roleService->updateRoleStatus($id, $validated['status']);

        if (!$role) {
            return response()->json(['message' => 'Failed to update role status'], 404);
        }
        return new RoleResource($role);
    }

    /**
     * Activate role.
     */
    public function activate(string $id)
    {
        $role = $this->roleService->updateRoleStatus($id, 'Aktif');

        if (!$role) {
            return response()->json(['message' => 'Role tidak ditemukan'], 404);
        }

        return new RoleResource($role);
    }

    protected function roleIsAssignedToCurrentUser(Request $request, string $id): bool
    {
        $user = $request->user();

        if (!$user) {
            return false;
        }

        $user->loadMissing('roles');

        return $user->roles->contains(fn ($role) => (int) $role->id === (int) $id);
    }
}
