<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'title',
        'description',
        'priority',
        'status',
        'start_planned',
        'end_planned',
        'duration_planned',
        'start_actual',
        'end_actual',
        'duration_actual',
        'percent_complete',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_planned' => 'date',
            'end_planned' => 'date',
            'start_actual' => 'date',
            'end_actual' => 'date',
            'duration_planned' => 'integer',
            'duration_actual' => 'integer',
            'percent_complete' => 'integer',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function dependencies()
    {
        return $this->hasMany(TaskDependency::class, 'task_id')->with('dependsOn');
    }

    public function dependents()
    {
        return $this->hasMany(TaskDependency::class, 'depends_on_task_id')->with('task');
    }

    public function statusHistories()
    {
        return $this->hasMany(StatusHistory::class)->latest('id');
    }
}
