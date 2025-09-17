<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        $types = [Task::class, Project::class, Milestone::class];
        $type = fake()->randomElement($types);

        $entityFactory = match ($type) {
            Task::class => Task::factory(),
            Project::class => Project::factory(),
            Milestone::class => Milestone::factory(),
        };

        $exts = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt',
        ];
        $mime = array_rand($exts);
        $ext = $exts[$mime];
        $base = fake()->unique()->bothify('file-####');
        $filename = $base.'.'.$ext;
        $path = 'attachments/'.now()->format('Y/m').'/'.$filename;

        return [
            'entity_type' => $type,
            'entity_id' => $entityFactory,
            'uploaded_by' => User::factory(),
            'filename' => $filename,
            'mime' => $mime,
            'storage_path' => $path,
            'size' => fake()->numberBetween(1_024, 5_242_880), // 1KB - 5MB
            'uploaded_at' => fake()->dateTimeBetween('-60 days', 'now')->format('Y-m-d H:i:s'),
        ];
    }
}

