<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencia declarada de turno por curso (no es un horario ni una sección).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('prematricula.detalle_solicitud', function (Blueprint $table) {
            $table->string('preferencia_turno', 30)->default('SIN_PREFERENCIA');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE prematricula.detalle_solicitud
            ADD CONSTRAINT pre_det_turno_ck
            CHECK (preferencia_turno IN ('MANANA', 'TARDE', 'SIN_PREFERENCIA'))
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE prematricula.detalle_solicitud DROP CONSTRAINT IF EXISTS pre_det_turno_ck');

        Schema::table('prematricula.detalle_solicitud', function (Blueprint $table) {
            $table->dropColumn('preferencia_turno');
        });
    }
};
