<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectBaseline extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'baseline_name',
        'taken_at',
        'note',
        'start_planned_base',
        'end_planned_base',
        'value_amount_base',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'start_planned_base' => 'date',
            'end_planned_base' => 'date',
            'value_amount_base' => 'decimal:2',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Task-level baselines captured under this project baseline.
     */
    public function taskBaselines()
    {
        return $this->hasMany(TaskBaseline::class, 'baseline_id');
    }
}

