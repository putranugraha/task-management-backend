<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermissionIsActive
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'User tidak terautentikasi.');
        }

        $allowedPermissions = $user->activePermissionNames();
        $requiredPermissions = collect(explode('|', $permission))
            ->map(fn ($item) => trim($item))
            ->filter();

        if ($requiredPermissions->isEmpty() || $requiredPermissions->intersect($allowedPermissions)->isEmpty()) {
            abort(403, 'Anda tidak memiliki permission yang aktif untuk mengakses resource ini.');
        }

        return $next($request);
    }
}
