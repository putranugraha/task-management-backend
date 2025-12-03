<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Normalisasi email agar pencarian tidak sensitif huruf besar/kecil dan spasi
        $normalizedEmail = strtolower(trim($request->email));

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (($user->status ?? null) !== 'Aktif' || ! $user->is_active) {
            return response()->json(['message' => 'Akun Anda tidak aktif.'], 403);
        }

        $user->forceFill([
            'last_login_at' => Carbon::now(),
        ])->save();

        $user->loadMissing('division', 'roles');

        $primaryRole = $user->roles->first()->name ?? null;
        $dashboardType = match ($primaryRole) {
            'Admin' => 'admin',
            'Manager' => 'manager',
            'Member' => 'member',
            default => 'member',
        };
        $homePath = match ($dashboardType) {
            'admin' => '/admin/dashboard',
            'manager' => '/manager/dashboard',
            'member' => '/member/dashboard',
            default => '/dashboard',
        };

        $token = $user->createToken('auth-token', ['*'], Carbon::now()->addDay())->plainTextToken;

        activity('auth')
            ->causedBy($user)
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log('login');

        return response()->json([
            'user' => new UserResource($user),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'primary_role' => $primaryRole,
            'dashboard_type' => $dashboardType,
            'home_path' => $homePath,
            'token' => $token,
            'message' => 'Login berhasil',
        ], 200);
    }

    public function register(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'name' => ['sometimes', 'string', 'max:255'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'division_id' => ['sometimes', 'nullable', 'exists:divisions,id'],
        ]);

        $user = User::create([
            'name' => $request->input('name', Str::before($request->email, '@')),
            'email' => strtolower(trim($request->email)),
            'password_hash' => $request->password,
            'job_title' => $request->input('job_title'),
            'is_active' => true,
            'status' => 'Aktif',
        ]);

        $user->assignRole('Member');
        $user->loadMissing('division', 'roles');

        $token = $user->createToken('auth-token', ['*'], Carbon::now()->addDay())->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'token' => $token,
            'message' => 'Registrasi berhasil',
        ], 201);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && method_exists($user, 'currentAccessToken')) {
            $user->currentAccessToken()?->delete();
        }

        if ($user) {
            activity('auth')
                ->causedBy($user)
                ->withProperties([
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log('logout');
        }

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logout berhasil']);
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['status' => __($status)], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password_hash' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['status' => __($status)], 200);
    }
}
