<?php

namespace Database\Seeders;

use App\Models\Division;
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

        $divisions = [
            'software' => Division::updateOrCreate(
                ['code' => 'SW'],
                [
                    'name' => 'Software',
                    'description' => 'Tim software untuk frontend, backend, integrasi, QA, DevOps, dan deployment.',
                    'status' => 'Aktif',
                ]
            ),
            'creative' => Division::updateOrCreate(
                ['code' => 'CR'],
                [
                    'name' => 'Creative',
                    'description' => 'Tim creative untuk discovery, UI/UX, konten, visual, dan optimasi digital.',
                    'status' => 'Aktif',
                ]
            ),
        ];

        $users = [
            ['name' => 'Gus Sastra', 'email' => 'gussastra@gmail.com', 'role' => 'Member', 'division' => 'creative'],
            ['name' => 'Wira', 'email' => 'wira@gmail.com', 'role' => 'Admin', 'division' => 'software'],
            ['name' => 'Gungaria', 'email' => 'gungaria@gmail.com', 'role' => 'Member', 'division' => 'software'],
            ['name' => 'Gungindra', 'email' => 'gungindra@gmail.com', 'role' => 'Member', 'division' => 'creative'],
            ['name' => 'Krisna', 'email' => 'krisna@gmail.com', 'role' => 'Member', 'division' => 'creative'],
            ['name' => 'Mahen', 'email' => 'mahen@gmail.com', 'role' => 'Member', 'division' => 'software'],
            ['name' => 'Dwiki', 'email' => 'dwiki@gmail.com', 'role' => 'Manager', 'division' => 'creative'],
            ['name' => 'Divo', 'email' => 'divo@gmail.com', 'role' => 'Member', 'division' => 'software'],
            ['name' => 'Wisnu', 'email' => 'wisnu@gmail.com', 'role' => 'Member', 'division' => 'creative'],
            ['name' => 'Gustra', 'email' => 'gustra@gmail.com', 'role' => 'Member', 'division' => 'software'],
            ['name' => 'Madeadi', 'email' => 'madeadi@gmail.com', 'role' => 'Manager', 'division' => 'software'],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password_hash' => 'password',
                    'division_id' => $divisions[$userData['division']]->id,
                    'job_title' => '-',
                    'is_active' => true,
                    'status' => 'Aktif',
                    'last_login_at' => null,
                ]
            );

            $user->syncRoles([$userData['role']]);
        }
    }
}
