<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskProgressEntry extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'progress_date',
        'percent_complete',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'progress_date' => 'date',
            'percent_complete' => 'integer',
        ];
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

