<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\Project;
use App\Models\Milestone;
use App\Models\User;

class AttachmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        if ($users->isEmpty()) {
            $users = User::factory()->count(3)->create();
        }

        $projects = Project::all();
        if ($projects->isEmpty()) {
            $projects = Project::factory()->count(3)->create();
        }

        $tasks = Task::all();
        if ($tasks->isEmpty()) {
            $tasks = Task::factory()->count(5)->create();
        }

        $milestones = Milestone::all();
        if ($milestones->isEmpty()) {
            $milestones = Milestone::factory()->count(3)->create();
        }

        $seedFor = function ($collection, string $type) use ($users) {
            foreach ($collection as $entity) {
                $count = fake()->numberBetween(0, 3);
                for ($i = 0; $i < $count; $i++) {
                    $exts = [
                        'image/png' => 'png',
                        'image/jpeg' => 'jpg',
                        'application/pdf' => 'pdf',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                        'text/plain' => 'txt',
                    ];
                    $mime = array_rand($exts);
                    $ext = $exts[$mime];
                    $base = fake()->unique()->bothify('doc-####');
                    $filename = $base.'.'.$ext;
                    $path = 'attachments/'.now()->format('Y/m').'/'.$filename;

                    Attachment::create([
                        'entity_type' => $type,
                        'entity_id' => $entity->id,
                        'uploaded_by' => $users->random()->id,
                        'filename' => $filename,
                        'mime' => $mime,
                        'storage_path' => $path,
                        'size' => fake()->numberBetween(2_048, 10_485_760),
                        'uploaded_at' => now()->subDays(fake()->numberBetween(0, 60))->startOfDay()->addHours(fake()->numberBetween(8, 18)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        };

        $seedFor($projects, \App\Models\Project::class);
        $seedFor($tasks, \App\Models\Task::class);
        $seedFor($milestones, \App\Models\Milestone::class);
    }
}

