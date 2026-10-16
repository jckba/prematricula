<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

class AcademicProcessLock
{
    /** @template T
     * @param  Closure(): T  $operation
     * @return T
     */
    public function transaction(Closure $operation): mixed
    {
        return DB::transaction(function () use ($operation): mixed {
            DB::select('SELECT pg_advisory_xact_lock(?)', [82640102]);

            return $operation();
        }, 3);
    }
}
