<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password_hash' => 'password',
            'job_title' => 'Administrator',
            'is_active' => true,
            'status' => 'Aktif',
            'last_login_at' => null,
        ]);
        $admin->assignRole('Admin');
        $admin->removeRole('Member');

        $manager = User::factory()->create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password_hash' => 'password',
            'job_title' => 'Project Manager',
            'is_active' => true,
            'status' => 'Aktif',
            'last_login_at' => null,
        ]);
        $manager->assignRole('Manager');
        $manager->removeRole('Member');

        $member = User::factory()->create([
            'name' => 'Member',
            'email' => 'member@example.com',
            'password_hash' => 'password',
            'job_title' => 'Team Member',
            'is_active' => true,
            'status' => 'Aktif',
            'last_login_at' => null,
        ]);
        $member->assignRole('Member');
    }
}
