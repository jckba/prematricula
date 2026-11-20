<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

class AcademicProcessLock
{
    public function __construct(private readonly TransactionLock $lock) {}

    /** @template T
     * @param  Closure(): T  $operation
     * @return T
     */
    public function transaction(Closure $operation): mixed
    {
        return DB::transaction(function () use ($operation): mixed {
            $this->lock->acquire(TransactionLock::ACADEMIC_PROCESS);

            return $operation();
        }, 3);
    }
}
