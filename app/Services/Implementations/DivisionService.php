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

    public function createDivision(array $data)
    {
        $division = $this->repository->createDivision($data);
        if ($division) {
            $this->clearCaches($division->id, $division->code, $division->name);

            $actor = Auth::user();

            $properties = [
                'division_id' => $division->id,
                'code' => $division->code,
                'name' => $division->name,
                'description' => $division->description,
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
        $division = $this->getDivisionById($id);
        $result = $this->repository->deleteDivision($id);
        if ($result) {
            $this->clearCaches($id, $division->code ?? null, $division->name ?? null);

            if ($division) {
                $actor = Auth::user();

                $properties = [
                    'division_id' => $division->id,
                    'code' => $division->code,
                    'name' => $division->name,
                    'description' => $division->description,
                ];

                $activity = activity('divisions')
                    ->performedOn($division instanceof Division ? $division : null)
                    ->withProperties($properties);

                if ($actor) {
                    $activity->causedBy($actor);
                }

                $activity->log('deleted');
            }
        }
        return $result;
    }

    public function countUsersInDivision($divisionId)
    {
        if (!$this->getDivisionById($divisionId)) {
            return null;
        }

        return Cache::remember(self::CACHE_COUNT_PREFIX.$divisionId, self::CACHE_DURATION, fn () => $this->repository->countUsersInDivision($divisionId));
    }

    protected function clearCaches($id = null, $code = null, $name = null): void
    {
        Cache::forget(self::CACHE_ALL);
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
}

