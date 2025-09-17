<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserRepository implements UserRepositoryInterface
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function getAllUsers()
    {
        return $this->user->with(['roles', 'division'])->get();
    }

    public function getUserById($id)
    {
        try {
            return $this->user->with(['roles', 'division'])->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("User with ID {$id} not found.");
            return null;
        }
    }

    public function getUserByName($name)
    {
        return $this->user->where('name', $name)->with(['roles', 'division'])->first();
    }

    public function getUserByStatus($status)
    {
        if (!in_array($status, ['Aktif', 'Non Aktif'])) {
            return collect();
        }

        return $this->user->where('status', $status)->with(['roles', 'division'])->get();
    }

    public function getActiveUsers()
    {
        return $this->getUserByStatus('Aktif');
    }

    public function getInactiveUsers()
    {
        return $this->getUserByStatus('Non Aktif');
    }

    public function createUser(array $data)
    {
        try {
            $user = $this->user->create($data);
            return $user->loadMissing(['roles', 'division']);
        } catch (\Exception $e) {
            Log::error("Failed to create user: {$e->getMessage()}");
            return null;
        }
    }

    public function updateUser($id, array $data)
    {
        $user = $this->findUser($id);

        if ($user) {
            try {
                $user->update($data);
                return $user->loadMissing(['roles', 'division']);
            } catch (\Exception $e) {
                Log::error("Failed to update user with ID {$id}: {$e->getMessage()}");
                return null;
            }
        }

        return null;
    }

    public function deleteUser($id)
    {
        $user = $this->findUser($id);

        if ($user) {
            try {
                $user->delete();
                return true;
            } catch (\Exception $e) {
                Log::error("Failed to delete user with ID {$id}: {$e->getMessage()}");
                return false;
            }
        }

        return false;
    }

    protected function findUser($id)
    {
        try {
            return $this->user->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Log::error("User with ID {$id} not found.");
            return null;
        }
    }

    public function updateUserStatus($id, $status)
    {
        $user = $this->findUser($id);

        if ($user) {
            $user->status = $status;
            $user->is_active = $status === 'Aktif';
            $user->save();

            return $user->loadMissing(['roles', 'division']);
        }

        return null;
    }
}
