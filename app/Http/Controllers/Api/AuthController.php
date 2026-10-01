<?php

namespace App\Http\Controllers\Api;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where(
            'correo_institucional',
            $credentials['correo_institucional']
        )->first();

        if (
            $user === null ||
            ! Hash::check($credentials['password'], $user->password)
        ) {
            return response()->json([
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        if ($user->estado !== RecordStatus::ACTIVO) {
            return response()->json([
                'message' => 'El usuario se encuentra inactivo.',
            ], 403);
        }

        $token = $user
            ->createToken('frontend')
            ->plainTextToken;

        return response()->json([
            'message' => 'Autenticación correcta.',

            'data' => [
                'token' => $token,

                'user' => [
                    'id' => $user->id,
                    'correo_institucional' =>
                        $user->correo_institucional,
                    'rol' => $user->rol->value,
                ],
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'correo_institucional' =>
                    $user->correo_institucional,
                'rol' => $user->rol->value,
                'estado' => $user->estado->value,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()
            ->currentAccessToken()
            ?->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}
