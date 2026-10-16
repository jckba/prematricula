<?php

namespace Tests\Feature\Api\Coordinator;

use App\Enums\AcademicHistoryStatus;
use App\Enums\CoordinatorPosition;
use App\Enums\PreEnrollmentStatus;
use App\Models\AcademicHistory;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\PreEnrollmentDetail;
use App\Models\PreEnrollmentRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UpdateCourseTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([CoordinatorPosition::DIRECTOR_ESCUELA])]
    #[TestWith([CoordinatorPosition::SECRETARIA])]
    public function test_both_coordinator_positions_can_update_unused_courses(CoordinatorPosition $position): void
    {
        $user = User::factory()->coordinator($position)->create();
        $course = Course::factory()->create();
        $first = Course::factory()->create(['codigo' => 'BASE1']);
        $second = Course::factory()->create(['codigo' => 'BASE2']);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/courses/'.$course->id, [
                'nombre' => 'Curso actualizado', 'creditos' => 5, 'ciclo' => 9,
                'horas_teoria' => '2.50', 'horas_practica' => 1, 'horas_laboratorio' => '0.25',
                'prerrequisitos' => ['BASE2', 'BASE1'],
            ])->assertOk()->assertJsonPath('data.id', $course->id)
            ->assertJsonPath('data.horas_teoria', '2.50')->assertJsonCount(2, 'data.prerrequisitos');

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'nombre' => 'Curso actualizado', 'creditos' => 5, 'ciclo' => 9]);
        $this->assertSame([$first->id, $second->id], $course->prerequisites()->orderBy('courses.id')->pluck('courses.id')->all());
    }

    #[TestWith(['nombre', 'Cambio prohibido'])]
    #[TestWith(['creditos', 6])]
    #[TestWith(['ciclo', 8])]
    #[TestWith(['horas_teoria', '4.25'])]
    #[TestWith(['horas_practica', '3.00'])]
    #[TestWith(['horas_laboratorio', '1.00'])]
    public function test_returns_422_for_academic_changes_to_courses_with_history(string $field, mixed $value): void
    {
        $user = User::factory()->coordinator()->create();
        $course = Course::factory()->create();
        $history = $this->createHistory($course);
        $originalValue = $course->getAttribute($field);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/courses/'.$course->id, [$field => $value])->assertUnprocessable()
            ->assertJsonPath('data.errors.0.column', $field);

        $this->assertSame($originalValue, $course->fresh()->getAttribute($field));
        $this->assertSame('15.50', $history->refresh()->nota_final);
        $this->assertSame($course->id, $history->course_id);
        $this->assertSame(AcademicHistoryStatus::APROBADO, $history->estado);
    }

    public function test_returns_422_for_prerequisite_changes_to_courses_with_history(): void
    {
        $user = User::factory()->coordinator()->create();
        $course = Course::factory()->create();
        $base = Course::factory()->create(['codigo' => 'BASE']);
        $course->prerequisites()->attach($base);
        $this->createHistory($course);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/courses/'.$course->id, ['prerrequisitos' => []])
            ->assertUnprocessable()->assertJsonPath('data.errors.0.column', 'prerrequisitos');

        $this->assertSame([$base->id], $course->prerequisites->modelKeys());
    }

    public function test_courses_selected_in_a_draft_are_also_protected(): void
    {
        $user = User::factory()->coordinator()->create();
        $course = Course::factory()->create();
        $student = Student::factory()->create();
        $period = AcademicPeriod::factory()->create();
        $preEnrollment = PreEnrollmentRequest::create([
            'student_id' => $student->id, 'academic_period_id' => $period->id, 'estado' => PreEnrollmentStatus::BORRADOR,
        ]);
        $detail = PreEnrollmentDetail::create(['pre_enrollment_request_id' => $preEnrollment->id, 'course_id' => $course->id]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/courses/'.$course->id, ['creditos' => 6])->assertUnprocessable();

        $this->assertSame(4, $course->fresh()->creditos);
        $this->assertModelExists($detail);
    }

    public function test_used_course_can_be_deactivated_without_changing_history(): void
    {
        $user = User::factory()->coordinator()->create();
        $course = Course::factory()->create();
        $history = $this->createHistory($course);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/courses/'.$course->id, ['estado' => 'INACTIVO'])
            ->assertOk()->assertJsonPath('data.estado', 'INACTIVO');

        $this->assertSame('INACTIVO', $course->fresh()->estado->value);
        $this->assertSame('15.50', $history->refresh()->nota_final);
        $this->assertModelExists($history);
    }

    public function test_repeating_identical_values_on_a_used_course_does_not_change_it(): void
    {
        $this->freezeTime();
        $user = User::factory()->coordinator()->create();
        $course = Course::factory()->create();
        $this->createHistory($course);
        $originalTimestamp = $course->updated_at->toISOString();
        $this->travel(1)->minutes();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/courses/'.$course->id, ['creditos' => 4])->assertOk();

        $this->assertSame($originalTimestamp, $course->fresh()->updated_at->toISOString());
    }

    public function test_returns_422_for_a_cycle_through_the_existing_catalog(): void
    {
        $user = User::factory()->coordinator()->create();
        $first = Course::factory()->create(['codigo' => 'FIRST']);
        $second = Course::factory()->create(['codigo' => 'SECOND']);
        $second->prerequisites()->attach($first);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/courses/'.$first->id, ['prerrequisitos' => ['SECOND']])
            ->assertUnprocessable();

        $this->assertDatabaseCount('course_prerequisites', 1);
        $this->assertSame([], $first->prerequisites->modelKeys());
    }

    #[TestWith(['MISSING'])]
    #[TestWith(['SELF'])]
    public function test_returns_422_for_missing_or_self_prerequisites(string $code): void
    {
        $user = User::factory()->coordinator()->create();
        $course = Course::factory()->create(['codigo' => 'SELF']);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/courses/'.$course->id, ['prerrequisitos' => [$code]])
            ->assertUnprocessable()->assertJsonPath('data.errors.0.column', 'prerrequisitos');

        $this->assertDatabaseCount('course_prerequisites', 0);
    }

    public function test_returns_422_for_attempted_code_renaming(): void
    {
        $user = User::factory()->coordinator()->create();
        $course = Course::factory()->create(['codigo' => 'ORIGINAL']);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/courses/'.$course->id, ['codigo' => 'NEW'])
            ->assertUnprocessable()->assertJsonValidationErrors('codigo');

        $this->assertSame('ORIGINAL', $course->fresh()->codigo);
    }

    public function test_returns_422_for_hour_values_with_more_than_two_decimal_places(): void
    {
        $user = User::factory()->coordinator()->create();
        $course = Course::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/courses/'.$course->id, ['horas_teoria' => '1.234'])
            ->assertUnprocessable()->assertJsonValidationErrors('horas_teoria');

        $this->assertSame('2.00', $course->fresh()->horas_teoria);
    }

    public function test_returns_403_for_students_attempting_to_modify_the_catalog(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create();

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/courses/'.$course->id, ['creditos' => 8])->assertForbidden();

        $this->assertSame(4, $course->fresh()->creditos);
    }

    private function createHistory(Course $course): AcademicHistory
    {
        return AcademicHistory::factory()->for($course)->create();
    }
}
