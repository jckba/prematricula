<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        string $role
    ): Response {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $requiredRole = UserRole::tryFrom($role);

        if (
            $requiredRole === null ||
            $user->rol !== $requiredRole
        ) {
            abort(403);
        }

        return $next($request);
    }
}
