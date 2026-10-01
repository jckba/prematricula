<?php

namespace App\Http\Controllers;

use App\Enums\RecordStatus;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse {
        $credentials =  $request->validated();

        $authenticated = Auth::attempt([
            'correo_institucional' =>
            $credentials['correo_institucional'],
            'password' =>
            $credentials['password'],
            'estado' =>
            RecordStatus::ACTIVO->value,
        ]);

        if(! $authenticated) {
            return back()
                ->withErrors([
                    'correo_institucional' => 'Credenciales inválidas',
                ])->onlyInput('correo_institucional');
        }

        $request->session()->regenerate();

        return redirect()
            ->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login');
    }
}
