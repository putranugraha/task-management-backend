<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Milestone extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'name',
        'due_planned',
        'due_actual',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_planned' => 'date',
            'due_actual' => 'date',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}

