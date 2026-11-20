<?php

use App\Services\TransactionLock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_locks', function (Blueprint $table): void {
            $table->string('name', 64)->primary();
        });

        DB::table('process_locks')->insert([
            ['name' => TransactionLock::CURRICULUM_CATALOG],
            ['name' => TransactionLock::ACADEMIC_PROCESS],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('process_locks');
    }
};
