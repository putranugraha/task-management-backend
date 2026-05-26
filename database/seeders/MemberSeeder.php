<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Role::whereIn('name', ['Admin', 'Manager', 'Member'])->count() < 3) {
            $this->call(RolePermissionSeeder::class);
        }

        $users = [
            ['name' => 'Gus Sastra', 'email' => 'gussastra@gmail.com', 'role' => 'Manager', 'job_title' => 'Administrator'],
            ['name' => 'Wira', 'email' => 'wira@gmail.com', 'role' => 'Manager', 'job_title' => 'Project Manager'],
            ['name' => 'Gungaria', 'email' => 'gungaria@gmail.com', 'role' => 'Member', 'job_title' => 'Team Member'],
            ['name' => 'Gungindra', 'email' => 'gungindra@gmail.com', 'role' => 'Member', 'job_title' => 'Team Member'],
            ['name' => 'Krisna', 'email' => 'krisna@gmail.com', 'role' => 'Member', 'job_title' => 'Team Member'],
            ['name' => 'Mahen', 'email' => 'mahen@gmail.com', 'role' => 'Member', 'job_title' => 'Team Member'],
            ['name' => 'Dwiki', 'email' => 'dwiki@gmail.com', 'role' => 'Member', 'job_title' => 'Team Member'],
            ['name' => 'Divo', 'email' => 'divo@gmail.com', 'role' => 'Member', 'job_title' => 'Team Member'],
            ['name' => 'Wisnu', 'email' => 'wisnu@gmail.com', 'role' => 'Member', 'job_title' => 'Team Member'],
            ['name' => 'Gustra', 'email' => 'gustra@gmail.com', 'role' => 'Member', 'job_title' => 'Team Member'],
            ['name' => 'Madeadi', 'email' => 'madeadi@gmail.com', 'role' => 'Manager', 'job_title' => 'Team Member'],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password_hash' => 'password',
                    'job_title' => $userData['job_title'],
                    'is_active' => true,
                    'status' => 'Aktif',
                    'last_login_at' => null,
                ]
            );

            $user->syncRoles([$userData['role']]);
        }
    }
}
