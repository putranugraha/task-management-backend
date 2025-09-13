<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Login dengan Sanctum
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        // Regenerate session for SPA (Sanctum cookie-based)
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        if (($user->status ?? null) !== 'Aktif') {
            return response()->json(['message' => 'Akun Anda tidak aktif.'], 403);
        }

        // Issue a token for non-SPA clients (24 jam)
        $token = $user->createToken('auth-token', ['*'], Carbon::now()->addDay())->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'token' => $token,
            'message' => 'Login berhasil',
        ], 200);
    }

    // Registrasi user baru
    public function register(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // optional name; will default from email if missing
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $request->input('name', Str::before($request->email, '@')),
            'email' => $request->email,
            // Model cast akan otomatis hash
            'password' => $request->password,
            'status' => 'Aktif',
        ]);

        // Default role untuk pendaftaran
        $user->assignRole('Member');

        $token = $user->createToken('auth-token', ['*'], Carbon::now()->addDay())->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'token' => $token,
            'message' => 'Registrasi berhasil',
        ], 201);
    }

    // Logout: hapus token saat ini dan invalidate session jika ada
    public function logout(Request $request)
    {
        if ($request->user() && method_exists($request->user(), 'currentAccessToken')) {
            $request->user()->currentAccessToken()?->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logout berhasil']);
    }

    // Kirim email reset password
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

    // Reset password
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
                    'password' => Hash::make($password),
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
