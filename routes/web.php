<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [
        AuthController::class,
        'showLogin',
    ])->name('login');

    Route::post('/login', [
        AuthController::class,
        'login',
    ])->middleware('throttle:login')->name('login.store');

    Route::get('/register', [
        RegisteredUserController::class,
        'create',
    ])->name('register');

    Route::post('/register', [
        RegisteredUserController::class,
        'store',
    ])->middleware('throttle:5,1')->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::post('/logout', [
        AuthController::class,
        'logout',
    ])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Frontend (SPA ligera con token Sanctum)
|--------------------------------------------------------------------------
| Estas vistas no usan la sesión de Laravel: el JS valida el token y el rol
| contra /api/v1/me antes de mostrar el contenido.
*/
Route::view('/student', 'student.dashboard')->name('student.dashboard');
Route::view('/coordinator', 'coordinator.dashboard')->name('coordinator.dashboard');
