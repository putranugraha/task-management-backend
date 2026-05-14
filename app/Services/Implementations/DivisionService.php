<?php

namespace App\Services\Implementations;

use App\Repositories\Contracts\DivisionRepositoryInterface;
use App\Services\Contracts\DivisionServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\Division;

class DivisionService implements DivisionServiceInterface
{
    protected DivisionRepositoryInterface $repository;

    const CACHE_ALL = 'divisions.all';
    const CACHE_ID_PREFIX = 'division.'; // + id
    const CACHE_CODE_PREFIX = 'division.code.'; // + code
    const CACHE_NAME_PREFIX = 'division.name.'; // + name
    const CACHE_STATUS_PREFIX = 'divisions.status.'; // + status
    const CACHE_COUNT_PREFIX = 'division.count.'; // + divisionId
    const CACHE_DURATION = 1800; // 30 minutes

    public function __construct(DivisionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllDivisions()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_DURATION, fn () => $this->repository->getAllDivisions());
    }

    public function getDivisionById($id)
    {
        return Cache::remember(self::CACHE_ID_PREFIX.$id, self::CACHE_DURATION, fn () => $this->repository->getDivisionById($id));
    }

    public function getDivisionByCode($code)
    {
        return Cache::remember(self::CACHE_CODE_PREFIX.$code, self::CACHE_DURATION, fn () => $this->repository->getDivisionByCode($code));
    }

    public function getDivisionByName($name)
    {
        return Cache::remember(self::CACHE_NAME_PREFIX.$name, self::CACHE_DURATION, fn () => $this->repository->getDivisionByName($name));
    }

    public function getDivisionByStatus($status)
    {
        if (!in_array($status, ['Aktif', 'Non Aktif'], true)) {
            return collect();
        }

        return Cache::remember(self::CACHE_STATUS_PREFIX.$status, self::CACHE_DURATION, fn () => $this->repository->getDivisionByStatus($status));
    }

    public function getActiveDivisions()
    {
        return $this->getDivisionByStatus('Aktif');
    }

    public function getInactiveDivisions()
    {
        return $this->getDivisionByStatus('Non Aktif');
    }

    public function createDivision(array $data)
    {
        // Auto-generate numeric codes (01, 02, 03, ...) to keep Division codes simple and consistent.
        // Code is still unique at the DB level; we retry a few times in case of race collisions.
        $data['code'] = $this->generateNextNumericCode();
        $data['status'] = $data['status'] ?? 'Aktif';

        $division = null;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $division = $this->repository->createDivision($data);
            if ($division) {
                break;
            }
            $data['code'] = $this->incrementNumericCode($data['code'] ?? null);
        }

        if ($division) {
            $this->clearCaches($division->id, $division->code, $division->name);

            $actor = Auth::user();

            $properties = [
                'division_id' => $division->id,
                'code' => $division->code,
                'name' => $division->name,
                'description' => $division->description,
                'status' => $division->status ?? 'Aktif',
            ];

            $activity = activity('divisions')
                ->performedOn($division instanceof Division ? $division : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('created');
        }
        return $division;
    }

    public function updateDivision($id, array $data)
    {
        $before = $this->repository->getDivisionById($id);
        $division = $this->repository->updateDivision($id, $data);
        if ($division) {
            $this->clearCaches($id, $division->code, $division->name);

            $actor = Auth::user();

            $properties = [
                'division_id' => $division->id,
                'code_before' => $before->code ?? null,
                'code_after' => $division->code,
                'name_before' => $before->name ?? null,
                'name_after' => $division->name,
                'description_before' => $before->description ?? null,
                'description_after' => $division->description,
                'status_before' => $before->status ?? null,
                'status_after' => $division->status ?? null,
            ];

            $activity = activity('divisions')
                ->performedOn($division instanceof Division ? $division : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('updated');
        }
        return $division;
    }

    public function deleteDivision($id)
    {
        $before = $this->getDivisionById($id);
        $division = $this->repository->updateDivisionStatus($id, 'Non Aktif');
        if ($division) {
            $this->clearCaches($id, $division->code ?? null, $division->name ?? null, $before->status ?? null);
            $this->clearCaches($id, $division->code ?? null, $division->name ?? null, $division->status ?? null);

            $actor = Auth::user();

            $properties = [
                'division_id' => $division->id,
                'code' => $division->code,
                'name' => $division->name,
                'description' => $division->description,
                'status_before' => $before->status ?? null,
                'status_after' => $division->status ?? null,
            ];

            $activity = activity('divisions')
                ->performedOn($division instanceof Division ? $division : null)
                ->withProperties($properties);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('deactivated');
        }
        return (bool) $division;
    }

    public function updateDivisionStatus($id, $status)
    {
        if (!in_array($status, ['Aktif', 'Non Aktif'], true)) {
            return null;
        }

        $before = $this->getDivisionById($id);
        $division = $this->repository->updateDivisionStatus($id, $status);
        if ($division) {
            $this->clearCaches($id, $division->code, $division->name, $before->status ?? null);
            $this->clearCaches($id, $division->code, $division->name, $division->status ?? null);

            $actor = Auth::user();

            $activity = activity('divisions')
                ->performedOn($division instanceof Division ? $division : null)
                ->withProperties([
                    'division_id' => $division->id,
                    'code' => $division->code,
                    'name' => $division->name,
                    'status_before' => $before->status ?? null,
                    'status_after' => $division->status ?? null,
                ]);

            if ($actor) {
                $activity->causedBy($actor);
            }

            $activity->log('status_changed');
        }

        return $division;
    }

    public function countUsersInDivision($divisionId)
    {
        if (!$this->getDivisionById($divisionId)) {
            return null;
        }

        return Cache::remember(self::CACHE_COUNT_PREFIX.$divisionId, self::CACHE_DURATION, fn () => $this->repository->countUsersInDivision($divisionId));
    }

    protected function clearCaches($id = null, $code = null, $name = null, $status = null): void
    {
        Cache::forget(self::CACHE_ALL);
        Cache::forget(self::CACHE_STATUS_PREFIX.'Aktif');
        Cache::forget(self::CACHE_STATUS_PREFIX.'Non Aktif');
        if ($status) {
            Cache::forget(self::CACHE_STATUS_PREFIX.$status);
        }
        if ($id) {
            Cache::forget(self::CACHE_ID_PREFIX.$id);
            Cache::forget(self::CACHE_COUNT_PREFIX.$id);
        }
        if ($code) {
            Cache::forget(self::CACHE_CODE_PREFIX.$code);
        }
        if ($name) {
            Cache::forget(self::CACHE_NAME_PREFIX.$name);
        }
    }

    protected function generateNextNumericCode(): string
    {
        $max = 0;
        $codes = Division::query()->get(['code']);
        foreach ($codes as $row) {
            $code = (string) ($row->code ?? '');
            if (!preg_match('/^\d+$/', $code)) {
                continue;
            }
            $val = (int) $code;
            if ($val > $max) {
                $max = $val;
            }
        }
        return $this->formatNumericCode($max + 1);
    }

    protected function incrementNumericCode(?string $current): string
    {
        $current = (string) ($current ?? '');
        $n = preg_match('/^\d+$/', $current) ? (int) $current : 0;
        return $this->formatNumericCode($n + 1);
    }

    protected function formatNumericCode(int $n): string
    {
        if ($n < 1) {
            $n = 1;
        }
        // Pad to 2 digits minimum: 1 -> 01, 10 -> 10
        return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }
}

