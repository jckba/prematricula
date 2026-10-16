<?php

namespace Tests\Feature\Api;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Shuchkin\SimpleXLSX;
use Tests\TestCase;

class CourseCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_catalog_lists_only_active_courses_with_weekly_hours_and_prerequisites(): void
    {
        $student = Student::factory()->create();
        $base = Course::factory()->create(['codigo' => 'BASE']);
        $course = Course::factory()->create(['codigo' => 'ADV', 'ciclo' => 9, 'horas_teoria' => '1.25']);
        $course->prerequisites()->attach($base);
        Course::factory()->inactive()->create();

        $this->withToken($student->user->createToken('test')->plainTextToken)->getJson('/api/v1/courses')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.codigo', 'ADV')
            ->assertJsonPath('data.1.horas_teoria', '1.25')
            ->assertJsonPath('data.1.prerrequisitos.0.codigo', 'BASE');
    }

    public function test_coordinator_catalog_can_include_inactive_courses(): void
    {
        $user = User::factory()->coordinator()->create();
        Course::factory()->create();
        Course::factory()->inactive()->create();

        $this->withToken($user->createToken('test')->plainTextToken)->getJson('/api/v1/courses')
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_student_can_consult_course_by_id(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create();

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/courses/'.$course->id)->assertOk()
            ->assertJsonPath('data.id', $course->id)->assertJsonPath('data.creditos', 4);
    }

    public function test_returns_404_for_student_consulting_an_inactive_course(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->inactive()->create();

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/courses/'.$course->id)->assertNotFound();
    }

    public function test_returns_401_for_guest_catalog_requests(): void
    {
        $this->getJson('/api/v1/courses')->assertUnauthorized();
    }

    public function test_returns_403_if_a_student_requests_an_inactive_catalog(): void
    {
        $student = Student::factory()->create();

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/courses?estado=INACTIVO')->assertForbidden();
    }

    public function test_catalog_supports_case_insensitive_search_cycle_filter_and_pagination(): void
    {
        $user = User::factory()->coordinator()->create();
        $course = Course::factory()->create(['nombre' => 'Álgebra y Matemática', 'ciclo' => 4]);
        Course::factory()->create(['nombre' => 'Matemática básica', 'ciclo' => 1]);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/courses?q=MATEM&ciclo=4&per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $course->id);
    }

    public function test_search_treats_wildcards_as_literal_characters(): void
    {
        $user = User::factory()->coordinator()->create();
        $course = Course::factory()->create(['codigo' => 'TEST_1']);
        Course::factory()->create(['codigo' => 'TESTA1']);

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/courses?q=TEST_1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $course->id);
    }

    public function test_cycle_does_not_restrict_student_eligibility(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create(['ciclo' => 99]);
        AcademicPeriod::create([
            'codigo' => '2027-I', 'fecha_inicio' => '2027-04-01', 'fecha_fin' => '2027-08-01',
            'fecha_inicio_prematricula' => now()->subDay(), 'fecha_fin_prematricula' => now()->addDay(),
            'estado' => AcademicPeriodStatus::PREMATRICULA,
        ]);

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->getJson('/api/v1/student/pre-enrollment/available-courses')->assertOk()
            ->assertJsonCount(1, 'data.cursos')->assertJsonPath('data.cursos.0.id', $course->id);
    }

    public function test_template_is_a_real_xlsx_with_exact_required_headers(): void
    {
        $user = User::factory()->coordinator()->create();

        $response = $this->withToken($user->createToken('test')->plainTextToken)
            ->get('/api/v1/coordinator/curriculum/template')->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Content-Disposition', 'attachment; filename="plantilla-malla.xlsx"');

        $workbook = SimpleXLSX::parseData($response->getContent());
        $this->assertSame([
            ['codigo', 'nombre', 'creditos', 'ciclo', 'horas_teoria', 'horas_practica', 'horas_laboratorio', 'prerrequisitos'],
        ], $workbook->rows());
    }

    public function test_export_preserves_code_text_fractional_hours_and_safe_literal_names(): void
    {
        $user = User::factory()->coordinator()->create();
        $base = Course::factory()->create(['codigo' => '0012']);
        $course = Course::factory()->create(['codigo' => 'ADV', 'nombre' => '=1+1 <b>Texto</b>', 'horas_teoria' => '1.25']);
        $course->prerequisites()->attach($base);

        $response = $this->withToken($user->createToken('test')->plainTextToken)
            ->get('/api/v1/coordinator/curriculum/export')->assertOk();

        $rows = SimpleXLSX::parseData($response->getContent())->rows();
        $this->assertSame('0012', $rows[1][0]);
        $this->assertSame('=1+1 <b>Texto</b>', $rows[2][1]);
        $this->assertSame(1.25, $rows[2][4]);
        $this->assertSame('0012', $rows[2][7]);
    }

    public function test_export_can_be_validated_and_imported_again_without_changes(): void
    {
        $user = User::factory()->coordinator()->create();
        $this->withToken($user->createToken('test')->plainTextToken);
        $base = Course::factory()->create(['codigo' => '0012']);
        $course = Course::factory()->inactive()->create(['codigo' => 'ADV']);
        $course->prerequisites()->attach($base);
        $export = $this->get('/api/v1/coordinator/curriculum/export')->assertOk();
        $file = UploadedFile::fake()->createWithContent('export.xlsx', $export->getContent());
        $preview = $this->postJson('/api/v1/coordinator/curriculum/validate', ['file' => $file])->assertOk()->json('data');

        $this->postJson('/api/v1/coordinator/curriculum/import', [
            'file' => $file, 'sha256' => $preview['sha256'], 'catalog_fingerprint' => $preview['catalog_fingerprint'],
        ])->assertOk()->assertJsonPath('data.summary', ['created' => 0, 'updated' => 0, 'unchanged' => 2]);

        $this->assertDatabaseCount('courses', 2);
        $this->assertSame('INACTIVO', $course->refresh()->estado->value);
        $this->assertSame([$base->id], $course->prerequisites->modelKeys());
    }
}
