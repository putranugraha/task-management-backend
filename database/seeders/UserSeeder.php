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
        // Admin user
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);
        $admin->assignRole('Admin');
        $admin->removeRole('Member'); // Remove default Member role

        // Manager user
        $manager = User::factory()->create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
        ]);
        $manager->assignRole('Manager');
        $manager->removeRole('Member'); // Remove default Member role

        // Member user
        $member = User::factory()->create([
            'name' => 'Member',
            'email' => 'member@example.com',
        ]);
        $member->assignRole('Member');
    }
}
