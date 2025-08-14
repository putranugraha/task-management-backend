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

		// Permissions for task management
		$permissions = [
			'mengelola user',
			'mengelola role',
			'mengelola project',
			'mengelola tugas',
			'melihat project',
			'melihat tugas',
			'mencetak laporan',
		];

		foreach ($permissions as $permission) {
			Permission::firstOrCreate(['name' => $permission]);
		}

		// Admin: semua permission
		$admin = Role::firstOrCreate(['name' => 'Admin']);
		$admin->syncPermissions($permissions);

		// Manager: tidak bisa kelola user/role
		$manager = Role::firstOrCreate(['name' => 'Manager']);
		$manager->syncPermissions([
			'mengelola project',
			'mengelola tugas',
			'melihat project',
			'melihat tugas',
			'mencetak laporan',
		]);

		// Member: hanya bisa lihat project & tugas
		$member = Role::firstOrCreate(['name' => 'Member']);
		$member->syncPermissions([
			'melihat project',
			'melihat tugas',
		]);
	}
}
