<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\PreEnrollmentRequest;
use App\Models\PreEnrollmentWindow;
use App\Models\Student;
use App\UseCases\PreEnrollment\SubmitPreEnrollment;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use LogicException;
use ReflectionClass;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PreEnrollmentConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'prematricula_test') {
            throw new LogicException('La prueba concurrente exige la base aislada prematricula_test.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        try {
            $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
            RefreshDatabaseState::$migrated = false;
        } finally {
            parent::tearDown();
        }
    }

    public function test_simultaneous_draft_creation_keeps_one_logical_request(): void
    {
        $student = Student::factory()->create();
        $period = AcademicPeriod::factory()->create();

        $results = $this->race('draft', $student->id, $period->id);

        $this->assertSame([true, true], array_column($results, 'ok'));
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('pre_enrollment_requests', 1);
    }

    public function test_simultaneous_submission_registers_only_one_revision_and_receipt(): void
    {
        $request = PreEnrollmentRequest::factory()->create();
        $course = Course::factory()->create();
        $request->details()->create(['course_id' => $course->id]);

        $results = $this->race('submit', $request->id);

        $successes = array_column($results, 'ok');
        sort($successes);
        $this->assertSame([false, true], $successes);
        $this->assertDatabaseCount('pre_enrollment_revisions', 1);
        $this->assertSame(1, $request->fresh()->currentRevision->revision_number);
    }

    public function test_simultaneous_correction_start_is_idempotent_and_preserves_the_sent_revision(): void
    {
        $this->travel(-3)->days();
        $request = PreEnrollmentRequest::factory()->create();
        $course = Course::factory()->create();
        $request->details()->create(['course_id' => $course->id]);
        app(SubmitPreEnrollment::class)($request);
        $revisionId = $request->fresh()->current_revision_id;
        $this->travelBack();
        PreEnrollmentWindow::factory()->for($request->academicPeriod)->create(['tipo' => 'EXTRAORDINARIA',
            'fecha_inicio' => now()->subHour(), 'fecha_fin' => now()->addHour()]);

        $results = $this->race('correct', $request->id);

        $this->assertSame([true, true], array_column($results, 'ok'));
        $this->assertSame($revisionId, $request->fresh()->current_revision_id);
        $this->assertSame('BORRADOR', $request->fresh()->estado->value);
        $this->assertDatabaseCount('pre_enrollment_revisions', 1);
    }

    public function test_simultaneous_course_additions_respect_the_credit_limit(): void
    {
        $request = PreEnrollmentRequest::factory()->create();
        $request->student->update(['creditos_maximos' => 4]);
        $first = Course::factory()->create(['creditos' => 4]);
        $second = Course::factory()->create(['creditos' => 4]);

        $results = $this->race('add', $request->id, $first->id, $second->id);

        $successes = array_column($results, 'ok');
        sort($successes);
        $this->assertSame([false, true], $successes);
        $this->assertDatabaseCount('pre_enrollment_details', 1);
    }

    /** @return list<array{ok: bool, id?: int}> */
    private function race(string $action, int $id, int $firstExtra = 0, ?int $secondExtra = null): array
    {
        $code = <<<'PHP'
require $argv[2];
require getcwd()."/vendor/autoload.php";
$app = require getcwd()."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
fwrite(STDOUT, "READY\n");
try {
    $id = (int) $argv[3];
    $extra = (int) $argv[4];
    $result = match ($argv[1]) {
        "draft" => app(App\UseCases\PreEnrollment\GetOrCreateDraft::class)(App\Models\Student::findOrFail($id), App\Models\AcademicPeriod::findOrFail($extra)),
        "submit" => app(App\UseCases\PreEnrollment\SubmitPreEnrollment::class)(App\Models\PreEnrollmentRequest::findOrFail($id)),
        "correct" => app(App\UseCases\PreEnrollment\StartPreEnrollmentCorrection::class)(App\Models\PreEnrollmentRequest::findOrFail($id)),
        "add" => app(App\UseCases\PreEnrollment\AddCourse::class)(App\Models\PreEnrollmentRequest::findOrFail($id), App\Models\Course::findOrFail($extra)),
    };
    echo json_encode(["ok" => true, "id" => $result->id]);
} catch (DomainException) {
    echo json_encode(["ok" => false]);
} catch (Throwable) {
    echo json_encode(["error" => "Error inesperado en worker"]);
    exit(1);
}
PHP;
        $autoload = dirname((new ReflectionClass(Dompdf::class))->getFileName(), 4).'/autoload.php';
        $connection = config('database.connections.pgsql');
        $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password']];
        $workers = [];
        DB::select('SELECT pg_advisory_lock(?)', [82640102]);
        try {
            foreach ([$firstExtra, $secondExtra ?? $firstExtra] as $extra) {
                $worker = new Process([PHP_BINARY, '-r', $code, $action, $autoload, (string) $id, (string) $extra], base_path(), $environment, timeout: 20);
                $worker->start();
                $workers[] = $worker;
            }
            foreach ($workers as $worker) {
                $ready = str_contains($worker->getOutput(), 'READY') || $worker->waitUntil(fn (): bool => str_contains($worker->getOutput(), 'READY'));
                $this->assertTrue($ready);
            }
        } finally {
            DB::select('SELECT pg_advisory_unlock(?)', [82640102]);
        }
        $results = [];
        foreach ($workers as $worker) {
            $this->assertSame(0, $worker->wait(), 'El worker concurrente falló.');
            $lines = explode("\n", trim($worker->getOutput()));
            $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    }
}
