<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskCostEntry extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'incurred_on',
        'amount',
        'category',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'incurred_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}

