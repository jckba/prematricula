<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStudentProfile
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if ($user === null || $user->student === null) {
            return response()->json([
                'message' => 'El usuario autenticado no tiene un perfil de estudiante asociado.',
            ], 403);
        }

        return $next($request);
    }
}
