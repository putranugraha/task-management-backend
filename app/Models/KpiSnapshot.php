<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiSnapshot extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'period_id',
        'tasks_total',
        'tasks_done',
        'overdue_count',
        'avg_cycle_time_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tasks_total' => 'integer',
            'tasks_done' => 'integer',
            'overdue_count' => 'integer',
            'avg_cycle_time_days' => 'decimal:2',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function reportingPeriod()
    {
        return $this->belongsTo(ReportingPeriod::class, 'period_id');
    }
}
