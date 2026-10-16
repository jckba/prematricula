<?php

namespace Tests\Feature;

use App\Enums\CoordinatorPosition;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\CurriculumSpreadsheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Shuchkin\SimpleXLSXGen;
use Tests\TestCase;

class BackendHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_frontend_handoff_flow_through_versioned_api(): void
    {
        $this->travelTo('2026-10-16 15:00:00 UTC');
        $director = User::factory()->coordinator()->create();
        $secretary = User::factory()->coordinator(CoordinatorPosition::SECRETARIA)->create();
        $student = Student::factory()->create(['codigo_universitario' => '000123']);
        $directorToken = $this->login($director->correo_institucional);
        $secretaryToken = $this->login($secretary->correo_institucional);
        $studentToken = $this->login($student->codigo_universitario);
        $this->asToken($directorToken);

        $this->postJson('/api/v1/coordinator/academic-periods', ['codigo' => 'HISTORY', 'fecha_inicio' => '2026-04-01',
            'fecha_fin' => '2026-08-01', 'estado' => 'CERRADO'])->assertCreated();
        $periodId = $this->postJson('/api/v1/coordinator/academic-periods', ['codigo' => 'TARGET', 'fecha_inicio' => '2027-04-01',
            'fecha_fin' => '2027-08-01', 'estado' => 'PREMATRICULA'])->assertCreated()->json('data.id');
        $root = '/api/v1/coordinator/academic-periods/'.$periodId;
        $this->postJson($root.'/windows', ['tipo' => 'ORDINARIA', 'fecha_inicio' => '2026-10-16 09:00:00',
            'fecha_fin' => '2026-10-16 18:00:00', 'motivo' => 'Ordinary handoff window'])->assertCreated();

        $file = $this->workbook('curriculum.xlsx', [CurriculumSpreadsheet::COLUMNS,
            ['BASE', 'Base course', 3, 1, 2, 0, 0, ''], ['ADV', 'Advanced course', 3, 3, 2, 1, 0, 'BASE']]);
        $preview = $this->post('/api/v1/coordinator/curriculum/validate', ['file' => $file])->assertOk()->json('data');
        $this->post('/api/v1/coordinator/curriculum/import', ['file' => $file, 'sha256' => $preview['sha256'],
            'catalog_fingerprint' => $preview['catalog_fingerprint']])->assertOk();
        $courseId = Course::query()->where('codigo', 'ADV')->sole()->id;
        $this->importResult('EN_CURSO', null);

        $this->asToken($studentToken);
        $requestId = $this->getJson('/api/v1/student/pre-enrollment')->assertCreated()->json('data.id');
        $detailId = $this->postJson('/api/v1/student/pre-enrollment/courses', ['course_id' => $courseId])
            ->assertCreated()->json('data.detail_id');
        $firstRevision = $this->postJson('/api/v1/student/pre-enrollment/submit')->assertOk()->assertJsonPath('data.estado', 'ENVIADA')
            ->assertJsonPath('data.validacion_academica.estado', 'PENDIENTE_RESULTADOS')->json('data.current_revision_id');
        $firstPdf = $this->get('/api/v1/student/pre-enrollments/'.$requestId.'/receipt')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF-', $firstPdf);
        file_put_contents(storage_path('framework/testing/handoff-receipt.pdf'), $firstPdf);

        $this->travel(1)->days();
        $this->asToken($studentToken);
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $secretaryToken = $this->login($secretary->correo_institucional);
        $studentToken = $this->login($student->codigo_universitario);
        $this->asToken($secretaryToken);
        $this->importResult('APROBADO', 15);
        $this->postJson($root.'/windows', ['tipo' => 'EXTRAORDINARIA', 'fecha_inicio' => '2026-10-17 09:00:00',
            'fecha_fin' => '2026-10-17 18:00:00', 'motivo' => 'Official results published'])->assertCreated();

        $this->asToken($studentToken);
        $this->postJson('/api/v1/student/pre-enrollments/'.$requestId.'/corrections')->assertOk();
        $this->patchJson('/api/v1/student/pre-enrollment/courses/'.$detailId.'/preference', ['preferencia_turno' => 'TARDE'])->assertOk();
        $this->postJson('/api/v1/student/pre-enrollment/submit')->assertOk()->assertJsonPath('data.revision_vigente.revision_number', 2);
        $this->get('/api/v1/student/pre-enrollments/'.$requestId.'/revisions/'.$firstRevision.'/receipt')->assertOk()->assertContent($firstPdf);
        $this->getJson('/api/v1/student/pre-enrollments/'.$requestId.'/revisions')->assertOk()->assertJsonCount(2, 'data');

        $this->asToken($secretaryToken);
        $this->getJson($root.'/demand')->assertOk()->assertJsonPath('data.cursos.0.registrada', 1)
            ->assertJsonPath('data.cursos.0.valida', 1)->assertJsonPath('data.cursos.0.tarde', 1);
        $this->getJson('/api/v1/coordinator/students/000123/academic?academic_period_id='.$periodId)
            ->assertOk()->assertJsonPath('data.prematricula.creditos_totales', 3);
        $teacherId = $this->postJson('/api/v1/coordinator/teachers', ['codigo_institucional' => 'DOC001',
            'nombres' => 'Demo', 'apellidos' => 'Teacher', 'max_horas_semanales' => 4])->assertCreated()->json('data.id');
        $assignmentId = $this->postJson('/api/v1/coordinator/teaching-assignments', ['academic_period_id' => $periodId,
            'teacher_id' => $teacherId, 'course_id' => $courseId, 'horas_teoria_asignadas' => 2,
            'horas_practica_asignadas' => 1])->assertCreated()->json('data.id');
        $this->getJson($root.'/teaching-load/courses/'.$courseId)->assertOk()
            ->assertJsonPath('data.carga.horas_teoria_pendientes', '0.00')->assertJsonPath('data.carga.demanda_valida', 1)
            ->assertJsonPath('data.carga.total_semanal', '3.00')->assertJsonMissingPath('data.carga.secciones');
        $this->patchJson($root, ['estado' => 'CERRADO'])->assertOk();
        $this->deleteJson('/api/v1/coordinator/teaching-assignments/'.$assignmentId)->assertConflict();

        $this->assertDatabaseCount('pre_enrollment_revisions', 2);
        $this->assertDatabaseCount('teaching_assignments', 1);
        $this->assertDatabaseCount('academic_history_imports', 2);
    }

    private function login(string $identifier): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/login', ['identifier' => $identifier, 'password' => 'password'])
            ->assertOk()->json('data.token');
    }

    private function asToken(string $token): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->withHeader('Accept', 'application/json');
    }

    private function importResult(string $state, ?int $grade): void
    {
        $file = $this->workbook('results.xlsx', [['codigo_universitario', 'codigo_curso', 'codigo_periodo', 'nota_final', 'estado'],
            [SimpleXLSXGen::raw('000123'), 'BASE', 'HISTORY', $grade, $state]]);
        $preview = $this->post('/api/v1/coordinator/academic-history/validate', ['file' => $file])->assertOk()->json('data');
        $this->post('/api/v1/coordinator/academic-history/import', ['file' => $file, 'sha256' => $preview['sha256'],
            'history_fingerprint' => $preview['history_fingerprint'], 'motivo' => 'Official result for handoff'])->assertOk();
    }

    /** @param list<list<mixed>> $rows */
    private function workbook(string $name, array $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, (string) SimpleXLSXGen::fromArray($rows));
    }
}
