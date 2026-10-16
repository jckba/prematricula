<?php

namespace Tests\Feature;

use App\Enums\CoordinatorPosition;
use App\Models\AcademicHistory;
use App\Models\AcademicHistoryChange;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Shuchkin\SimpleXLSX;
use Shuchkin\SimpleXLSXGen;
use Tests\TestCase;

class AcademicHistoryImportApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/coordinator/academic-history';

    #[TestWith([CoordinatorPosition::DIRECTOR_ESCUELA])]
    #[TestWith([CoordinatorPosition::SECRETARIA])]
    public function test_official_status_is_imported_without_inventing_a_passing_grade(CoordinatorPosition $position): void
    {
        $actor = $this->coordinator($position);
        [$student, $course, $period] = $this->references();
        $file = $this->file([['000123', 'CUR01', '2026-II', 1.25, 'APROBADO']]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $file])->assertOk()->json('data');
        $this->assertDatabaseCount('academic_histories', 0);
        $this->assertDatabaseCount('academic_history_imports', 0);

        $this->postJson(self::BASE.'/import', $this->confirmation($file, $preview))->assertOk()
            ->assertJsonPath('data.summary.created', 1);

        $history = AcademicHistory::sole();
        $this->assertSame('1.25', $history->nota_final);
        $this->assertSame('APROBADO', $history->estado->value);
        $this->assertSame($student->id, $history->student_id);
        $this->assertDatabaseHas('academic_history_imports', ['created_by' => $actor->id, 'motivo' => 'Acta oficial']);
        $this->assertDatabaseHas('academic_history_changes', ['academic_history_id' => $history->id]);
        $change = AcademicHistoryChange::sole();
        $this->assertNull($change->before);
        $this->assertSame('1.25', $change->after['nota_final']);
        $this->assertSame('APROBADO', $change->after['estado']);
    }

    public function test_repeating_an_import_is_idempotent_and_updates_are_audited(): void
    {
        $this->coordinator();
        $this->references();
        $pending = $this->file([['000123', 'CUR01', '2026-II', null, 'EN_CURSO']]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $pending])->assertOk()->json('data');
        $payload = $this->confirmation($pending, $preview);
        $this->postJson(self::BASE.'/import', $payload)->assertOk();
        $this->postJson(self::BASE.'/import', $payload)->assertOk()->assertJsonPath('data.summary.unchanged', 1);
        $this->assertDatabaseCount('academic_histories', 1);
        $this->assertDatabaseCount('academic_history_changes', 1);
        $final = $this->file([['000123', 'CUR01', '2026-II', 19.50, 'DESAPROBADO']]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $final])->assertOk()->json('data');

        $this->postJson(self::BASE.'/import', $this->confirmation($final, $preview))->assertOk()
            ->assertJsonPath('data.summary.updated', 1);

        $this->assertDatabaseCount('academic_histories', 1);
        $this->assertDatabaseCount('academic_history_imports', 3);
        $change = AcademicHistoryChange::orderByDesc('id')->first();
        $this->assertNull($change->before['nota_final']);
        $this->assertSame('EN_CURSO', $change->before['estado']);
        $this->assertSame('19.50', $change->after['nota_final']);
        $this->assertSame('DESAPROBADO', $change->after['estado']);
    }

    #[TestWith([null, 'APROBADO', 'nota_final'])]
    #[TestWith([null, 'DESAPROBADO', 'nota_final'])]
    #[TestWith([12, 'RETIRADO', 'nota_final'])]
    #[TestWith([-1, 'APROBADO', 'nota_final'])]
    #[TestWith([1000, 'APROBADO', 'nota_final'])]
    #[TestWith(['1.234', 'APROBADO', 'nota_final'])]
    #[TestWith(['texto', 'APROBADO', 'nota_final'])]
    #[TestWith([10, 'INVALIDO', 'estado'])]
    public function test_returns_422_with_row_and_column_for_inconsistent_grades_or_states(mixed $grade, string $status, string $column): void
    {
        $this->coordinator();
        $this->references();

        $this->postJson(self::BASE.'/validate', ['file' => $this->file([['000123', 'CUR01', '2026-II', $grade, $status]])])
            ->assertUnprocessable()->assertJsonPath('data.errors.0.row', 2)->assertJsonPath('data.errors.0.column', $column);

        $this->assertDatabaseCount('academic_histories', 0);
        $this->assertDatabaseCount('academic_history_imports', 0);
    }

    #[TestWith(['EN_CURSO'])]
    #[TestWith(['RETIRADO'])]
    public function test_null_grade_is_supported_for_in_progress_and_withdrawn_results(string $status): void
    {
        $this->coordinator();
        $this->references();
        $file = $this->file([['000123', 'CUR01', '2026-II', null, $status]]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $file])->assertOk()->json('data');

        $this->postJson(self::BASE.'/import', $this->confirmation($file, $preview))->assertOk();

        $this->assertDatabaseHas('academic_histories', ['nota_final' => null, 'estado' => $status]);
    }

    #[TestWith([0, 'codigo_universitario'])]
    #[TestWith([1, 'codigo_curso'])]
    #[TestWith([2, 'codigo_periodo'])]
    public function test_returns_422_for_missing_references(int $index, string $column): void
    {
        $this->coordinator();
        $this->references();
        $row = ['000123', 'CUR01', '2026-II', 15, 'APROBADO'];
        $row[$index] = 'NOEXISTE';

        $this->postJson(self::BASE.'/validate', ['file' => $this->file([$row])])->assertUnprocessable()
            ->assertJsonPath('data.errors.0.row', 2)->assertJsonPath('data.errors.0.column', $column);
        $this->assertDatabaseCount('academic_histories', 0);
    }

    public function test_duplicate_results_in_a_file_are_rejected_before_writing(): void
    {
        $this->coordinator();
        $this->references();
        $row = ['000123', 'CUR01', '2026-II', 15, 'APROBADO'];
        $file = $this->file([$row, $row]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()
            ->assertJsonPath('data.errors.0.row', 3)->json('data');

        $this->postJson(self::BASE.'/import', $this->confirmation($file, $preview))->assertUnprocessable();

        $this->assertDatabaseCount('academic_histories', 0);
        $this->assertDatabaseCount('academic_history_imports', 0);
    }

    public function test_stale_history_fingerprint_and_changed_file_return_409(): void
    {
        $this->coordinator();
        [$student, $course, $period] = $this->references();
        $file = $this->file([['000123', 'CUR01', '2026-II', 15, 'APROBADO']]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $file])->assertOk()->json('data');
        AcademicHistory::factory()->for($student)->for($course)->for($period)->create(['estado' => 'EN_CURSO', 'nota_final' => null]);

        $this->postJson(self::BASE.'/import', $this->confirmation($file, $preview))->assertConflict();
        $changed = $this->file([['000123', 'CUR01', '2026-II', 12, 'APROBADO']]);
        $this->postJson(self::BASE.'/import', $this->confirmation($changed, $preview))->assertConflict();

        $this->assertDatabaseHas('academic_histories', ['estado' => 'EN_CURSO', 'nota_final' => null]);
        $this->assertDatabaseCount('academic_history_imports', 0);
    }

    public function test_database_failure_rolls_back_results_and_audit_records_together(): void
    {
        $this->coordinator();
        $this->references();
        Course::factory()->create(['codigo' => 'FAIL']);
        $file = $this->file([
            ['000123', 'CUR01', '2026-II', 15, 'APROBADO'],
            ['000123', 'FAIL', '2026-II', 17, 'APROBADO'],
        ]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $file])->assertOk()->json('data');
        DB::statement('ALTER TABLE academic_histories ADD CONSTRAINT test_grade_failure CHECK (nota_final <> 17)');

        $this->postJson(self::BASE.'/import', $this->confirmation($file, $preview))->assertInternalServerError();

        $this->assertDatabaseCount('academic_histories', 0);
        $this->assertDatabaseCount('academic_history_imports', 0);
        $this->assertDatabaseCount('academic_history_changes', 0);
    }

    public function test_templates_are_real_xlsx_and_empty_or_corrupt_files_are_rejected(): void
    {
        $this->coordinator();
        $response = $this->get(self::BASE.'/template')->assertOk();
        $this->assertSame([['codigo_universitario', 'codigo_curso', 'codigo_periodo', 'nota_final', 'estado']],
            SimpleXLSX::parseData($response->getContent())->rows());

        $this->postJson(self::BASE.'/validate', ['file' => $this->file([])])->assertUnprocessable()
            ->assertJsonPath('data.errors.0.row', 2);
        $this->postJson(self::BASE.'/validate', ['file' => UploadedFile::fake()->createWithContent('bad.xlsx', 'broken')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_formulas_are_reported_at_their_source_cell(): void
    {
        $this->coordinator();
        $this->references();

        $this->postJson(self::BASE.'/validate', ['file' => $this->file([['000123', 'CUR01', '2026-II', '<f v="15">10+5</f>', 'APROBADO']])])
            ->assertUnprocessable()->assertJsonPath('data.errors.0.row', 2)->assertJsonPath('data.errors.0.column', 'nota_final');
        $this->assertDatabaseCount('academic_histories', 0);
    }

    public function test_confirmation_requires_a_reason_and_hashes(): void
    {
        $this->coordinator();

        $this->postJson(self::BASE.'/import', ['file' => $this->file([])])->assertUnprocessable()
            ->assertJsonValidationErrors(['sha256', 'history_fingerprint', 'motivo']);
    }

    public function test_students_cannot_import_or_view_import_audits_and_can_only_read_their_own_history(): void
    {
        [$student, $course, $period] = $this->references();
        $own = AcademicHistory::factory()->for($student)->for($course)->for($period)->create();
        $other = AcademicHistory::factory()->create();
        $this->withToken($student->user->createToken('test')->plainTextToken);

        $this->getJson('/api/v1/student/academic-history?student_id='.$other->student_id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->postJson(self::BASE.'/validate', [])->assertForbidden();
        $this->postJson(self::BASE.'/import', [])->assertForbidden();
        $this->getJson(self::BASE.'/imports')->assertForbidden();
        $this->getJson(self::BASE.'/template')->assertForbidden();
        $this->patchJson('/api/v1/student/academic-history/'.$own->id, ['estado' => 'APROBADO'])->assertNotFound();
    }

    public function test_coordinator_can_consult_the_persisted_import_audit(): void
    {
        $actor = $this->coordinator();
        $this->references();
        $file = $this->file([['000123', 'CUR01', '2026-II', 10, 'APROBADO']]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $file])->assertOk()->json('data');
        $importId = $this->postJson(self::BASE.'/import', $this->confirmation($file, $preview))->assertOk()->json('data.import_id');

        $this->getJson(self::BASE.'/imports/'.$importId)->assertOk()
            ->assertJsonPath('data.created_by', $actor->id)->assertJsonCount(1, 'data.changes')
            ->assertJsonPath('data.changes.0.after.estado', 'APROBADO');
    }

    public function test_guests_cannot_import_or_consult_history(): void
    {
        $this->postJson(self::BASE.'/validate', [])->assertUnauthorized();
        $this->getJson('/api/v1/student/academic-history')->assertUnauthorized();
    }

    /** @return array{Student, Course, AcademicPeriod} */
    private function references(): array
    {
        return [Student::factory()->create(['codigo_universitario' => '000123']),
            Course::factory()->create(['codigo' => 'CUR01']),
            AcademicPeriod::factory()->closed()->create(['codigo' => '2026-II', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-12-31'])];
    }

    private function coordinator(CoordinatorPosition $position = CoordinatorPosition::DIRECTOR_ESCUELA): User
    {
        $actor = User::factory()->coordinator($position)->create();
        $this->withToken($actor->createToken('test')->plainTextToken);

        return $actor;
    }

    /** @param list<list<mixed>> $rows */
    private function file(array $rows): UploadedFile
    {
        $data = [['codigo_universitario', 'codigo_curso', 'codigo_periodo', 'nota_final', 'estado']];
        foreach ($rows as $row) {
            foreach ([0, 1, 2, 4] as $index) {
                $row[$index] = SimpleXLSXGen::raw($row[$index]);
            }
            $data[] = $row;
        }

        return UploadedFile::fake()->createWithContent('resultados.xlsx', (string) SimpleXLSXGen::fromArray($data));
    }

    /** @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function confirmation(UploadedFile $file, array $report): array
    {
        return ['file' => $file, 'sha256' => $report['sha256'], 'history_fingerprint' => $report['history_fingerprint'], 'motivo' => 'Acta oficial'];
    }
}
