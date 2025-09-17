<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            DivisionSeeder::class,
            ProjectSeeder::class,
            ProjectBaselineSeeder::class,
            MilestoneSeeder::class,
            TaskSeeder::class,
            TaskBaselineSeeder::class,
            TaskDependencySeeder::class,
            TaskAssignmentSeeder::class,
            StatusHistorySeeder::class,
            TimeEntrySeeder::class,
            CommentSeeder::class,
            AttachmentSeeder::class,
        ]);
    }
}




