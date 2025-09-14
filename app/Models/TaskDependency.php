<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskDependency extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'depends_on_task_id',
        'type',
        'lag_days',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lag_days' => 'integer',
        ];
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function dependsOn()
    {
        return $this->belongsTo(Task::class, 'depends_on_task_id');
    }
}

