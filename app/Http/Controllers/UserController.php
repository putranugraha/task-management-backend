<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Services\Contracts\UserServiceInterface;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserServiceInterface $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request)
    {
        $status = $request->query('status');

        if ($status === null) {
            $users = $this->userService->getAllUsers();
        } elseif (in_array($status, ['Aktif', 'Non Aktif'])) {
            $users = $this->userService->getUserByStatus($status);
        } else {
            return response()->json(['error' => 'Invalid status parameter'], 400);
        }

        if (!$users || $users->isEmpty()) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        return UserResource::collection($users);
    }

    public function store(UserStoreRequest $request)
    {
        $user = $this->userService->createUser($request->all());
        if (!$user) {
            return response()->json(['message' => 'Gagal membuat user'], 400);
        }
        return new UserResource($user);
    }

    public function show(string $id)
    {
        $user = $this->userService->getUserById($id);
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }
        return new UserResource($user);
    }

    public function update(UserUpdateRequest $request, string $id)
    {
        $user = $this->userService->updateUser($id, $request->all());
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }
        return new UserResource($user);
    }

    public function destroy(string $id)
    {
        $deleted = $this->userService->deleteUser($id);

        if (!$deleted) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }
        return response()->json(['message' => 'User berhasil dihapus']);
    }

    public function updateStatus(string $id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:Aktif,Non Aktif',
        ]);

        $user = $this->userService->updateUserStatus($id, $request->input('status'));

        if (!$user) {
            return response()->json(['message' => 'Failed to update user status'], 404);
        }
        return new UserResource($user);
    }
}