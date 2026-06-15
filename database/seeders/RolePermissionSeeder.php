<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions aligned with CRUD actions. Legacy "mengelola ..."
        // permissions are intentionally not seeded for new installs.
        $permissions = [
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
            'melihat milestones',
            'membuat milestones',
            'mengubah milestones',
            'menghapus milestones',
            'melihat divisions',
            'membuat divisions',
            'mengubah divisions',
            'menghapus divisions',
            'melihat tugas',
            'membuat tugas',
            'mengubah tugas',
            'menghapus tugas',
            'melihat biaya aktual',
            'membuat biaya aktual',
            'menghapus biaya aktual',
            'mengelola tugas sendiri',
            'mengisi entri waktu',
            'melihat komentar',
            'membuat komentar',
            'mengubah komentar',
            'menghapus komentar',
            'melihat lampiran',
            'membuat lampiran',
            'mengubah lampiran',
            'menghapus lampiran',
            'melihat laporan pribadi',
            'melihat laporan project',
            'mencetak laporan',
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                ['status' => 'Aktif']
            );
        }

        // Roles kept as in this project
        $admin = Role::updateOrCreate(
            ['name' => 'Admin', 'guard_name' => 'web'],
            ['status' => 'Aktif']
        );
        $manager = Role::updateOrCreate(
            ['name' => 'Manager', 'guard_name' => 'web'],
            ['status' => 'Aktif']
        );
        $member = Role::updateOrCreate(
            ['name' => 'Member', 'guard_name' => 'web'],
            ['status' => 'Aktif']
        );

        // Admin: all permissions
        $admin->syncPermissions(Permission::all());

        // Manager: all except managing users/roles/permissions
        $managerDisallowed = [
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
        ];
        $managerPermissions = Permission::whereNotIn('name', $managerDisallowed)->get();
        $manager->syncPermissions($managerPermissions);

        // Member: read-only access
        $member->syncPermissions([
            Permission::where('name', 'melihat project')->first(),
            Permission::where('name', 'melihat milestones')->first(),
            Permission::where('name', 'melihat divisions')->first(),
            Permission::where('name', 'melihat tugas')->first(),
            Permission::where('name', 'melihat biaya aktual')->first(),
            Permission::where('name', 'mengelola tugas sendiri')->first(),
            Permission::where('name', 'mengisi entri waktu')->first(),
            Permission::where('name', 'melihat komentar')->first(),
            Permission::where('name', 'membuat komentar')->first(),
            Permission::where('name', 'mengubah komentar')->first(),
            Permission::where('name', 'menghapus komentar')->first(),
            Permission::where('name', 'melihat lampiran')->first(),
            Permission::where('name', 'membuat lampiran')->first(),
            Permission::where('name', 'mengubah lampiran')->first(),
            Permission::where('name', 'menghapus lampiran')->first(),
            Permission::where('name', 'melihat laporan pribadi')->first(),
        ]);
    }
}
