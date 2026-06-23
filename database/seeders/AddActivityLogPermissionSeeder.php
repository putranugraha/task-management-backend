<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AddActivityLogPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::updateOrCreate(
            [
                'name' => 'melihat activity log',
                'guard_name' => 'web',
            ],
            [
                'status' => 'Aktif',
            ]
        );

        $admin = Role::where('name', 'Admin')
            ->where('guard_name', 'web')
            ->firstOrFail();

        $manager = Role::where('name', 'Manager')
            ->where('guard_name', 'web')
            ->firstOrFail();

        // Hanya menambahkan, tidak menghapus permission lama.
        if (! $admin->hasPermissionTo($permission)) {
            $admin->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}