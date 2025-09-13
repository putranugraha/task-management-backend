<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Http\Requests\PermissionStoreRequest;
use App\Http\Requests\PermissionUpdateRequest;
use App\Services\Contracts\PermissionServiceInterface;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Get the middleware the controller should use.
     *
     * @return array
     */

    /**
     * @var PermissionServiceInterface $permissionService
     */
    protected $permissionService;

    /**
     * Konstruktor PermissionController.
     */
    public function __construct(PermissionServiceInterface $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Ambil parameter status dari query string
        $status = $request->query('status');

        if ($status === null) {
            // Jika tidak ada query parameter, ambil semua permission
            $permissions = $this->permissionService->getAllPermissions();
        } elseif ($status == 1) {
            // Jika status = 1, ambil permission dengan status aktif
            $permissions = $this->permissionService->getActivePermissions();
        } elseif ($status == 0) {
            // Jika status = 0 ambil permission dengan status tidak aktif
            $permissions = $this->permissionService->getInactivePermissions();
        } else {
            return response()->json(['error' => 'Invalid status parameter'], 400);
        }

        if (!$permissions) {
            return response()->json(['message' => 'Permission tidak ditemukan'], 404);
        }

        return PermissionResource::collection($permissions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PermissionStoreRequest $request)
    {
        $permission = $this->permissionService->createPermission($request->validated());
        if (!$permission) {
            return response()->json(['message' => 'Gagal membuat permission'], 400);
        }
        return new PermissionResource($permission);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $permission = $this->permissionService->getPermissionById($id);
        if (!$permission) {
            return response()->json(['message' => 'Permission tidak ditemukan'], 404);
        }
        return new PermissionResource($permission);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PermissionUpdateRequest $request, string $id)
    {
        $permission = $this->permissionService->updatePermission($id, $request->validated());
        if (!$permission) {
            return response()->json(['message' => 'Permission tidak ditemukan'], 404);
        }

        return new PermissionResource($permission);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $deleted = $this->permissionService->deletePermission($id);

        if (!$deleted) {
            return response()->json(['message' => 'Permission tidak ditemukan'], 404);
        }

        return response()->json(['message' => 'Permission berhasil dihapus'], 200);
    }

    /**
     * Get Active Permissions.
     */
    public function getActivePermissions()
    {
        $permissions = $this->permissionService->getActivePermissions();
        if (!$permissions) {
            return response()->json(['message' => 'Permission tidak ditemukan'], 404);
        }
        return PermissionResource::collection($permissions);
    }

    /**
     * Update Status Permission.
     */
    public function updateStatus(string $id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:Aktif,Non Aktif',
        ]);

        $permission = $this->permissionService->updatePermissionStatus($id, $request->validated());

        if (!$permission) {
            return response()->json(['message' => 'Failed to update permission status'], 404);
        }
        return new PermissionResource($permission);
    }
}