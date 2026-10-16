<?php

namespace Tests\Feature\Api\Coordinator;

use App\Enums\CoordinatorPosition;
use App\Models\AcademicHistory;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Shuchkin\SimpleXLSXGen;
use Tests\TestCase;

class CurriculumImportTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/coordinator/curriculum';

    public function test_preview_validates_forward_references_without_persisting(): void
    {
        $this->authenticateCoordinator();
        Course::factory()->create(['codigo' => 'EXISTING']);
        $file = $this->spreadsheet([
            ['ADV', 'Curso avanzado', 5, 8, 2.5, 1, 0, 'BASE;EXISTING'],
            ['BASE', 'Curso básico', 4, 1, 2, 2, 0, ''],
        ]);

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.summary.created', 2)
            ->assertJsonPath('data.errors', []);

        $this->assertDatabaseCount('courses', 1);
        $this->assertDatabaseCount('course_prerequisites', 0);
    }

    #[TestWith([CoordinatorPosition::DIRECTOR_ESCUELA])]
    #[TestWith([CoordinatorPosition::SECRETARIA])]
    public function test_imports_courses_and_multiple_prerequisites_independent_of_row_order(CoordinatorPosition $position): void
    {
        $this->authenticateCoordinator($position);
        $existing = Course::factory()->create(['codigo' => 'EXISTING']);
        $file = $this->spreadsheet([
            ['ADV', 'Curso avanzado', 5, 8, 2.5, 1, 0, 'BASE;EXISTING'],
            ['BASE', 'Curso básico', 4, 1, 2, 2, 0, ''],
        ]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $file])->assertOk()->json('data');

        $this->postJson(self::BASE.'/import', ['file' => $file, ...$this->confirmation($preview)])
            ->assertOk()->assertJsonPath('data.summary.created', 2);

        $this->assertDatabaseCount('courses', 3);
        $this->assertDatabaseCount('course_prerequisites', 2);
        $this->assertModelExists($existing);
        $advanced = Course::where('codigo', 'ADV')->sole();
        $this->assertSame('2.50', $advanced->horas_teoria);
        $this->assertSame(['BASE', 'EXISTING'], $advanced->prerequisites()->orderBy('codigo')->pluck('codigo')->all());
    }

    public function test_repeating_the_same_confirmation_is_a_noop_without_duplicates_or_timestamp_changes(): void
    {
        $this->freezeTime();
        $this->authenticateCoordinator();
        $file = $this->spreadsheet([
            ['ADV', 'Curso avanzado', 5, 8, 2, 1, 0, 'BASE'],
            ['BASE', 'Curso básico', 4, 1, 2, 2, 0, ''],
        ]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $file])->assertOk()->json('data');
        $payload = ['file' => $file, ...$this->confirmation($preview)];
        $this->postJson(self::BASE.'/import', $payload)->assertOk();
        $original = Course::where('codigo', 'ADV')->sole();
        $this->travel(1)->minutes();

        $this->postJson(self::BASE.'/import', $payload)->assertOk()
            ->assertJsonPath('data.summary', ['created' => 0, 'updated' => 0, 'unchanged' => 2]);

        $this->assertDatabaseCount('courses', 2);
        $this->assertDatabaseCount('course_prerequisites', 1);
        $this->assertSame($original->updated_at->toISOString(), $original->fresh()->updated_at->toISOString());
    }

    public function test_returns_422_with_row_and_column_for_duplicate_course_codes(): void
    {
        $this->authenticateCoordinator();
        $file = $this->spreadsheet([
            ['BASE', 'Primero', 4, 1, 2, 2, 0, ''],
            ['BASE', 'Repetido', 4, 1, 2, 2, 0, ''],
        ]);

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()
            ->assertJsonFragment(['row' => 3, 'column' => 'codigo', 'message' => 'Código duplicado; ya aparece en la fila 2.']);

        $this->assertDatabaseCount('courses', 0);
    }

    #[TestWith(['MISSING', 'El prerrequisito MISSING no existe en el catálogo ni en el archivo.'])]
    #[TestWith(['BASE', 'Un curso no puede ser su propio prerrequisito.'])]
    public function test_returns_422_for_missing_or_self_prerequisites(string $prerequisites, string $message): void
    {
        $this->authenticateCoordinator();
        $file = $this->spreadsheet([['BASE', 'Curso básico', 4, 1, 2, 2, 0, $prerequisites]]);

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()
            ->assertJsonFragment(['row' => 2, 'column' => 'prerrequisitos', 'message' => $message]);

        $this->assertDatabaseCount('courses', 0);
        $this->assertDatabaseCount('course_prerequisites', 0);
    }

    public function test_returns_422_for_a_cycle_in_the_uploaded_graph(): void
    {
        $this->authenticateCoordinator();
        $file = $this->spreadsheet([
            ['A', 'Curso A', 4, 1, 2, 0, 0, 'B'],
            ['B', 'Curso B', 4, 1, 2, 0, 0, 'C'],
            ['C', 'Curso C', 4, 1, 2, 0, 0, 'A'],
        ]);

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()
            ->assertJsonCount(3, 'data.errors')
            ->assertJsonFragment(['row' => 4, 'column' => 'prerrequisitos', 'message' => 'El curso participa en una dependencia circular.']);

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_returns_422_for_a_cycle_through_an_existing_course_outside_the_file(): void
    {
        $this->authenticateCoordinator();
        $first = Course::factory()->create(['codigo' => 'A']);
        $second = Course::factory()->create(['codigo' => 'B']);
        $second->prerequisites()->attach($first);
        $file = $this->spreadsheet([['A', $first->nombre, 4, 1, 2, 2, 0, 'B']]);

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()
            ->assertJsonFragment(['row' => 2, 'column' => 'prerrequisitos', 'message' => 'El curso participa en una dependencia circular.']);

        $this->assertDatabaseCount('course_prerequisites', 1);
    }

    public function test_database_failure_rolls_back_courses_already_inserted_in_the_import(): void
    {
        $this->authenticateCoordinator();
        $file = $this->spreadsheet([
            ['GOOD', 'Curso válido', 4, 1, 2, 0, 0, ''],
            ['FAIL', 'Fallo de base de datos', 4, 1, 2, 0, 0, 'GOOD'],
        ]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $file])->assertOk()->json('data');
        DB::statement("ALTER TABLE courses ADD CONSTRAINT curriculum_test_failure CHECK (codigo <> 'FAIL')");

        $this->postJson(self::BASE.'/import', ['file' => $file, ...$this->confirmation($preview)])
            ->assertInternalServerError();

        $this->assertDatabaseCount('courses', 0);
        $this->assertDatabaseCount('course_prerequisites', 0);
    }

    public function test_returns_409_if_the_catalog_changed_since_preview(): void
    {
        $this->authenticateCoordinator();
        $file = $this->spreadsheet([['NEW', 'Curso nuevo', 4, 1, 2, 2, 0, '']]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $file])->assertOk()->json('data');
        Course::factory()->create();

        $this->postJson(self::BASE.'/import', ['file' => $file, ...$this->confirmation($preview)])
            ->assertConflict();

        $this->assertDatabaseCount('courses', 1);
        $this->assertDatabaseMissing('courses', ['codigo' => 'NEW']);
    }

    public function test_returns_409_if_the_file_does_not_match_the_preview_hash(): void
    {
        $this->authenticateCoordinator();
        $file = $this->spreadsheet([['NEW', 'Curso nuevo', 4, 1, 2, 2, 0, '']]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $file])->assertOk()->json('data');
        $changedFile = $this->spreadsheet([['CHANGED', 'Otro curso', 4, 1, 2, 2, 0, '']]);

        $this->postJson(self::BASE.'/import', ['file' => $changedFile, ...$this->confirmation($preview)])
            ->assertConflict();

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_returns_422_for_a_header_only_file(): void
    {
        $this->authenticateCoordinator();

        $this->postJson(self::BASE.'/validate', ['file' => $this->spreadsheet([])])
            ->assertUnprocessable()->assertJsonFragment([
                'row' => 2, 'column' => 'codigo', 'message' => 'El archivo no contiene cursos.',
            ]);
    }

    public function test_returns_422_for_a_corrupt_xlsx_container(): void
    {
        $this->authenticateCoordinator();
        $originalFile = $this->spreadsheet([]);
        $content = file_get_contents($originalFile->getRealPath());
        $file = UploadedFile::fake()->createWithContent('corrupt.xlsx', substr($content, 0, -50));

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('courses', 0);
    }

    #[TestWith([0, 'codigo', 1234])]
    #[TestWith([2, 'creditos', 0])]
    #[TestWith([2, 'creditos', 3.5])]
    #[TestWith([3, 'ciclo', -1])]
    #[TestWith([4, 'horas_teoria', -0.5])]
    #[TestWith([5, 'horas_practica', 1.234])]
    #[TestWith([6, 'horas_laboratorio', 1000])]
    public function test_returns_422_with_the_source_row_and_column_for_invalid_values(int $index, string $column, mixed $value): void
    {
        $this->authenticateCoordinator();
        $row = ['BASE', 'Curso básico', 4, 1, 2, 2, 0, ''];
        $row[$index] = $value;

        $this->postJson(self::BASE.'/validate', ['file' => $this->spreadsheet([$row])])
            ->assertUnprocessable()->assertJsonPath('data.errors.0.row', 2)
            ->assertJsonPath('data.errors.0.column', $column);

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_returns_403_for_students_attempting_import_validation(): void
    {
        $student = Student::factory()->create();
        $this->withToken($student->user->createToken('test')->plainTextToken);

        $this->postJson(self::BASE.'/validate', ['file' => $this->spreadsheet([])])->assertForbidden();

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_returns_401_without_authentication(): void
    {
        $this->postJson(self::BASE.'/validate', ['file' => $this->spreadsheet([])])->assertUnauthorized();
    }

    public function test_import_updates_only_unused_courses_and_keeps_omitted_courses_and_edges(): void
    {
        $this->authenticateCoordinator();
        $base = Course::factory()->create(['codigo' => 'BASE']);
        $included = Course::factory()->create(['codigo' => 'INCLUDED']);
        $omitted = Course::factory()->create(['codigo' => 'OMITTED']);
        $included->prerequisites()->attach($base);
        $omitted->prerequisites()->attach($base);
        $file = $this->spreadsheet([
            ['BASE', 'Nombre actualizado', 5, 4, 1.5, 2, 0, ''],
            ['INCLUDED', $included->nombre, 4, 1, 2, 2, 0, ''],
            ['NEW', 'Curso nuevo', 4, 1, 2, 2, 0, 'BASE'],
        ]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $file])->assertOk()->json('data');

        $this->postJson(self::BASE.'/import', ['file' => $file, ...$this->confirmation($preview)])
            ->assertOk()->assertJsonPath('data.summary', ['created' => 1, 'updated' => 2, 'unchanged' => 0]);

        $this->assertSame('Nombre actualizado', $base->fresh()->nombre);
        $this->assertSame([], $included->prerequisites->modelKeys());
        $this->assertSame([$base->id], $omitted->prerequisites->modelKeys());
        $this->assertModelExists($omitted);
        $this->assertDatabaseCount('courses', 4);
    }

    public function test_import_cannot_overwrite_used_courses_or_apply_other_rows_partially(): void
    {
        $this->authenticateCoordinator();
        $used = Course::factory()->create(['codigo' => 'USED', 'nombre' => 'Original']);
        $history = AcademicHistory::factory()->for($used)->create();
        $file = $this->spreadsheet([
            ['NEW', 'Curso nuevo', 4, 1, 2, 2, 0, ''],
            ['USED', 'Cambio prohibido', 4, 1, 2, 2, 0, ''],
        ]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()->json('data');

        $this->postJson(self::BASE.'/import', ['file' => $file, ...$this->confirmation($preview)])
            ->assertUnprocessable()->assertJsonFragment([
                'row' => 3, 'column' => 'nombre',
                'message' => 'El curso ya está utilizado en historial o prematrícula; sus datos académicos y prerrequisitos son inmutables.',
            ]);

        $this->assertDatabaseCount('courses', 1);
        $this->assertSame('Original', $used->refresh()->nombre);
        $this->assertSame('15.50', $history->refresh()->nota_final);
    }

    public function test_unchanged_used_courses_can_be_imported_idempotently(): void
    {
        $this->authenticateCoordinator();
        $used = Course::factory()->create(['codigo' => 'USED', 'nombre' => 'Original']);
        AcademicHistory::factory()->for($used)->create();
        $file = $this->spreadsheet([['USED', 'Original', 4, 1, 2, 2, 0, '']]);
        $preview = $this->postJson(self::BASE.'/validate', ['file' => $file])->assertOk()->json('data');

        $this->postJson(self::BASE.'/import', ['file' => $file, ...$this->confirmation($preview)])
            ->assertOk()->assertJsonPath('data.summary.unchanged', 1);

        $this->assertDatabaseCount('courses', 1);
        $this->assertDatabaseCount('academic_histories', 1);
    }

    public function test_blank_rows_do_not_change_source_row_numbers_in_errors(): void
    {
        $this->authenticateCoordinator();
        $file = $this->spreadsheet([
            ['GOOD', 'Correcto', 4, 1, 2, 2, 0, ''],
            ['', '', '', '', '', '', '', ''],
            ['BAD', 'Incorrecto', 4, 1, -1, 2, 0, ''],
        ]);

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()
            ->assertJsonPath('data.errors.0.row', 4)->assertJsonPath('data.errors.0.column', 'horas_teoria');
    }

    public function test_formulas_are_rejected_even_with_valid_cached_values(): void
    {
        $this->authenticateCoordinator();
        $file = $this->spreadsheet([['FORMULA', 'Fórmula', '<f v="4">2+2</f>', 1, 2, 2, 0, '']]);

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()
            ->assertJsonFragment([
                'row' => 2, 'column' => 'creditos',
                'message' => 'No se permiten fórmulas ni celdas con errores; ingrese valores explícitos.',
            ]);
    }

    public function test_duplicate_prerequisites_are_not_silently_deduplicated(): void
    {
        $this->authenticateCoordinator();
        Course::factory()->create(['codigo' => 'BASE']);
        $file = $this->spreadsheet([['ADV', 'Avanzado', 4, 2, 2, 2, 0, 'BASE;BASE']]);

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()
            ->assertJsonFragment(['row' => 2, 'column' => 'prerrequisitos', 'message' => 'No se permiten prerrequisitos repetidos.']);

        $this->assertDatabaseCount('course_prerequisites', 0);
    }

    public function test_file_size_limit_is_enforced_before_parsing(): void
    {
        $this->authenticateCoordinator();
        $file = UploadedFile::fake()->create('oversized.xlsx', 5121, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_an_xlsx_renamed_as_csv_is_rejected(): void
    {
        $this->authenticateCoordinator();
        $source = $this->spreadsheet([]);
        $file = UploadedFile::fake()->createWithContent('renamed.csv', file_get_contents($source->getRealPath()));

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_a_zero_byte_file_is_rejected(): void
    {
        $this->authenticateCoordinator();
        $file = UploadedFile::fake()->createWithContent('empty.xlsx', '');

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_confirmation_requires_file_and_catalog_hashes(): void
    {
        $this->authenticateCoordinator();

        $this->postJson(self::BASE.'/import', ['file' => $this->spreadsheet([])])->assertUnprocessable()
            ->assertJsonValidationErrors(['sha256', 'catalog_fingerprint']);
    }

    public function test_columns_are_bound_by_header_name_even_when_reordered(): void
    {
        $this->authenticateCoordinator();
        $file = $this->spreadsheet([
            ['Nombre primero', 'CODE', 4, 1, 2, 2, 0, ''],
        ], ['nombre', 'codigo', 'creditos', 'ciclo', 'horas_teoria', 'horas_practica', 'horas_laboratorio', 'prerrequisitos']);

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertOk()
            ->assertJsonPath('data.changes.0.codigo', 'CODE');
    }

    public function test_duplicate_or_missing_headers_are_rejected(): void
    {
        $this->authenticateCoordinator();
        $file = $this->spreadsheet([], ['codigo', 'codigo', 'creditos', 'ciclo', 'horas_teoria', 'horas_practica', 'horas_laboratorio', 'prerrequisitos']);

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_extra_columns_are_rejected_without_silently_ignoring_them(): void
    {
        $this->authenticateCoordinator();
        $file = $this->spreadsheet([], ['codigo', 'nombre', 'creditos', 'ciclo', 'horas_teoria', 'horas_practica', 'horas_laboratorio', 'prerrequisitos', 'extra']);

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_workbooks_with_multiple_sheets_are_rejected(): void
    {
        $this->authenticateCoordinator();
        $workbook = SimpleXLSXGen::fromArray([['codigo']], 'Primera')->addSheet([['codigo']], 'Segunda');
        $file = UploadedFile::fake()->createWithContent('multiple.xlsx', (string) $workbook);

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_maximum_physical_row_limit_is_enforced(): void
    {
        $this->authenticateCoordinator();
        config(['curriculum.max_rows' => 1]);
        $file = $this->spreadsheet([
            ['A', 'Curso A', 4, 1, 2, 2, 0, ''],
            ['B', 'Curso B', 4, 1, 2, 2, 0, ''],
        ]);

        $this->postJson(self::BASE.'/validate', ['file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_expanded_archive_size_is_bounded_before_xml_parsing(): void
    {
        $this->authenticateCoordinator();
        config(['curriculum.max_uncompressed_bytes' => 16]);

        $this->postJson(self::BASE.'/validate', ['file' => $this->spreadsheet([])])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_archive_entry_count_is_bounded(): void
    {
        $this->authenticateCoordinator();
        config(['curriculum.max_archive_entries' => 1]);

        $this->postJson(self::BASE.'/validate', ['file' => $this->spreadsheet([])])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    #[TestWith(['GET', '/template'])]
    #[TestWith(['GET', '/export'])]
    #[TestWith(['POST', '/import'])]
    public function test_students_cannot_access_other_curriculum_management_endpoints(string $method, string $path): void
    {
        $student = Student::factory()->create();

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->json($method, self::BASE.$path)->assertForbidden();
    }

    /** @param array<string, mixed> $preview
     * @return array{sha256: string, catalog_fingerprint: string}
     */
    private function confirmation(array $preview): array
    {
        return ['sha256' => $preview['sha256'], 'catalog_fingerprint' => $preview['catalog_fingerprint']];
    }

    private function authenticateCoordinator(CoordinatorPosition $position = CoordinatorPosition::DIRECTOR_ESCUELA): void
    {
        $user = User::factory()->coordinator($position)->create();
        $this->withToken($user->createToken('test')->plainTextToken);
    }

    /** @param list<list<mixed>> $rows
     * @param  list<string>|null  $headers
     */
    private function spreadsheet(array $rows, ?array $headers = null): UploadedFile
    {
        $data = [$headers ?? ['codigo', 'nombre', 'creditos', 'ciclo', 'horas_teoria', 'horas_practica', 'horas_laboratorio', 'prerrequisitos']];

        foreach ($rows as $row) {
            foreach ([0, 1, 7] as $index) {
                if (is_string($row[$index])) {
                    $row[$index] = SimpleXLSXGen::raw($row[$index]);
                }
            }

            $data[] = $row;
        }

        return UploadedFile::fake()->createWithContent('malla.xlsx', (string) SimpleXLSXGen::fromArray($data));
    }
}
