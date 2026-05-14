<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'client_name',
        'value_amount',
        'scope',
        'objective',
        'division_owner_id',
        'start_planned',
        'end_planned',
        'status',
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
            'value_amount' => 'decimal:2',
        ];
    }

    /**
     * Owner relation (assumes owner is a User).
     */
    public function divisionOwner()
    {
        return $this->belongsTo(User::class, 'division_owner_id');
    }

    /**
     * Baseline snapshots captured for the project.
     */
    public function baselines()
    {
        return $this->hasMany(ProjectBaseline::class);
    }

    /**
     * Reporting periods captured for the project.
     */
    public function reportingPeriods()
    {
        return $this->hasMany(ReportingPeriod::class);
    }

    /**
     * Milestones belonging to the project.
     */
    public function milestones()
    {
        return $this->hasMany(Milestone::class);
    }
}

