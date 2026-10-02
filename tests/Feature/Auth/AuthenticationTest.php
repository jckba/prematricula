<?php

namespace Tests\Feature\Auth;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'correo_institucional' => 'estudiante@test.edu.pe',
            'password' => 'password',
            'rol' => UserRole::ESTUDIANTE,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $response = $this->post('/login', [
            'correo_institucional' => 'estudiante@test.edu.pe',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::create([
            'correo_institucional' => 'estudiante@test.edu.pe',
            'password' => 'password',
            'rol' => UserRole::ESTUDIANTE,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $response = $this->post('/login', [
            'correo_institucional' => 'estudiante@test.edu.pe',
            'password' => 'incorrecta',
        ]);

        $response->assertSessionHasErrors(
            'correo_institucional'
        );

        $this->assertGuest();
    }

    public function test_non_existing_user_cannot_login(): void
    {
        $response = $this->post('/login', [
            'correo_institucional' => 'noexiste@test.edu.pe',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(
            'correo_institucional'
        );

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::create([
            'correo_institucional' => 'inactivo@test.edu.pe',
            'password' => 'password',
            'rol' => UserRole::ESTUDIANTE,
            'estado' => RecordStatus::INACTIVO,
        ]);

        $this->post('/login', [
            'correo_institucional' => 'inactivo@test.edu.pe',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::create([
            'correo_institucional' => 'student@test.edu.pe',
            'password' => 'password',
            'rol' => UserRole::ESTUDIANTE,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::create([
            'correo_institucional' => 'student@test.edu.pe',
            'password' => 'password',
            'rol' => UserRole::ESTUDIANTE,
            'estado' => RecordStatus::ACTIVO,
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/logout');

        $response->assertRedirect('/login');

        $this->assertGuest();
    }
}
