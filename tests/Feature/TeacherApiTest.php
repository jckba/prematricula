<?php

namespace Tests\Feature;

use App\Enums\CoordinatorPosition;
use App\Models\AcademicPeriod;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TeacherApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/coordinator/teachers';

    #[TestWith([CoordinatorPosition::DIRECTOR_ESCUELA])]
    #[TestWith([CoordinatorPosition::SECRETARIA])]
    public function test_coordinators_create_teachers_without_creating_access_accounts(CoordinatorPosition $position): void
    {
        Sanctum::actingAs(User::factory()->coordinator($position)->create());

        $this->postJson(self::BASE, ['codigo_institucional' => '0007', 'nombres' => 'Ana', 'apellidos' => 'Pérez',
            'correo_institucional' => 'ana@unitru.edu.pe', 'max_horas_semanales' => '20.50'])
            ->assertCreated()->assertJsonPath('data.codigo_institucional', '0007')
            ->assertJsonPath('data.max_horas_semanales', '20.50')->assertJsonPath('data.estado', 'ACTIVO')
            ->assertJsonMissingPath('data.user_id')->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('teachers', ['codigo_institucional' => '0007', 'nombres' => 'Ana']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_teachers_can_be_consulted_updated_and_deactivated_without_deleting_historical_assignments(): void
    {
        $teacher = Teacher::factory()->create(['codigo_institucional' => 'DOC01']);
        $period = AcademicPeriod::factory()->closed()->create();
        $assignment = TeachingAssignment::factory()->for($teacher)->for($period)->create();
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson(self::BASE.'/'.$teacher->id)->assertOk()->assertJsonPath('data.id', $teacher->id);
        $this->patchJson(self::BASE.'/'.$teacher->id, ['nombres' => 'Nuevo nombre', 'correo_institucional' => null])
            ->assertOk()->assertJsonPath('data.nombres', 'Nuevo nombre');
        $this->deleteJson(self::BASE.'/'.$teacher->id)->assertOk()->assertJsonPath('data.estado', 'INACTIVO');
        $this->getJson(self::BASE.'?estado=INACTIVO&q=DOC01&per_page=1')->assertOk()->assertJsonPath('meta.total', 1);

        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'estado' => 'INACTIVO', 'nombres' => 'Nuevo nombre']);
        $this->assertModelExists($assignment);
        $this->assertDatabaseCount('teachers', 1);
    }

    public function test_search_is_paginated_and_treats_wildcards_literally(): void
    {
        Teacher::factory()->create(['codigo_institucional' => 'DOC_1', 'nombres' => 'María', 'apellidos' => 'Torres']);
        Teacher::factory()->create(['codigo_institucional' => 'DOCX1']);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson(self::BASE.'?per_page=1')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson(self::BASE.'?q=DOC_1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE.'?q='.urlencode('maría torres'))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_duplicate_institutional_codes_return_422_without_adding_a_teacher(): void
    {
        Teacher::factory()->create(['codigo_institucional' => 'DUP']);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->postJson(self::BASE, ['codigo_institucional' => 'DUP', 'nombres' => 'Ana', 'apellidos' => 'Pérez'])
            ->assertUnprocessable()->assertJsonValidationErrors('codigo_institucional');

        $this->assertDatabaseCount('teachers', 1);
    }

    #[TestWith(['max_horas_semanales', -1])]
    #[TestWith(['max_horas_semanales', '1.001'])]
    #[TestWith(['max_horas_semanales', 100000])]
    #[TestWith(['correo_institucional', 'invalido'])]
    #[TestWith(['estado', 'RETIRADO'])]
    #[TestWith(['user_id', 1])]
    #[TestWith(['password', 'password'])]
    public function test_invalid_teacher_data_returns_422(string $field, mixed $value): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->postJson(self::BASE, ['codigo_institucional' => 'DOC01', 'nombres' => 'Ana', 'apellidos' => 'Pérez', $field => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('teachers', 0);
    }

    public function test_lowering_a_limit_checks_each_period_independently_and_can_be_removed(): void
    {
        $teacher = Teacher::factory()->create(['max_horas_semanales' => '4.00']);
        TeachingAssignment::factory()->for($teacher)->create(['horas_teoria_asignadas' => '2.00']);
        TeachingAssignment::factory()->for($teacher)->create(['horas_teoria_asignadas' => '2.00']);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->patchJson(self::BASE.'/'.$teacher->id, ['max_horas_semanales' => '1.50'])
            ->assertUnprocessable()->assertJsonValidationErrors('max_horas_semanales');
        $this->assertSame('4.00', $teacher->fresh()->max_horas_semanales);
        $this->patchJson(self::BASE.'/'.$teacher->id, ['max_horas_semanales' => '2.00'])->assertOk();
        $this->patchJson(self::BASE.'/'.$teacher->id, ['max_horas_semanales' => null])
            ->assertOk()->assertJsonPath('data.max_horas_semanales', null);
    }
}
