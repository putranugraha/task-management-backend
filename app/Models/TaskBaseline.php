<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskBaseline extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'baseline_id',
        'task_id',
        'start_planned_base',
        'end_planned_base',
        'duration_planned_base',
        'weight',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_planned_base' => 'date',
            'end_planned_base' => 'date',
            'duration_planned_base' => 'integer',
            'weight' => 'decimal:2',
        ];
    }

    public function baseline()
    {
        return $this->belongsTo(ProjectBaseline::class, 'baseline_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}

