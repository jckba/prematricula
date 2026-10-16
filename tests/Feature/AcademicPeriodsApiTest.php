<?php

namespace Tests\Feature;

use App\Enums\CoordinatorPosition;
use App\Models\AcademicHistory;
use App\Models\AcademicPeriod;
use App\Models\PreEnrollmentWindow;
use App\Models\Student;
use App\Models\User;
use App\Services\ActivePreEnrollmentWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AcademicPeriodsApiTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([CoordinatorPosition::DIRECTOR_ESCUELA])]
    #[TestWith([CoordinatorPosition::SECRETARIA])]
    public function test_coordinators_create_and_update_periods_with_audit_authors(CoordinatorPosition $position): void
    {
        $actor = User::factory()->coordinator($position)->create();
        $this->withToken($actor->createToken('test')->plainTextToken);
        $created = $this->postJson('/api/v1/coordinator/academic-periods', [
            'codigo' => '2027-I', 'fecha_inicio' => '2027-04-01', 'fecha_fin' => '2027-08-01', 'estado' => 'PLANIFICACION',
        ])->assertCreated()->assertJsonPath('data.created_by', $actor->id)->json('data');
        $other = User::factory()->coordinator()->create();
        Auth::forgetGuards();

        $this->withToken($other->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/academic-periods/'.$created['id'], ['estado' => 'PREMATRICULA'])
            ->assertOk()->assertJsonPath('data.updated_by', $other->id)->assertJsonPath('data.created_by', $actor->id);

        $this->assertDatabaseHas('academic_periods', ['id' => $created['id'], 'created_by' => $actor->id, 'updated_by' => $other->id]);
        $this->assertDatabaseCount('pre_enrollment_windows', 0);
    }

    public function test_returns_422_for_invalid_class_dates_and_duplicate_codes(): void
    {
        $this->coordinator();
        AcademicPeriod::factory()->create(['codigo' => 'DUP']);

        $this->postJson('/api/v1/coordinator/academic-periods', [
            'codigo' => 'DUP', 'fecha_inicio' => '2027-08-01', 'fecha_fin' => '2027-04-01', 'estado' => 'PLANIFICACION',
        ])->assertUnprocessable()->assertJsonValidationErrors(['codigo', 'fecha_fin']);
        $this->assertDatabaseCount('academic_periods', 1);
    }

    public function test_class_dates_are_independent_from_window_dates_and_use_institutional_timezone(): void
    {
        $this->travelTo('2026-10-16 15:00:00 UTC');
        config(['academic.timezone' => 'America/Lima']);
        $actor = $this->coordinator();
        $period = $this->period();

        $this->postJson('/api/v1/coordinator/academic-periods/'.$period->id.'/windows', [
            'tipo' => 'ORDINARIA', 'fecha_inicio' => '2026-10-16 09:00:00', 'fecha_fin' => '2026-10-16 11:00:00', 'motivo' => 'Proceso ordinario',
        ])->assertCreated()->assertJsonPath('data.fecha_inicio', '2026-10-16T14:00:00.000000Z')
            ->assertJsonPath('data.created_by', $actor->id);
        $this->assertSame($period->id, app(ActivePreEnrollmentWindow::class)->currentPeriod()->id);
        $this->assertSame('2027-04-01', $period->refresh()->fecha_inicio->toDateString());
    }

    public function test_closed_ordinary_window_blocks_and_extraordinary_window_reopens_for_all_students(): void
    {
        $this->travelTo('2026-10-16 15:00:00 UTC');
        $this->coordinator();
        $period = $this->period();
        PreEnrollmentWindow::factory()->for($period)->create(['fecha_inicio' => now()->subDays(3), 'fecha_fin' => now()->subDay()]);
        $legacy = $period->windows()->first()->getAttributes();

        $this->postJson('/api/v1/coordinator/academic-periods/'.$period->id.'/windows', [
            'tipo' => 'EXTRAORDINARIA', 'fecha_inicio' => '2026-10-16 09:00:00',
            'fecha_fin' => '2026-10-17 11:00:00', 'motivo' => 'Publicación de resultados oficiales',
        ])->assertCreated()->assertJsonPath('data.tipo', 'EXTRAORDINARIA');

        foreach ([Student::factory()->create(), Student::factory()->create()] as $student) {
            Auth::forgetGuards();
            $this->withToken($student->user->createToken('test')->plainTextToken)
                ->getJson('/api/v1/student/pre-enrollment')->assertCreated()->assertJsonPath('data.periodo.id', $period->id);
        }
        $this->assertSame($legacy, $period->windows()->orderBy('id')->first()->getAttributes());
        $this->assertDatabaseCount('pre_enrollment_windows', 2);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_returns_422_for_period_without_an_active_window(bool $closed): void
    {
        $this->freezeTime();
        $period = $this->period();
        if ($closed) {
            PreEnrollmentWindow::factory()->for($period)->create(['fecha_inicio' => now()->subDays(2), 'fecha_fin' => now()]);
        }
        $student = Student::factory()->create();

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/student/pre-enrollment')->assertUnprocessable();
        $this->assertDatabaseCount('pre_enrollment_requests', 0);
    }

    #[TestWith(['ORDINARIA'])]
    #[TestWith(['EXTRAORDINARIA'])]
    public function test_returns_422_for_overlapping_or_duplicate_windows(string $type): void
    {
        $this->travelTo('2026-10-16 15:00:00 UTC');
        $this->coordinator();
        $period = $this->period();
        PreEnrollmentWindow::factory()->for($period)->create(['fecha_inicio' => now()->subDay(), 'fecha_fin' => now()->addDay()]);

        $this->postJson('/api/v1/coordinator/academic-periods/'.$period->id.'/windows', [
            'tipo' => $type, 'fecha_inicio' => '2026-10-16 10:00:00', 'fecha_fin' => '2026-10-18 10:00:00', 'motivo' => 'Solapamiento',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('pre_enrollment_windows', 1);
    }

    public function test_extraordinary_window_requires_an_ordinary_window(): void
    {
        $this->coordinator();
        $period = $this->period();

        $this->postJson('/api/v1/coordinator/academic-periods/'.$period->id.'/windows', [
            'tipo' => 'EXTRAORDINARIA', 'fecha_inicio' => '2026-10-16 10:00:00',
            'fecha_fin' => '2026-10-17 10:00:00', 'motivo' => 'Sin ordinaria',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('pre_enrollment_windows', 0);
    }

    public function test_returns_422_for_reversed_window_dates_or_individual_reopening_payload(): void
    {
        $this->coordinator();
        $period = $this->period();

        $this->postJson('/api/v1/coordinator/academic-periods/'.$period->id.'/windows', [
            'tipo' => 'ORDINARIA', 'fecha_inicio' => '2026-10-17 10:00:00',
            'fecha_fin' => '2026-10-16 10:00:00', 'motivo' => 'Inválida', 'student_id' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['fecha_fin', 'student_id']);
        $this->assertDatabaseCount('pre_enrollment_windows', 0);
    }

    public function test_referenced_period_cannot_be_deleted_or_have_class_dates_rewritten(): void
    {
        $this->coordinator();
        $period = $this->period();
        AcademicHistory::factory()->for($period)->create();

        $this->deleteJson('/api/v1/coordinator/academic-periods/'.$period->id)->assertConflict();
        $this->patchJson('/api/v1/coordinator/academic-periods/'.$period->id, ['fecha_inicio' => '2027-03-01'])
            ->assertUnprocessable();
        $this->assertModelExists($period);
        $this->assertSame('2027-04-01', $period->fresh()->fecha_inicio->toDateString());
    }

    public function test_unused_period_can_be_deleted(): void
    {
        $this->coordinator();
        $period = $this->period();

        $this->deleteJson('/api/v1/coordinator/academic-periods/'.$period->id)->assertOk();

        $this->assertModelMissing($period);
    }

    public function test_students_can_read_but_not_manage_periods_or_windows(): void
    {
        $student = Student::factory()->create();
        $period = $this->period();
        $this->withToken($student->user->createToken('test')->plainTextToken);

        $this->getJson('/api/v1/academic-periods')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/academic-periods/'.$period->id)->assertOk();
        $this->postJson('/api/v1/coordinator/academic-periods', [])->assertForbidden();
        $this->patchJson('/api/v1/coordinator/academic-periods/'.$period->id, [])->assertForbidden();
        $this->postJson('/api/v1/coordinator/academic-periods/'.$period->id.'/windows', [])->assertForbidden();
        $this->deleteJson('/api/v1/coordinator/academic-periods/'.$period->id)->assertForbidden();
    }

    public function test_window_has_no_update_or_delete_api(): void
    {
        $this->coordinator();
        $period = $this->period();
        $window = PreEnrollmentWindow::factory()->for($period)->create();

        $this->patchJson('/api/v1/coordinator/academic-periods/'.$period->id.'/windows/'.$window->id, [])->assertNotFound();
        $this->deleteJson('/api/v1/coordinator/academic-periods/'.$period->id.'/windows/'.$window->id)->assertNotFound();
        $this->assertModelExists($window);
    }

    public function test_migration_preserves_legacy_dates_and_backfills_their_timezone_without_inventing_an_author(): void
    {
        config(['academic.legacy_timezone' => 'America/Lima']);
        $revisionMigration = require database_path('migrations/2026_10_16_165018_finalize_student_pre_enrollment_revisions.php');
        $revisionMigration->down();
        $migration = require database_path('migrations/2026_10_16_162542_add_academic_process_management.php');
        $migration->down();
        $id = DB::table('academic_periods')->insertGetId([
            'codigo' => 'LEGACY', 'fecha_inicio' => '2027-04-01', 'fecha_fin' => '2027-08-01',
            'fecha_inicio_prematricula' => '2026-10-16 09:00:00', 'fecha_fin_prematricula' => '2026-10-16 11:00:00', 'estado' => 'PREMATRICULA',
        ]);

        $migration->up();
        $revisionMigration->up();

        $window = PreEnrollmentWindow::where('academic_period_id', $id)->sole();
        $this->assertSame('2026-10-16T14:00:00.000000Z', $window->fecha_inicio->toISOString());
        $this->assertNull($window->created_by);
        $this->assertSame('MIGRACION', $window->origen);
        $this->assertDatabaseHas('academic_periods', ['id' => $id, 'fecha_inicio_prematricula' => '2026-10-16 09:00:00']);
    }

    private function coordinator(): User
    {
        $user = User::factory()->coordinator()->create();
        $this->withToken($user->createToken('test')->plainTextToken);

        return $user;
    }

    public function test_student_targets_the_next_known_period_not_a_later_period_with_an_open_window(): void
    {
        $this->freezeTime();
        AcademicPeriod::factory()->create(['codigo' => 'CURRENT', 'estado' => 'EN_CURSO', 'fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-07-31']);
        $next = AcademicPeriod::factory()->create(['codigo' => 'NEXT', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-12-31',
            'fecha_inicio_prematricula' => null, 'fecha_fin_prematricula' => null]);
        AcademicPeriod::factory()->create(['codigo' => 'LATER', 'fecha_inicio' => '2027-04-01', 'fecha_fin' => '2027-08-01']);
        $student = Student::factory()->create();
        $this->withToken($student->user->createToken('test')->plainTextToken);

        $this->getJson('/api/v1/student/pre-enrollment')->assertUnprocessable();
        PreEnrollmentWindow::factory()->for($next)->create();
        $this->getJson('/api/v1/student/pre-enrollment')->assertCreated()->assertJsonPath('data.periodo.id', $next->id);
    }

    public function test_dst_invalid_local_window_time_is_rejected(): void
    {
        config(['academic.timezone' => 'America/New_York']);
        $this->coordinator();
        $period = $this->period();

        $this->postJson('/api/v1/coordinator/academic-periods/'.$period->id.'/windows', [
            'tipo' => 'ORDINARIA', 'fecha_inicio' => '2027-03-14 02:30:00',
            'fecha_fin' => '2027-03-14 05:00:00', 'motivo' => 'Hora inexistente por cambio horario',
        ])->assertUnprocessable()->assertJsonValidationErrors('fecha_inicio');
        $this->assertDatabaseCount('pre_enrollment_windows', 0);
    }

    private function period(): AcademicPeriod
    {
        return AcademicPeriod::factory()->create(['fecha_inicio' => '2027-04-01', 'fecha_fin' => '2027-08-01',
            'fecha_inicio_prematricula' => null, 'fecha_fin_prematricula' => null]);
    }
}
