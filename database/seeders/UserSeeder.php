<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;
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

        $this->call(MemberSeeder::class);

        $software = Division::updateOrCreate(
            ['code' => 'SW'],
            [
                'name' => 'Software',
                'description' => 'Tim software untuk frontend, backend, integrasi, QA, DevOps, dan deployment.',
                'status' => 'Aktif',
            ]
        );

        $creative = Division::updateOrCreate(
            ['code' => 'CR'],
            [
                'name' => 'Creative',
                'description' => 'Tim creative untuk discovery, UI/UX, konten, visual, dan optimasi digital.',
                'status' => 'Aktif',
            ]
        );

        $testUsers = [
            ['name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'Admin', 'division_id' => $software->id],
            ['name' => 'Manager', 'email' => 'manager@example.com', 'role' => 'Manager', 'division_id' => $software->id],
            ['name' => 'Member', 'email' => 'member@example.com', 'role' => 'Member', 'division_id' => $creative->id],
        ];

        foreach ($testUsers as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password_hash' => 'password',
                    'division_id' => $userData['division_id'],
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
 
