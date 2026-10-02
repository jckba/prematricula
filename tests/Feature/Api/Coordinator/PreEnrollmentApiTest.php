<?php

namespace Tests\Feature\Api\Coordinator;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreEnrollmentApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1';

    public function test_student_cannot_access_coordinator_routes(): void
    {
        $user = User::create([
            'correo_institucional' => 'student@test.edu.pe',
            'password' => 'password',
            'rol' => UserRole::ESTUDIANTE,
            'estado' => RecordStatus::ACTIVO,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            self::API.'/coordinator/pre-enrollments'
        );

        $response->assertForbidden();
    }
}
