<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Services\Contracts\UserServiceInterface;
use App\Services\Contracts\RoleServiceInterface;


class UserController extends Controller
{
    /**
     * @var UserServiceInterface $userService
     */
    protected $userService;

    /**
     * Konstruktor UserController.
     */
    public function __construct(UserServiceInterface $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Ambil parameter status dari query string
        $status = $request->query('status');

        if ($status === null) {
            // Jika tidak ada query parameter, ambil semua user
            $users = $this->userService->getAllUsers();
        } elseif ($status == 1) {
            // Jika status = 1, ambil user dengan status aktif
            $users = $this->userService->getActiveUsers();
        } elseif ($status == 0) {
            // Jika status = 0 ambil user dengan status tidak aktif
            $users = $this->userService->getInactiveUsers();
        } else {
            return response()->json(['error' => 'Invalid status parameter'], 400);
        }

        if (!$users) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        return UserResource::collection($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserStoreRequest $request)
    {
        $user = $this->userService->createUser($request->all());
        if (!$user) {
            return response()->json(['message' => 'Gagal membuat user'], 400);
        }
        return new UserResource($user);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = $this->userService->getUserById($id);
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }
        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserUpdateRequest $request, string $id)
    {
        $user = $this->userService->updateUser($id, $request->all());
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }
        return new UserResource($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $deleted = $this->userService->deleteUser($id);

        if (!$deleted) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }
        return response()->json(['message' => 'User berhasil dihapus']);
    }

    /**
     * Update Status User.
     */
    public function updateStatus(string $id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:Aktif,Non Aktif',
        ]);

        $user = $this->userService->updateUserStatus($id, $request->validated());

        if (!$user) {
            return response()->json(['message' => 'Failed to update user status'], 404);
        }
        return new UserResource($user);
    }

    /**
     * Lightweight options endpoint for FE selects.
     * GET /api/users/options
     * Query params:
     * - status: 1 (active, default) | 0 (inactive) | all
     * - q: search by name/email (optional)
     * - limit: max items (default 50, max 200)
     */
    public function options(Request $request)
    {
        $status = (string) $request->query('status', '1');
        $q = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 50);
        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;

        if ($status === '1') {
            $users = $this->userService->getActiveUsers();
        } elseif ($status === '0') {
            $users = $this->userService->getInactiveUsers();
        } else {
            $users = $this->userService->getAllUsers();
        }

        // Ensure we have a collection to filter/map
        if (method_exists($users, 'items')) {
            $collection = collect($users->items());
        } elseif ($users instanceof \Illuminate\Support\Collection) {
            $collection = $users;
        } else {
            $collection = collect($users);
        }

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $collection = $collection->filter(function ($u) use ($needle) {
                $name = mb_strtolower((string) ($u->name ?? ''));
                $email = mb_strtolower((string) ($u->email ?? ''));
                return str_contains($name, $needle) || str_contains($email, $needle);
            });
        }

        $data = $collection
            ->take($limit)
            ->values()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
            ]);

        return response()->json(['data' => $data]);
    }
}
