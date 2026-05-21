<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::query()
            ->with('causer')
            ->latest('created_at');

        if ($logName = $request->query('log_name')) {
            $query->where('log_name', $logName);
        }

        if ($event = $request->query('event')) {
            $query->where('description', $event);
        }

        if ($causerId = $request->query('causer_id')) {
            $query->where('causer_id', $causerId);
        }

        if ($subjectType = $request->query('subject_type')) {
            $query->where('subject_type', $subjectType);
        }

        if ($subjectId = $request->query('subject_id')) {
            $query->where('subject_id', $subjectId);
        }

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 20;
        }

        $activities = $query->paginate($perPage);

        $data = $activities->map(function (Activity $activity) {
            return [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'event' => $activity->description,
                'time' => optional($activity->created_at)?->toIso8601String(),
                'actor_id' => $activity->causer_id,
                'actor_name' => optional($activity->causer)->name ?? null,
                'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
                'subject_id' => $activity->subject_id,
                'properties' => $activity->properties,
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }
}
