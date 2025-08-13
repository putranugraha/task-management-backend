<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		// Reset cached roles and permissions
		app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

		// Permissions for task management
		$permissions = [
			'manage tasks',
			'manage projects',
			'manage users',
			'view tasks',
			'view projects',
		];

		foreach ($permissions as $perm) {
			Permission::firstOrCreate(['name' => $perm]);
		}

		// Roles
		$admin = Role::firstOrCreate(['name' => 'Admin']);
		$user = Role::firstOrCreate(['name' => 'User']);

		// Assign all permissions to Admin
		$admin->syncPermissions($permissions);

		// Assign only view tasks and view projects to User
		$user->syncPermissions([
			'view tasks',
			'view projects',
		]);
	}
}
