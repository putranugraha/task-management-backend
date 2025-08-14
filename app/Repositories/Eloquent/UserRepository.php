<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * Class UserRepository
 *
 * This class is the repository for the User model.
 * It handles all the database operations related to the user.
 *
 * @package App\Repositories\Eloquent
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * The User model instance.
     *
     * @var \App\Models\User
     */
    protected User $model;

    /**
     * Create a new UserRepository instance.
     *
     * @param \App\Models\User $model
     */
    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /**
     * Mengambil semua user beserta relasi roles.
     * Fetches all users with their roles.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getAllUsers()
    {
        // Eager load the 'roles' relationship to avoid N+1 query problems.
        return $this->model->with(['roles'])->get();
    }

    /**
     * Mengambil user berdasarkan ID.
     * Fetches a user by their ID with roles.
     *
     * @param int $id
     * @return \App\Models\User|null
     */
    public function getUserById($id)
    {
        try {
            // Find the user by ID or throw an exception if not found.
            return $this->model->with('roles')->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            // Log a warning if the user is not found.
            Log::warning("User with ID {$id} not found.", ['exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Mengambil user berdasarkan status.
     * Fetches users by their status.
     *
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserByStatus($status)
    {
    return $this->model->where('status', $status)->with('roles')->get();
    }

    /**
     * Membuat user baru.
     * Creates a new user.
     *
     * @param array $data
     * @return \App\Models\User|null
     */
    public function createUser(array $data)
    {
        try {
            // Create the user with the given data.
            $user = $this->model->create($data);
            // Return the newly created user with their roles.
            return $this->model->with('roles')->find($user->id);
        } catch (\Exception $e) {
            // Log an error if user creation fails.
            Log::error("Failed to create user: {$e->getMessage()}", ['exception' => $e, 'data' => $data]);
            return null;
        }
    }

    /**
     * Memperbarui user berdasarkan ID.
     * Updates a user by their ID.
     *
     * @param int $id
     * @param array $data
     * @return \App\Models\User|null
     */
    public function updateUser($id, array $data)
    {
        // Find the user first.
        $user = $this->getUserById($id);

        if (!$user) {
            return null; // The getUserById method already logged the warning.
        }

        try {
            // Update the user's data.
            $user->update($data);
            // Return the updated user with roles.
            return $this->model->with('roles')->find($user->id);
        } catch (\Exception $e) {
            // Log an error if the update fails.
            Log::error("Failed to update user with ID {$id}: {$e->getMessage()}", ['exception' => $e, 'data' => $data]);
            return null;
        }
    }

    /**
     * Menghapus user berdasarkan ID.
     * Deletes a user by their ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteUser($id)
    {
        $user = $this->getUserById($id);

        if (!$user) {
            return false;
        }

        try {
            // Delete the user.
            return $user->delete();
        } catch (\Exception $e) {
            // Log an error if deletion fails.
            Log::error("Failed to delete user with ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }

    /**
     * Mengupdate status user.
     * Updates a user's status.
     *
     * @param int $id
     * @param string $status
     * @return \App\Models\User|null
     */
    public function updateUserStatus($id, $status)
    {
        $user = $this->getUserById($id);

        if ($user) {
            $user->status = $status;
            $user->save();
            return $user;
        }

        return null;
    }

    /**
     * Mengambil user berdasarkan role.
     * Fetches users by a specific role.
     *
     * @param string $roleName
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUsersByRole($roleName)
    {
        // This assumes you have a 'role' scope or a relationship query set up.
        // A common way is using whereHas for relationships.
        return $this->model->whereHas('roles', function ($query) use ($roleName) {
            $query->where('name', $roleName);
        })->with('roles')->get();
    }
}
