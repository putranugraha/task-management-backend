<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Services\Contracts\UserServiceInterface;

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
        $status = $request->query('status');

        if ($status === null) {
            $users = $this->userService->getAllUsers();
        } elseif (in_array($status, ['active', 'inactive', 'suspended', 'pending'])) {
            $users = $this->userService->getUserByStatus($status);
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
            'status' => 'required|in:active,inactive,suspended,pending',
        ]);

        $user = $this->userService->updateUserStatus($id, $request->input('status'));

        if (!$user) {
            return response()->json(['message' => 'Failed to update user status'], 404);
        }
        return new UserResource($user);
    }
}
