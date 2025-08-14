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
        $admin = User::firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Admin',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('Admin');

        // Manager user
        $manager = User::firstOrCreate([
            'email' => 'manager@example.com',
        ], [
            'name' => 'Manager',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $manager->assignRole('Manager');

        // Member user
        $member = User::firstOrCreate([
            'email' => 'member@example.com',
        ], [
            'name' => 'Member',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $member->assignRole('Member');
    }
}
