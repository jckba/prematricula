<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Portable replacement for PostgreSQL's pg_advisory_xact_lock().
 *
 * Each named lock is a row in process_locks. Locking that row with
 * SELECT ... FOR UPDATE serializes every transaction that asks for the same
 * name; InnoDB (MariaDB/MySQL) and PostgreSQL release the row lock
 * automatically on COMMIT or ROLLBACK, exactly like the advisory lock did.
 */
class TransactionLock
{
    public const CURRICULUM_CATALOG = 'curriculum_catalog';

    public const ACADEMIC_PROCESS = 'academic_process';

    public function acquire(string $name): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('TransactionLock solo puede adquirirse dentro de una transacción.');
        }

        if ($this->lockRow($name)) {
            return;
        }

        // Missing row (e.g. a database imported without process_locks data):
        // create it and lock it. INSERT IGNORE waits for a concurrent insert of
        // the same key, so two first-time callers still end up serialized.
        DB::table('process_locks')->insertOrIgnore(['name' => $name]);

        if (! $this->lockRow($name)) {
            throw new LogicException("No se pudo adquirir el bloqueo de proceso {$name}.");
        }
    }

    /** @phpstan-impure Reads the database: the second call follows an INSERT. */
    private function lockRow(string $name): bool
    {
        return DB::table('process_locks')->where('name', $name)->lockForUpdate()->value('name') !== null;
    }
}
