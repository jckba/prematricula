<?php

namespace Tests\Feature\Auth;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PDOException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SensitiveDatabaseErrorsTest extends TestCase
{
    #[TestWith(['users'])]
    #[TestWith(['personal_access_tokens'])]
    public function test_database_errors_do_not_log_or_return_credentials_even_with_debug_enabled(string $table): void
    {
        config(['app.debug' => true]);
        $previous = new PDOException('secret-test-password-or-token');
        $previous->errorInfo = ['23505', 7, 'secret-test-password-or-token'];
        $exception = new QueryException('pgsql', 'insert into "'.$table.'" values (?)', ['secret-test-hash'], $previous);
        Route::get('/api/v1/testing/database-error', function () use ($exception): never {
            throw $exception;
        });
        Log::shouldReceive('error')->once()->with('Error de base de datos.', [
            'connection' => 'pgsql', 'sqlstate' => '23505',
        ]);

        $this->getJson('/api/v1/testing/database-error')->assertInternalServerError()
            ->assertExactJson(['message' => 'No se pudo completar la operación.']);
    }
}
