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

        // Permissions aligned with project (pluralized where applicable)
        $permissions = [
            'mengelola users',
            'mengelola roles',
            'mengelola permissions',
            'mengelola project',
            'mengelola tugas',
            'mengelola tugas sendiri',
            'mengisi entri waktu',
            'mengelola komentar',
            'mengelola lampiran',
            'melihat project',
            'melihat tugas',
            'melihat laporan pribadi',
            'mencetak laporan',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Roles kept as in this project
        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $manager = Role::firstOrCreate(['name' => 'Manager']);
        $member = Role::firstOrCreate(['name' => 'Member']);

        // Admin: all permissions
        $admin->syncPermissions(Permission::all());

        // Manager: all except managing users/roles/permissions
        $managerDisallowed = [
            'mengelola users',
            'mengelola roles',
            'mengelola permissions',
        ];
        $managerPermissions = Permission::whereNotIn('name', $managerDisallowed)->get();
        $manager->syncPermissions($managerPermissions);

        // Member: read-only access
        $member->syncPermissions([
            Permission::where('name', 'melihat project')->first(),
            Permission::where('name', 'melihat tugas')->first(),
            Permission::where('name', 'mengelola tugas sendiri')->first(),
            Permission::where('name', 'mengisi entri waktu')->first(),
            Permission::where('name', 'mengelola komentar')->first(),
            Permission::where('name', 'mengelola lampiran')->first(),
            Permission::where('name', 'melihat laporan pribadi')->first(),
        ]);
    }
}
