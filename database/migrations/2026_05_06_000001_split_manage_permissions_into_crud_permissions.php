<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'melihat users',
        'membuat users',
        'mengubah users',
        'menghapus users',
        'melihat roles',
        'membuat roles',
        'mengubah roles',
        'menghapus roles',
        'melihat permissions',
        'membuat permissions',
        'mengubah permissions',
        'menghapus permissions',
        'melihat project',
        'membuat project',
        'mengubah project',
        'menghapus project',
        'melihat tugas',
        'membuat tugas',
        'mengubah tugas',
        'menghapus tugas',
        'melihat komentar',
        'membuat komentar',
        'mengubah komentar',
        'menghapus komentar',
        'melihat lampiran',
        'membuat lampiran',
        'mengubah lampiran',
        'menghapus lampiran',
    ];

    private array $legacyMap = [
        'mengelola users' => ['melihat users', 'membuat users', 'mengubah users', 'menghapus users'],
        'mengelola roles' => ['melihat roles', 'membuat roles', 'mengubah roles', 'menghapus roles'],
        'mengelola permissions' => ['melihat permissions', 'membuat permissions', 'mengubah permissions', 'menghapus permissions'],
        'mengelola project' => ['melihat project', 'membuat project', 'mengubah project', 'menghapus project'],
        'mengelola tugas' => ['melihat tugas', 'membuat tugas', 'mengubah tugas', 'menghapus tugas'],
        'mengelola komentar' => ['melihat komentar', 'membuat komentar', 'mengubah komentar', 'menghapus komentar'],
        'mengelola lampiran' => ['melihat lampiran', 'membuat lampiran', 'mengubah lampiran', 'menghapus lampiran'],
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach ($this->legacyMap as $legacy => $granularPermissions) {
            $legacyPermission = Permission::where('name', $legacy)->where('guard_name', 'web')->first();

            if (!$legacyPermission) {
                continue;
            }

            $roles = Role::permission($legacyPermission)->get();

            foreach ($roles as $role) {
                $role->givePermissionTo($granularPermissions);
            }

            $directAssignments = DB::table('model_has_permissions')
                ->where('permission_id', $legacyPermission->id)
                ->get();

            foreach ($granularPermissions as $granularPermission) {
                $newPermission = Permission::where('name', $granularPermission)->where('guard_name', 'web')->first();

                if (!$newPermission) {
                    continue;
                }

                foreach ($directAssignments as $assignment) {
                    $row = (array) $assignment;
                    $row['permission_id'] = $newPermission->id;
                    DB::table('model_has_permissions')->insertOrIgnore($row);
                }
            }
        }

        Permission::whereIn('name', array_keys($this->legacyMap))
            ->where('guard_name', 'web')
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (array_keys($this->legacyMap) as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        DB::table('permissions')
            ->whereIn('name', $this->permissions)
            ->where('guard_name', 'web')
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
