<?php

namespace Tests\Feature;

use App\Enums\CoordinatorPosition;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TeachingAssignmentApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/coordinator/teaching-assignments';

    #[TestWith([CoordinatorPosition::DIRECTOR_ESCUELA])]
    #[TestWith([CoordinatorPosition::SECRETARIA])]
    public function test_creates_an_assignment_with_independent_hours_and_actor_audit(CoordinatorPosition $position): void
    {
        $actor = User::factory()->coordinator($position)->create();
        Sanctum::actingAs($actor);
        $data = $this->data(['horas_teoria' => 2, 'horas_practica' => 3, 'horas_laboratorio' => 1]);

        $response = $this->postJson(self::BASE, $data + ['horas_teoria_asignadas' => '1.25', 'horas_practica_asignadas' => '0.75',
            'horas_laboratorio_asignadas' => '0.50'])->assertCreated()
            ->assertJsonPath('data.total_semanal', '2.50')->assertJsonPath('data.created_by', $actor->id)
            ->assertJsonPath('data.updated_by', $actor->id)->assertJsonPath('data.horas_laboratorio_asignadas', '0.50');

        $this->assertDatabaseHas('teaching_assignments', $data + ['created_by' => $actor->id, 'horas_teoria_asignadas' => '1.25']);
        $this->getJson(self::BASE.'/'.$response->json('data.id').'/history')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.actor_id', $actor->id)->assertJsonPath('data.0.accion', 'CREADA')
            ->assertJsonPath('data.0.before', null)->assertJsonPath('data.0.after.horas_practica_asignadas', '0.75');
    }

    public function test_distributes_one_course_among_teachers_and_several_courses_to_one_teacher(): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $data = $this->data();
        $otherTeacher = Teacher::factory()->create();
        $otherCourse = Course::factory()->create();

        $this->postJson(self::BASE, $data + ['horas_teoria_asignadas' => 1])->assertCreated();
        $this->postJson(self::BASE, array_replace($data, ['teacher_id' => $otherTeacher->id, 'horas_teoria_asignadas' => 1]))->assertCreated();
        $this->postJson(self::BASE, array_replace($data, ['course_id' => $otherCourse->id, 'horas_practica_asignadas' => 1]))->assertCreated();
        $this->getJson(self::BASE.'?academic_period_id='.$data['academic_period_id'].'&course_id='.$data['course_id'])
            ->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson(self::BASE.'?teacher_id='.$data['teacher_id'])->assertOk()->assertJsonPath('meta.total', 2);
    }

    #[TestWith(['horas_teoria_asignadas', -1])]
    #[TestWith(['horas_practica_asignadas', '-0.50'])]
    #[TestWith(['horas_laboratorio_asignadas', '0.001'])]
    #[TestWith(['horas_teoria_asignadas', null])]
    #[TestWith(['horas_teoria_asignadas', 'abc'])]
    #[TestWith(['horas_practica_asignadas', 1000])]
    #[TestWith(['created_by', 1])]
    #[TestWith(['updated_by', 1])]
    public function test_invalid_hours_or_forged_authors_return_422(string $field, mixed $value): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $data = $this->data();

        $this->postJson(self::BASE, $data + [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('teaching_assignments', 0);
        $this->assertDatabaseCount('teaching_assignment_changes', 0);
    }

    public function test_all_zero_or_missing_hours_return_422(): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $data = $this->data();

        $this->postJson(self::BASE, $data)->assertUnprocessable()->assertJsonValidationErrors('horas');
        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    #[TestWith(['horas_teoria_asignadas', 'horas_teoria'])]
    #[TestWith(['horas_practica_asignadas', 'horas_practica'])]
    #[TestWith(['horas_laboratorio_asignadas', 'horas_laboratorio'])]
    public function test_each_hour_type_prevents_exceeding_course_capacity(string $assigned, string $available): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $data = $this->data([$available => '2.00']);
        $this->postJson(self::BASE, $data + [$assigned => '1.50'])->assertCreated();
        $other = array_replace($data, ['teacher_id' => Teacher::factory()->create()->id, $assigned => '0.75']);

        $this->postJson(self::BASE, $other)->assertUnprocessable()->assertJsonValidationErrors($assigned);

        $this->assertDatabaseCount('teaching_assignments', 1);
        $this->assertDatabaseCount('teaching_assignment_changes', 1);
    }

    public function test_decimal_boundaries_are_exact_and_updates_exclude_the_current_assignment(): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $data = $this->data(['horas_teoria' => '0.30']);
        $first = $this->postJson(self::BASE, $data + ['horas_teoria_asignadas' => '0.10'])->assertCreated()->json('data.id');
        $this->postJson(self::BASE, array_replace($data, ['teacher_id' => Teacher::factory()->create()->id, 'horas_teoria_asignadas' => '0.20']))->assertCreated();

        $this->patchJson(self::BASE.'/'.$first, ['horas_teoria_asignadas' => '0.10'])->assertOk();
        $this->patchJson(self::BASE.'/'.$first, ['horas_teoria_asignadas' => '0.11'])->assertUnprocessable();
        $this->assertDatabaseHas('teaching_assignments', ['id' => $first, 'horas_teoria_asignadas' => '0.10']);
    }

    public function test_teacher_limits_are_per_period_and_include_all_hour_types_and_courses(): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $teacher = Teacher::factory()->create(['max_horas_semanales' => '3.00']);
        $data = array_replace($this->data(), ['teacher_id' => $teacher->id]);
        $first = $this->postJson(self::BASE, $data + ['horas_teoria_asignadas' => 1, 'horas_practica_asignadas' => 1])
            ->assertCreated()->json('data.id');
        $this->postJson(self::BASE, array_replace($data, ['course_id' => Course::factory()->create()->id, 'horas_teoria_asignadas' => 1]))->assertCreated();
        $this->postJson(self::BASE, array_replace($data, ['academic_period_id' => AcademicPeriod::factory()->create()->id, 'horas_teoria_asignadas' => 2]))->assertCreated();

        $this->postJson(self::BASE, array_replace($data, ['course_id' => Course::factory()->create()->id, 'horas_practica_asignadas' => '0.01']))
            ->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->patchJson(self::BASE.'/'.$first, ['horas_practica_asignadas' => '1.01'])->assertUnprocessable();
        $this->patchJson(self::BASE.'/'.$first, ['horas_practica_asignadas' => '0.50'])->assertOk();
    }

    #[TestWith(['teacher'])]
    #[TestWith(['course'])]
    public function test_inactive_teachers_or_courses_return_422(string $target): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $data = $this->data();
        ($target === 'teacher' ? Teacher::findOrFail($data['teacher_id']) : Course::findOrFail($data['course_id']))->update(['estado' => 'INACTIVO']);

        $this->postJson(self::BASE, $data + ['horas_teoria_asignadas' => 1])->assertUnprocessable()->assertJsonValidationErrors('assignment');
        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    public function test_duplicates_return_422_and_assignment_identity_cannot_be_changed(): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $data = $this->data();
        $id = $this->postJson(self::BASE, $data + ['horas_teoria_asignadas' => 1])->assertCreated()->json('data.id');

        $this->postJson(self::BASE, $data + ['horas_teoria_asignadas' => 1])->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->patchJson(self::BASE.'/'.$id, ['course_id' => Course::factory()->create()->id])->assertUnprocessable()->assertJsonValidationErrors('course_id');
        $this->assertDatabaseCount('teaching_assignments', 1);
        $this->assertDatabaseCount('teaching_assignment_changes', 1);
    }

    public function test_updates_and_logical_deletes_preserve_authors_and_complete_history_and_allow_reassignment(): void
    {
        $creator = User::factory()->coordinator()->create();
        Sanctum::actingAs($creator);
        $data = $this->data();
        $id = $this->postJson(self::BASE, $data + ['horas_teoria_asignadas' => 1])->assertCreated()->json('data.id');
        $editor = User::factory()->coordinator()->create();
        Sanctum::actingAs($editor);

        $this->patchJson(self::BASE.'/'.$id, ['horas_practica_asignadas' => '0.50'])->assertOk()
            ->assertJsonPath('data.created_by', $creator->id)->assertJsonPath('data.updated_by', $editor->id);
        $this->deleteJson(self::BASE.'/'.$id)->assertOk();
        $this->assertSoftDeleted('teaching_assignments', ['id' => $id]);
        $this->getJson(self::BASE.'/'.$id)->assertNotFound();
        $this->getJson(self::BASE)->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson(self::BASE.'/'.$id.'/history')->assertOk()->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.1.before.horas_practica_asignadas', '0.00')
            ->assertJsonPath('data.1.after.horas_practica_asignadas', '0.50')->assertJsonPath('data.2.accion', 'ELIMINADA')
            ->assertJsonPath('data.2.actor_id', $editor->id);
        $this->postJson(self::BASE, $data + ['horas_teoria_asignadas' => 2])->assertCreated();
        $this->assertDatabaseCount('teaching_assignments', 2);
    }

    public function test_closed_periods_reject_creations_updates_and_deletes_but_allow_consultation(): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $period = AcademicPeriod::factory()->closed()->create();
        $assignment = TeachingAssignment::factory()->for($period)->create();

        $this->postJson(self::BASE, ['academic_period_id' => $period->id, 'teacher_id' => Teacher::factory()->create()->id,
            'course_id' => $assignment->course_id, 'horas_teoria_asignadas' => 1])->assertConflict();
        $this->patchJson(self::BASE.'/'.$assignment->id, ['horas_teoria_asignadas' => '0.50'])->assertConflict();
        $this->deleteJson(self::BASE.'/'.$assignment->id)->assertConflict();
        $this->getJson(self::BASE.'/'.$assignment->id)->assertOk();
        $this->assertNotSoftDeleted($assignment);
        $this->assertDatabaseCount('teaching_assignment_changes', 0);
    }

    public function test_referenced_periods_and_courses_preserve_their_academic_definition_even_after_logical_deletion(): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $period = AcademicPeriod::factory()->create(['fecha_inicio_prematricula' => null, 'fecha_fin_prematricula' => null]);
        $assignment = TeachingAssignment::factory()->for($period)->create();

        $this->deleteJson(self::BASE.'/'.$assignment->id)->assertOk();
        $this->deleteJson('/api/v1/coordinator/academic-periods/'.$period->id)->assertConflict();
        $this->patchJson('/api/v1/coordinator/academic-periods/'.$period->id, ['codigo' => 'CAMBIO'])->assertUnprocessable();
        $this->patchJson('/api/v1/coordinator/courses/'.$assignment->course_id, ['horas_teoria' => '0.50'])->assertUnprocessable();
        $this->assertSame('2.00', $assignment->course->fresh()->horas_teoria);
    }

    public function test_audit_failure_rolls_back_the_assignment_transaction(): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());
        $data = $this->data();
        DB::statement("ALTER TABLE teaching_assignment_changes ADD CONSTRAINT reject_test_audit CHECK (accion <> 'CREADA')");

        $this->postJson(self::BASE, $data + ['horas_teoria_asignadas' => 1])->assertInternalServerError();

        $this->assertDatabaseCount('teaching_assignments', 0);
        $this->assertDatabaseCount('teaching_assignment_changes', 0);
    }

    public function test_all_teaching_endpoints_reject_unauthenticated_students_and_inactive_coordinators(): void
    {
        $teacher = Teacher::factory()->create();
        $assignment = TeachingAssignment::factory()->for($teacher)->create();
        $root = '/api/v1/coordinator/academic-periods/'.$assignment->academic_period_id.'/teaching-load';
        $requests = [['GET', '/api/v1/coordinator/teachers'], ['POST', '/api/v1/coordinator/teachers'],
            ['GET', '/api/v1/coordinator/teachers/'.$teacher->id], ['PATCH', '/api/v1/coordinator/teachers/'.$teacher->id],
            ['DELETE', '/api/v1/coordinator/teachers/'.$teacher->id], ['GET', self::BASE], ['POST', self::BASE],
            ['GET', self::BASE.'/'.$assignment->id], ['PATCH', self::BASE.'/'.$assignment->id], ['DELETE', self::BASE.'/'.$assignment->id],
            ['GET', self::BASE.'/'.$assignment->id.'/history'], ['GET', $root], ['GET', $root.'/teachers'],
            ['GET', $root.'/teachers/'.$teacher->id], ['GET', $root.'/courses'], ['GET', $root.'/courses/'.$assignment->course_id]];
        foreach ($requests as [$method, $path]) {
            $this->json($method, $path)->assertUnauthorized();
        }
        foreach ([Student::factory()->create()->user, User::factory()->coordinator()->inactive()->create()] as $actor) {
            Sanctum::actingAs($actor);
            foreach ($requests as [$method, $path]) {
                $this->json($method, $path)->assertForbidden();
            }
        }
        $this->assertDatabaseCount('teaching_assignments', 1);
        $this->assertNotSoftDeleted($assignment);
    }

    /** @param array<string, mixed> $hours
     * @return array{academic_period_id: int, teacher_id: int, course_id: int}
     */
    private function data(array $hours = []): array
    {
        return ['academic_period_id' => AcademicPeriod::factory()->create()->id, 'teacher_id' => Teacher::factory()->create()->id,
            'course_id' => Course::factory()->create($hours)->id];
    }
}
