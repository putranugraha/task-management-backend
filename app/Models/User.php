<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'division_id',
        'job_title',
        'is_active',
        'last_login_at',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'password_hash' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function activePermissionNames()
    {
        $this->loadMissing('permissions', 'roles.permissions');

        $directPermissions = $this->permissions
            ->filter(fn ($permission) => ($permission->status ?? 'Aktif') === 'Aktif')
            ->pluck('name');

        $rolePermissions = $this->roles
            ->filter(fn ($role) => ($role->status ?? 'Aktif') === 'Aktif')
            ->flatMap(fn ($role) => $role->permissions)
            ->filter(fn ($permission) => ($permission->status ?? 'Aktif') === 'Aktif')
            ->pluck('name');

        return $directPermissions
            ->merge($rolePermissions)
            ->unique()
            ->values();
    }
}

