<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TeachingAssignmentConcurrencyTest extends TestCase
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

    #[TestWith(['course'])]
    #[TestWith(['teacher'])]
    #[TestWith(['duplicate'])]
    public function test_simultaneous_assignments_cannot_exceed_capacity_or_create_duplicates(string $limit): void
    {
        $period = AcademicPeriod::factory()->create();
        $teacher = Teacher::factory()->create(['max_horas_semanales' => 2]);
        $course = Course::factory()->create(['horas_teoria' => 2]);
        $actor = User::factory()->coordinator()->create();
        $first = ['academic_period_id' => $period->id, 'teacher_id' => $teacher->id,
            'course_id' => $course->id, 'horas_teoria_asignadas' => '1.50'];
        $second = $first;
        if ($limit === 'course') {
            $second['teacher_id'] = Teacher::factory()->create()->id;
        } elseif ($limit === 'teacher') {
            $second['course_id'] = Course::factory()->create()->id;
        }

        $results = $this->race($actor->id, [$first, $second]);

        $successes = array_column($results, 'ok');
        sort($successes);
        $this->assertSame([false, true], $successes);
        $this->assertDatabaseCount('teaching_assignments', 1);
        $this->assertDatabaseCount('teaching_assignment_changes', 1);
        $this->assertDatabaseHas('teaching_assignments', ['horas_teoria_asignadas' => '1.50']);
    }

    /** @param list<array<string, mixed>> $payloads
     * @return list<array{ok: bool}>
     */
    private function race(int $actorId, array $payloads): array
    {
        $code = <<<'PHP'
require getcwd()."/vendor/autoload.php";
$app = require getcwd()."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
fwrite(STDOUT, "READY\n");
try {
    app(App\UseCases\Teaching\ManageTeachingAssignment::class)->save(
        json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR), App\Models\User::findOrFail((int) $argv[1])
    );
    echo json_encode(["ok" => true]);
} catch (Illuminate\Validation\ValidationException) {
    echo json_encode(["ok" => false]);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).": ".$exception->getMessage());
    exit(1);
}
PHP;
        $connection = config('database.connections.pgsql');
        $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
        $workers = [];
        DB::select('SELECT pg_advisory_lock(?)', [82640102]);
        try {
            foreach ($payloads as $payload) {
                $worker = new Process([PHP_BINARY, '-r', $code, (string) $actorId, json_encode($payload, JSON_THROW_ON_ERROR)],
                    base_path(), $environment, timeout: 30);
                $worker->start();
                $workers[] = $worker;
            }
            foreach ($workers as $worker) {
                $ready = str_contains($worker->getOutput(), 'READY') || $worker->waitUntil(fn (): bool => str_contains($worker->getOutput(), 'READY'));
                $this->assertTrue($ready, $worker->getErrorOutput());
            }
        } finally {
            DB::select('SELECT pg_advisory_unlock(?)', [82640102]);
        }
        $results = [];
        foreach ($workers as $worker) {
            $this->assertSame(0, $worker->wait(), 'El worker concurrente falló: '.$worker->getErrorOutput());
            $lines = explode("\n", trim($worker->getOutput()));
            $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    }
}
