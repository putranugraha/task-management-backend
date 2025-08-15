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

        // Manager user
        $manager = User::factory()->create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
        ]);
        $manager->assignRole('Manager');

        // Member user
        $member = User::factory()->create([
            'name' => 'Member',
            'email' => 'member@example.com',
        ]);
        $member->assignRole('Member');
    }
}
