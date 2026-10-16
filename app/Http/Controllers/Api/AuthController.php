<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(LoginRequest $request, Timebox $timebox): JsonResponse
    {
        $credentials = $request->validated();

        $token = $timebox->call(fn (): ?NewAccessToken => DB::transaction(function () use ($credentials): ?NewAccessToken {
            $identifier = $credentials['identifier'] ?? $credentials['correo_institucional'];
            $emailUser = User::where('correo_institucional', $identifier)->first();
            $studentUser = Student::with('user')
                ->where('codigo_universitario', $identifier)
                ->first()?->user;

            $isAmbiguous = $emailUser !== null && $studentUser !== null
                && ! $emailUser->is($studentUser);
            $user = $emailUser ?? $studentUser;

            if ($user === null || $isAmbiguous) {
                return null;
            }

            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $matchesEmail = $user->correo_institucional === $identifier;
            $matchesStudentCode = $user->rol === UserRole::ESTUDIANTE
                && $user->student?->codigo_universitario === $identifier;

            if (! Hash::check($credentials['password'], $user->password) || ! $user->canAccessApi() ||
                (! $matchesEmail && ! $matchesStudentCode)) {
                return null;
            }

            return $user->createToken('frontend', ['*'], now()->startOfSecond()->addMinutes(config('sanctum.expiration')));
        }), 200000);

        if ($token === null) {
            return response()->json([
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        return response()->json([
            'message' => 'Autenticación correcta.',

            'data' => [
                'token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at->toISOString(),
                'user' => new UserResource($token->accessToken->tokenable),
            ],
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } else {
            auth('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}
