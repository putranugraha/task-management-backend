<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'user_id',
        'content',
    ];

    public function entity()
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

