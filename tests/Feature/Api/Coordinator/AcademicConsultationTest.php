<?php

namespace Tests\Feature\Api\Coordinator;

use App\Models\AcademicHistory;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\PreEnrollmentRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\RevalidatePreEnrollments;
use Database\Factories\PreEnrollmentRevisionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AcademicConsultationTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/coordinator/students';

    public function test_exact_code_search_preserves_leading_zeroes_and_returns_only_minimal_academic_identity(): void
    {
        Student::factory()->create(['codigo_universitario' => '00123', 'nombres' => 'Ana', 'apellidos' => 'Pérez']);
        Student::factory()->create(['codigo_universitario' => '123']);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $response = $this->getJson(self::BASE.'?codigo_universitario=00123')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.codigo_universitario', '00123');

        $this->assertSame(['codigo_universitario', 'nombres', 'apellidos', 'estado', 'creditos_maximos'], array_keys($response->json('data.0')));
    }

    public function test_partial_code_and_full_name_search_support_filters_sorting_and_pagination(): void
    {
        Student::factory()->create(['codigo_universitario' => '00101', 'nombres' => 'Ana María', 'apellidos' => 'Pérez', 'estado' => 'ACTIVO']);
        Student::factory()->create(['codigo_universitario' => '00102', 'nombres' => 'Ana María', 'apellidos' => 'Torres', 'estado' => 'ACTIVO']);
        Student::factory()->create(['codigo_universitario' => '00103', 'nombres' => 'Ana María', 'apellidos' => 'Díaz', 'estado' => 'INACTIVO']);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson(self::BASE.'?q=001&estado=ACTIVO&sort=apellidos&direction=desc&per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2)->assertJsonPath('data.0.apellidos', 'Torres');
        $this->getJson(self::BASE.'?q='.urlencode('maría pérez'))->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.codigo_universitario', '00101');
        $this->getJson(self::BASE.'?q=zzzz')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_search_treats_wildcards_as_literal_characters(): void
    {
        Student::factory()->create(['nombres' => 'Ana_1']);
        Student::factory()->create(['nombres' => 'AnaX1']);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson(self::BASE.'?q=Ana_1')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_academic_lookup_returns_relevant_history_current_selection_credits_and_validation_without_account_data(): void
    {
        $this->travelTo('2026-10-16 15:00:00 UTC');
        $student = Student::factory()->create(['codigo_universitario' => '0007']);
        $period = AcademicPeriod::factory()->create(['codigo' => '2027-I', 'fecha_inicio' => '2027-04-01']);
        $prior = AcademicPeriod::factory()->closed()->create(['fecha_inicio' => '2026-04-01']);
        $future = AcademicPeriod::factory()->create(['fecha_inicio' => '2028-04-01']);
        $base = Course::factory()->create(['codigo' => 'BASE']);
        $course = Course::factory()->create(['codigo' => 'CURSO', 'creditos' => 3]);
        AcademicHistory::factory()->for($student)->for($prior, 'academicPeriod')->for($base, 'course')->create(['estado' => 'APROBADO', 'nota_final' => 16]);
        AcademicHistory::factory()->for($student)->for($future, 'academicPeriod')->for($course, 'course')->create(['estado' => 'APROBADO']);
        $enrollment = PreEnrollmentRequest::factory()->for($student)->for($period)->create(['estado' => 'ENVIADA']);
        $attributes = PreEnrollmentRevisionFactory::new()->raw();
        $attributes['snapshot']['detalles'] = [['course_id' => $course->id, 'codigo' => 'CURSO', 'nombre' => $course->nombre,
            'creditos' => 3, 'preferencia_turno' => 'TARDE', 'private_note' => 'no exponer']];
        $revision = PreEnrollmentRevisionFactory::new()->for($enrollment)->create(['snapshot' => $attributes['snapshot']]);
        $revision->courses()->attach($course);
        $enrollment->update(['current_revision_id' => $revision->id]);
        app(RevalidatePreEnrollments::class)->evaluate($enrollment);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $response = $this->getJson(self::BASE.'/0007/academic?academic_period_id='.$period->id)->assertOk()
            ->assertJsonPath('data.estudiante.codigo_universitario', '0007')->assertJsonPath('data.estudiante.estado', 'ACTIVO')
            ->assertJsonPath('data.historial_academico.paginacion.total', 1)->assertJsonPath('data.historial_academico.data.0.nota_final', '16.00')
            ->assertJsonPath('data.prematricula.cursos.0.course_id', $course->id)->assertJsonPath('data.prematricula.creditos_totales', 3)
            ->assertJsonPath('data.prematricula.validacion_academica.estado', 'VALIDA')
            ->assertJsonPath('data.prematricula.origen_cursos', 'REVISION_REGISTRADA');

        $this->assertStringNotContainsString('correo_institucional', $response->getContent());
        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('pdf_base64', $response->getContent());
        $this->assertStringNotContainsString('private_note', $response->getContent());
    }

    public function test_unsubmitted_draft_is_identified_and_missing_request_is_null(): void
    {
        $student = Student::factory()->create();
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create();
        $enrollment = PreEnrollmentRequest::factory()->for($student)->for($period)->create();
        $enrollment->details()->create(['course_id' => $course->id, 'preferencia_turno' => 'MANANA']);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson(self::BASE.'/'.$student->codigo_universitario.'/academic?academic_period_id='.$period->id)
            ->assertOk()->assertJsonPath('data.prematricula.origen_cursos', 'BORRADOR')
            ->assertJsonPath('data.prematricula.revision_id', null)->assertJsonPath('data.prematricula.creditos_totales', 4);
        $otherPeriod = AcademicPeriod::factory()->create();
        $this->getJson(self::BASE.'/'.$student->codigo_universitario.'/academic?academic_period_id='.$otherPeriod->id)
            ->assertOk()->assertJsonPath('data.prematricula', null);
    }

    public function test_history_is_paginated_and_validation_for_an_old_revision_is_hidden(): void
    {
        $student = Student::factory()->create();
        $period = AcademicPeriod::factory()->create(['fecha_inicio' => '2027-04-01']);
        $prior = AcademicPeriod::factory()->closed()->create(['fecha_inicio' => '2026-04-01']);
        AcademicHistory::factory()->count(3)->for($student)->for($prior, 'academicPeriod')->create();
        $enrollment = PreEnrollmentRequest::factory()->for($student)->for($period)->create(['estado' => 'ENVIADA']);
        $old = PreEnrollmentRevisionFactory::new()->for($enrollment)->create();
        $current = PreEnrollmentRevisionFactory::new()->for($enrollment)->create(['revision_number' => 2]);
        $enrollment->update(['current_revision_id' => $current->id]);
        $enrollment->academicValidation()->create(['estado' => 'VALIDA', 'revision_id' => $old->id, 'cursos' => [], 'evaluated_at' => now()]);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson(self::BASE.'/'.$student->codigo_universitario.'/academic?academic_period_id='.$period->id.'&per_page=2&page=2')
            ->assertOk()->assertJsonCount(1, 'data.historial_academico.data')
            ->assertJsonPath('data.historial_academico.paginacion.total', 3)->assertJsonPath('data.prematricula.validacion_academica', null);
    }

    #[TestWith([null])]
    #[TestWith(['incompleto'])]
    public function test_incomplete_revision_details_return_an_empty_selection_without_failing(mixed $details): void
    {
        $student = Student::factory()->create();
        $period = AcademicPeriod::factory()->create();
        $enrollment = PreEnrollmentRequest::factory()->for($student)->for($period)->create(['estado' => 'ENVIADA']);
        $snapshot = PreEnrollmentRevisionFactory::new()->raw()['snapshot'];
        $snapshot['detalles'] = $details;
        $revision = PreEnrollmentRevisionFactory::new()->for($enrollment)->create(['snapshot' => $snapshot]);
        $enrollment->update(['current_revision_id' => $revision->id]);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson(self::BASE.'/'.$student->codigo_universitario.'/academic?academic_period_id='.$period->id)
            ->assertOk()->assertJsonCount(0, 'data.prematricula.cursos')->assertJsonPath('data.prematricula.creditos_totales', 0);
    }

    public function test_repeated_or_malformed_revision_entries_do_not_inflate_selection_or_credits(): void
    {
        $student = Student::factory()->create();
        $period = AcademicPeriod::factory()->create();
        $course = Course::factory()->create(['creditos' => 3]);
        $enrollment = PreEnrollmentRequest::factory()->for($student)->for($period)->create(['estado' => 'ENVIADA']);
        $detail = ['course_id' => $course->id, 'codigo' => $course->codigo, 'nombre' => $course->nombre,
            'creditos' => 3, 'preferencia_turno' => 'MANANA'];
        $snapshot = PreEnrollmentRevisionFactory::new()->raw()['snapshot'];
        $snapshot['detalles'] = [null, 'incompleto', $detail, $detail, ['creditos' => 20]];
        $revision = PreEnrollmentRevisionFactory::new()->for($enrollment)->create(['snapshot' => $snapshot]);
        $revision->courses()->attach($course);
        $enrollment->update(['current_revision_id' => $revision->id]);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson(self::BASE.'/'.$student->codigo_universitario.'/academic?academic_period_id='.$period->id)
            ->assertOk()->assertJsonCount(1, 'data.prematricula.cursos')
            ->assertJsonPath('data.prematricula.cursos.0.course_id', $course->id)
            ->assertJsonPath('data.prematricula.creditos_totales', 3);
    }

    public function test_academic_validation_exposes_only_academic_fields_from_the_current_revision(): void
    {
        $student = Student::factory()->create();
        $period = AcademicPeriod::factory()->create();
        $enrollment = PreEnrollmentRequest::factory()->for($student)->for($period)->create(['estado' => 'ENVIADA']);
        $revision = PreEnrollmentRevisionFactory::new()->for($enrollment)->create();
        $enrollment->update(['current_revision_id' => $revision->id]);
        $enrollment->academicValidation()->create(['estado' => 'PENDIENTE_RESULTADOS', 'revision_id' => $revision->id,
            'evaluated_at' => now(), 'cursos' => [['course_id' => 17, 'codigo' => 'MAT', 'estado' => 'PENDIENTE_RESULTADOS',
                'motivo' => null, 'private_note' => 'dato privado', 'prerrequisitos' => [['id' => 16, 'codigo' => 'BASE',
                    'estado_historial' => 'EN_CURSO', 'estado_validacion' => 'PENDIENTE_RESULTADOS', 'correo' => 'privado@example.org']]]]]);
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson(self::BASE.'/'.$student->codigo_universitario.'/academic?academic_period_id='.$period->id)
            ->assertOk()->assertJsonPath('data.prematricula.validacion_academica.cursos.0.codigo', 'MAT')
            ->assertJsonPath('data.prematricula.validacion_academica.cursos.0.prerrequisitos.0.codigo', 'BASE')
            ->assertJsonMissingPath('data.prematricula.validacion_academica.cursos.0.private_note')
            ->assertJsonMissingPath('data.prematricula.validacion_academica.cursos.0.prerrequisitos.0.correo');
    }

    #[TestWith(['sort=password'])]
    #[TestWith(['per_page=0'])]
    #[TestWith(['direction=invalid'])]
    #[TestWith(['estado=ENVIADA'])]
    #[TestWith(['q[]=name'])]
    public function test_invalid_filters_return_422(string $query): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson(self::BASE.'?'.$query)->assertUnprocessable();
    }

    public function test_academic_lookup_requires_a_period_and_exact_existing_code(): void
    {
        $student = Student::factory()->create(['codigo_universitario' => '00123']);
        $period = AcademicPeriod::factory()->create();
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson(self::BASE.'/00123/academic')->assertUnprocessable()->assertJsonValidationErrors('academic_period_id');
        $this->getJson(self::BASE.'/00123/academic?academic_period_id=99999')->assertUnprocessable();
        $this->getJson(self::BASE.'/123/academic?academic_period_id='.$period->id)->assertNotFound();
    }

    public function test_both_consultation_endpoints_return_401_without_authentication_and_403_for_students(): void
    {
        $student = Student::factory()->create();
        $period = AcademicPeriod::factory()->create();
        $detail = self::BASE.'/'.$student->codigo_universitario.'/academic?academic_period_id='.$period->id;
        $this->getJson(self::BASE)->assertUnauthorized();
        $this->getJson($detail)->assertUnauthorized();
        Sanctum::actingAs($student->user);

        $this->getJson(self::BASE)->assertForbidden();
        $this->getJson($detail)->assertForbidden();
    }
}
