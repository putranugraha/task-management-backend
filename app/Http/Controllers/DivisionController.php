<?php

namespace App\Http\Controllers;

use App\Http\Requests\DivisionStoreRequest;
use App\Http\Requests\DivisionUpdateRequest;
use App\Http\Resources\DivisionResource;
use App\Services\Contracts\DivisionServiceInterface;
use Illuminate\Http\Request;

class DivisionController extends Controller
{
    protected DivisionServiceInterface $service;

    public function __construct(DivisionServiceInterface $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $code = $request->query('code');
        $name = $request->query('name');
        $status = $request->query('status');
        $withUsersCount = $request->boolean('with_users_count');
        $withUsers = $request->boolean('with_users');

        if ($code) {
            $division = $this->service->getDivisionByCode($code);
            if (!$division) {
                return response()->json(['message' => 'Division tidak ditemukan'], 404);
            }
            if ($withUsers) {
                $division->load('users');
            }
            if ($withUsersCount) {
                $count = $this->service->countUsersInDivision($division->id);
                $division->setAttribute('users_count', $count ?? 0);
            }
            return DivisionResource::collection(collect([$division]));
        }

        if ($name) {
            $division = $this->service->getDivisionByName($name);
            if (!$division) {
                return response()->json(['message' => 'Division tidak ditemukan'], 404);
            }
            if ($withUsers) {
                $division->load('users');
            }
            if ($withUsersCount) {
                $count = $this->service->countUsersInDivision($division->id);
                $division->setAttribute('users_count', $count ?? 0);
            }
            return DivisionResource::collection(collect([$division]));
        }

        if ($status === null || $status === 'all') {
            $divisions = $this->service->getAllDivisions();
        } elseif ($status == 1 || $status === 'Aktif') {
            $divisions = $this->service->getActiveDivisions();
        } elseif ($status == 0 || $status === 'Non Aktif') {
            $divisions = $this->service->getInactiveDivisions();
        } else {
            return response()->json(['error' => 'Invalid status parameter'], 400);
        }
        if ($withUsers && method_exists($divisions, 'load')) {
            $divisions->load('users');
        }
        if ($withUsersCount && method_exists($divisions, 'map')) {
            $divisions = $divisions->map(function ($division) {
                $count = $this->service->countUsersInDivision($division->id);
                $division->setAttribute('users_count', $count ?? 0);
                return $division;
            });
        }

        return DivisionResource::collection($divisions);
    }

    public function store(DivisionStoreRequest $request)
    {
        $division = $this->service->createDivision($request->validated());
        if (!$division) {
            return response()->json(['message' => 'Gagal membuat division'], 400);
        }
        return new DivisionResource($division);
    }

    public function show(string $id, Request $request)
    {
        $division = $this->service->getDivisionById($id);
        if (!$division) {
            return response()->json(['message' => 'Division tidak ditemukan'], 404);
        }

        if ($request->boolean('with_users')) {
            $division->load('users');
        }

        if ($request->boolean('with_users_count')) {
            $count = $this->service->countUsersInDivision($division->id);
            $division->setAttribute('users_count', $count ?? 0);
        }

        return new DivisionResource($division);
    }

    public function update(DivisionUpdateRequest $request, string $id)
    {
        $division = $this->service->updateDivision($id, $request->validated());
        if (!$division) {
            return response()->json(['message' => 'Division tidak ditemukan atau data tidak valid'], 404);
        }
        return new DivisionResource($division);
    }

    public function destroy(string $id)
    {
        $deactivated = $this->service->deleteDivision($id);
        if (!$deactivated) {
            return response()->json(['message' => 'Division tidak ditemukan'], 404);
        }
        return response()->json(['message' => 'Division berhasil dinonaktifkan']);
    }

    public function updateStatus(string $id, Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|in:Aktif,Non Aktif',
        ]);

        $division = $this->service->updateDivisionStatus($id, $validated['status']);
        if (!$division) {
            return response()->json(['message' => 'Division tidak ditemukan'], 404);
        }

        return new DivisionResource($division);
    }

    public function activate(string $id)
    {
        $division = $this->service->updateDivisionStatus($id, 'Aktif');
        if (!$division) {
            return response()->json(['message' => 'Division tidak ditemukan'], 404);
        }

        return new DivisionResource($division);
    }

    public function usersCount(string $divisionId)
    {
        $count = $this->service->countUsersInDivision($divisionId);
        if ($count === null) {
            return response()->json(['message' => 'Division tidak ditemukan'], 404);
        }
        return response()->json([
            'division_id' => (int) $divisionId,
            'users_count' => (int) $count,
        ]);
    }
}

