<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Role::whereIn('name', ['Admin', 'Manager', 'Member'])->count() < 3) {
            $this->call(RolePermissionSeeder::class);
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password_hash' => 'password',
                'job_title' => 'Administrator',
                'is_active' => true,
                'status' => 'Aktif',
                'last_login_at' => null,
            ]
        );
        $admin->assignRole('Admin');
        $admin->removeRole('Member');

        $manager = User::updateOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Manager',
                'password_hash' => 'password',
                'job_title' => 'Project Manager',
                'is_active' => true,
                'status' => 'Aktif',
                'last_login_at' => null,
            ]
        );
        $manager->assignRole('Manager');
        $manager->removeRole('Member');

        $member = User::updateOrCreate(
            ['email' => 'member@example.com'],
            [
                'name' => 'Member',
                'password_hash' => 'password',
                'job_title' => 'Team Member',
                'is_active' => true,
                'status' => 'Aktif',
                'last_login_at' => null,
            ]
        );
        $member->assignRole('Member');
    }
}
 
