<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterStudentRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterStudentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $user = User::create([
                'correo_institucional' => $data['correo_institucional'],
                'password' => $data['codigo_universitario'],
                'rol' => UserRole::ESTUDIANTE,
                'estado' => RecordStatus::ACTIVO,
            ]);

            Student::create([
                'user_id' => $user->id,
                'codigo_universitario' => $data['codigo_universitario'],
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'],
                'estado' => RecordStatus::ACTIVO,
            ]);
        });

        return redirect()
            ->route('login')
            ->with('status', "Registro completado correctamente. Ya puedes iniciar sesión.\ncorreo: tu correo institucional\ncontraseña: tu código de matrícula");
    }
}
