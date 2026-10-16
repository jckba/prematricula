<?php

namespace Tests\Feature;

use App\Enums\CoordinatorPosition;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\PreEnrollmentRequest;
use App\Models\PreEnrollmentWindow;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PreEnrollmentReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_is_a_real_single_page_a4_pdf_with_institutional_and_historical_data(): void
    {
        config(['institution.university' => 'Universidad de Prueba', 'institution.school' => 'Escuela de Ingeniería']);
        [$student, $period, $request] = $this->submission(3);
        $response = $this->get('/api/v1/student/pre-enrollments/'.$request->id.'/receipt')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $binary = $response->getContent();

        $this->assertStringStartsWith('%PDF-', $binary);
        [$metadata, $text] = $this->inspectPdf($binary);
        $this->assertMatchesRegularExpression('/Pages:\\s+1\\b/', $metadata);
        $this->assertMatchesRegularExpression('/Page size:\\s+595\\.28 x 841\\.89 pts \\(A4\\)/', $metadata);
        foreach (['Universidad de Prueba', 'Escuela de Ingeniería', 'María', 'Núñez', '000123', '2027-I',
            'Revisión: 1', 'Créditos totales: 3', 'no constituye matrícula definitiva', 'CUR001', 'CUR003', 'MANANA'] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertSame('2026-10-16T15:00:00.000000Z', $request->currentRevision->submitted_at->toISOString());
        $this->assertStringContainsString('2026-10-16 10:00:00', $text);
    }

    #[TestWith([22])]
    #[TestWith([80])]
    public function test_all_courses_and_long_names_fit_on_one_page_without_omissions(int $count): void
    {
        [$student, $period, $request] = $this->submission($count, true);

        $binary = $this->get('/api/v1/student/pre-enrollments/'.$request->id.'/receipt')->assertOk()->getContent();
        [$metadata, $text] = $this->inspectPdf($binary);

        $this->assertMatchesRegularExpression('/Pages:\\s+1\\b/', $metadata);
        for ($number = 1; $number <= $count; $number++) {
            $this->assertStringContainsString(sprintf('CUR%03d', $number), $text);
        }
        $flattened = preg_replace('/\\s+/u', '', $text);
        foreach ($request->currentRevision->snapshot['detalles'] as $detail) {
            $this->assertStringContainsString(preg_replace('/\\s+/u', '', $detail['nombre']), $flattened);
        }
        $this->assertStringContainsString('Créditos totales: '.$count, $text);
    }

    public function test_historical_pdf_is_byte_identical_after_related_records_and_configuration_change(): void
    {
        [$student, $period, $request] = $this->submission(1);
        $revision = $request->currentRevision;
        $original = $this->get('/api/v1/student/pre-enrollments/'.$request->id.'/receipt')->assertOk()->getContent();
        $course = $request->details()->sole()->course;
        $course->update(['nombre' => 'Nombre actual distinto', 'creditos' => 2]);
        $student->update(['nombres' => 'Nombre nuevo', 'codigo_universitario' => 'CAMBIO']);
        $period->update(['codigo' => 'CAMBIO-PERIODO']);
        config(['institution.university' => 'Universidad actual distinta']);
        $this->travel(2)->days();

        $historical = $this->get('/api/v1/student/pre-enrollments/'.$request->id.'/revisions/'.$revision->id.'/receipt')
            ->assertOk()->getContent();

        $this->assertSame($original, $historical);
        $this->assertSame(hash('sha256', $original), $revision->fresh()->pdf_sha256);
        [, $text] = $this->inspectPdf($historical);
        $this->assertStringContainsString('000123', $text);
        $this->assertStringNotContainsString('Nombre actual distinto', $text);
        $this->assertStringNotContainsString('CAMBIO-PERIODO', $text);
    }

    public function test_correction_issues_another_receipt_and_preserves_the_previous_pdf(): void
    {
        [$student, $period, $request] = $this->submission(1);
        $first = $request->currentRevision;
        $firstPdf = $this->get('/api/v1/student/pre-enrollments/'.$request->id.'/receipt')->assertOk()->getContent();
        $this->travel(2)->days();
        PreEnrollmentWindow::factory()->for($period)->create(['tipo' => 'EXTRAORDINARIA', 'fecha_inicio' => now()->subHour(), 'fecha_fin' => now()->addDay()]);
        $this->postJson('/api/v1/student/pre-enrollments/'.$request->id.'/corrections')->assertOk();
        $this->patchJson('/api/v1/student/pre-enrollment/courses/'.$request->details()->sole()->id.'/preference', ['preferencia_turno' => 'TARDE'])->assertOk();
        $this->postJson('/api/v1/student/pre-enrollment/submit')->assertOk();
        $second = $request->fresh()->currentRevision;

        $current = $this->get('/api/v1/student/pre-enrollments/'.$request->id.'/receipt')->assertOk()->getContent();
        $old = $this->get('/api/v1/student/pre-enrollments/'.$request->id.'/revisions/'.$first->id.'/receipt')->assertOk()->getContent();

        $this->assertSame($firstPdf, $old);
        $this->assertNotSame($first->receipt_code, $second->receipt_code);
        $this->assertNotSame($old, $current);
        $this->assertMatchesRegularExpression('/Pages:\\s+1\\b/', $this->inspectPdf($current)[0]);
        $this->getJson('/api/v1/student/pre-enrollments/'.$request->id.'/revisions')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.revision_number', 2)->assertJsonMissingPath('data.0.pdf_base64');
    }

    #[TestWith([CoordinatorPosition::DIRECTOR_ESCUELA])]
    #[TestWith([CoordinatorPosition::SECRETARIA])]
    public function test_authorized_coordinators_can_download_receipts(CoordinatorPosition $position): void
    {
        [$student, $period, $request] = $this->submission(1);
        $this->asUser(User::factory()->coordinator($position)->create());

        $this->get('/api/v1/coordinator/pre-enrollments/'.$request->id.'/receipt')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/api/v1/coordinator/pre-enrollments/'.$request->id.'/revisions/'.$request->current_revision_id.'/receipt')->assertOk();
    }

    public function test_other_students_cannot_download_current_or_historical_receipts_or_read_revisions(): void
    {
        [$student, $period, $request] = $this->submission(1);
        $this->asUser(Student::factory()->create()->user);

        $this->getJson('/api/v1/student/pre-enrollments/'.$request->id.'/receipt')->assertNotFound();
        $this->getJson('/api/v1/student/pre-enrollments/'.$request->id.'/revisions/'.$request->current_revision_id.'/receipt')->assertNotFound();
        $this->getJson('/api/v1/student/pre-enrollments/'.$request->id.'/revisions')->assertNotFound();
        $this->getJson('/api/v1/student/pre-enrollments/'.$request->id)->assertNotFound();
    }

    public function test_nested_revision_must_belong_to_the_requested_logical_request(): void
    {
        [$student, $period, $request] = $this->submission(1);
        $other = PreEnrollmentRequest::factory()->create(['student_id' => $student->id]);

        $this->getJson('/api/v1/student/pre-enrollments/'.$other->id.'/revisions/'.$request->current_revision_id.'/receipt')->assertNotFound();
    }

    public function test_legacy_snapshot_without_original_pdf_is_not_fabricated_from_current_relations(): void
    {
        $student = Student::factory()->create();
        $request = PreEnrollmentRequest::factory()->create(['student_id' => $student->id, 'estado' => 'ENVIADA']);
        $legacy = $request->revisions()->create(['motivo' => 'ENVIO', 'revision_number' => 1, 'snapshot' => ['detalles' => []]]);
        $request->update(['current_revision_id' => $legacy->id]);
        $this->asUser($student->user);

        $this->getJson('/api/v1/student/pre-enrollments/'.$request->id.'/receipt')->assertConflict();
        $this->getJson('/api/v1/student/pre-enrollments/'.$request->id.'/revisions/'.$legacy->id.'/receipt')->assertConflict();

        $this->assertNull($legacy->fresh()->pdf_base64);
    }

    public function test_submitted_revisions_are_immutable_in_models_and_database(): void
    {
        [$student, $period, $request] = $this->submission(1);
        $revision = $request->currentRevision;
        try {
            $revision->update(['snapshot' => []]);
            $this->fail('El modelo permitió modificar la revisión.');
        } catch (LogicException) {
            $this->assertSame($revision->getRawOriginal('snapshot'), $revision->fresh()->getRawOriginal('snapshot'));
        }
        try {
            DB::transaction(fn () => DB::table('pre_enrollment_revisions')->where('id', $revision->id)->update(['snapshot' => '{}']));
            $this->fail('La base permitió modificar la revisión.');
        } catch (QueryException) {
            $this->assertSame($revision->pdf_sha256, $revision->fresh()->pdf_sha256);
        }
    }

    public function test_a_course_removed_from_the_draft_remains_protected_by_its_submitted_revision(): void
    {
        [$student, $period, $request] = $this->submission(1);
        $course = $request->details()->sole()->course;
        $this->travel(2)->days();
        PreEnrollmentWindow::factory()->for($period)->create(['tipo' => 'EXTRAORDINARIA', 'fecha_inicio' => now()->subHour(), 'fecha_fin' => now()->addDay()]);
        $this->postJson('/api/v1/student/pre-enrollments/'.$request->id.'/corrections')->assertOk();
        $this->deleteJson('/api/v1/student/pre-enrollment/courses/'.$request->details()->sole()->id)->assertOk();
        $this->asUser(User::factory()->coordinator()->create());

        $this->patchJson('/api/v1/coordinator/courses/'.$course->id, ['nombre' => 'Cambio prohibido'])->assertUnprocessable();

        $this->assertSame(1, $course->preEnrollmentRevisions()->count());
    }

    public function test_guest_cannot_download_receipts(): void
    {
        $this->getJson('/api/v1/student/pre-enrollments/1/receipt')->assertUnauthorized();
    }

    public function test_revision_numbers_and_current_revision_parent_are_enforced_by_database_constraints(): void
    {
        [$student, $period, $request] = $this->submission(1);
        try {
            DB::transaction(fn () => $request->revisions()->create(['motivo' => 'ENVIO', 'revision_number' => 1, 'snapshot' => []]));
            $this->fail('Se permitió repetir el número de revisión.');
        } catch (QueryException) {
            $this->assertDatabaseCount('pre_enrollment_revisions', 1);
        }
        $other = PreEnrollmentRequest::factory()->create();
        try {
            DB::transaction(fn () => $other->update(['current_revision_id' => $request->current_revision_id]));
            $this->fail('Se permitió apuntar a una revisión de otra solicitud.');
        } catch (QueryException) {
            $this->assertNull($other->fresh()->current_revision_id);
        }
    }

    /** @return array{Student, AcademicPeriod, PreEnrollmentRequest} */
    private function submission(int $count, bool $longNames = false): array
    {
        $this->travelTo('2026-10-16 15:00:00 UTC');
        $student = Student::factory()->create(['codigo_universitario' => '000123', 'nombres' => 'María', 'apellidos' => 'Núñez', 'creditos_maximos' => max(22, $count)]);
        $period = AcademicPeriod::factory()->create(['codigo' => '2027-I', 'fecha_inicio' => '2027-04-01', 'fecha_fin' => '2027-08-01']);
        $request = PreEnrollmentRequest::factory()->create(['student_id' => $student->id, 'academic_period_id' => $period->id]);
        for ($number = 1; $number <= $count; $number++) {
            $course = Course::factory()->create(['codigo' => sprintf('CUR%03d', $number), 'creditos' => 1,
                'nombre' => $longNames ? str_repeat('Ingeniería y análisis académico ', 4).'Final '.$number : 'Asignatura '.$number]);
            $request->details()->create(['course_id' => $course->id, 'preferencia_turno' => 'MANANA']);
        }
        $this->asUser($student->user);
        $this->postJson('/api/v1/student/pre-enrollment/submit')->assertOk();

        return [$student, $period, $request->refresh()->load('currentRevision')];
    }

    /** @return array{string, string} */
    private function inspectPdf(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'prematricula-receipt-');
        file_put_contents($path, $binary);
        try {
            $info = new Process(['pdfinfo', $path], env: ['LC_ALL' => 'C']);
            $text = new Process(['pdftotext', '-raw', '-enc', 'UTF-8', $path, '-']);

            return [$info->mustRun()->getOutput(), $text->mustRun()->getOutput()];
        } finally {
            unlink($path);
        }
    }

    private function asUser(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('test')->plainTextToken);
    }
}
