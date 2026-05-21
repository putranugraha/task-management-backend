<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'from_status',
        'to_status',
        'changed_by',
        'note',
    ];

    protected function casts(): array
    {
        return [];
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

