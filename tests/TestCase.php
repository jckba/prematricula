<?php

namespace Tests;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PDOException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app->make('db')->connection();
        if (! $app->environment('testing') || ! in_array($connection->getDriverName(), ['mariadb', 'mysql'], true) ||
            $connection->getDatabaseName() !== 'prematricula_test') {
            throw new LogicException('Las pruebas solo pueden ejecutarse sobre MariaDB/MySQL prematricula_test con APP_ENV=testing.');
        }

        return $app;
    }

    /**
     * Makes every INSERT/UPDATE that writes $value into $table.$column fail like a
     * CHECK constraint violation would.
     *
     * Tests used to add a real CHECK with ALTER TABLE, but MariaDB/MySQL commit any
     * DDL implicitly, which ends the RefreshDatabase transaction. Failing the write
     * from beforeExecuting keeps the scenario (a database error in the middle of
     * the application's transaction) without DDL.
     */
    protected function failDatabaseWrite(string $table, string $column, string|int|float $value): void
    {
        DB::connection()->beforeExecuting(function (string $query, array $bindings, Connection $connection) use ($table, $column, $value): void {
            foreach ($this->writtenValues($query, $bindings, $table, $column) as $written) {
                if (is_numeric($written) && is_numeric($value) ? (float) $written === (float) $value : (string) $written === (string) $value) {
                    $previous = new PDOException("Simulated CHECK violation on {$table}.{$column}");
                    $previous->errorInfo = ['23000', 4025, $previous->getMessage()];

                    throw new QueryException($connection->getName(), $query, $bindings, $previous);
                }
            }
        });
    }

    /**
     * @param  array<int, mixed>  $bindings
     * @return list<mixed>
     */
    private function writtenValues(string $query, array $bindings, string $table, string $column): array
    {
        $bindings = array_values($bindings);
        $quoted = preg_quote($table, '/');

        if (preg_match("/^insert(?: ignore)? into `{$quoted}` \\(([^)]*)\\) values/i", $query, $match)) {
            $columns = array_map(fn (string $name): string => trim($name, ' `'), explode(',', $match[1]));
            $position = array_search($column, $columns, true);

            return $position === false ? [] : array_values(array_filter($bindings,
                fn (int $index): bool => $index % count($columns) === $position, ARRAY_FILTER_USE_KEY));
        }

        if (preg_match("/^update `{$quoted}` set (.+?)(?: where |$)/is", $query, $match)) {
            $assignments = array_map('trim', explode(',', $match[1]));
            $position = array_search("`{$column}` = ?", $assignments, true);

            return $position === false ? [] : [$bindings[$position]];
        }

        return [];
    }
}
