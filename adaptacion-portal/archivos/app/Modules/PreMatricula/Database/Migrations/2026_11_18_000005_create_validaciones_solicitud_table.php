<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resultado vigente de la validación académica de una solicitud.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('prematricula.validacion_solicitud', function (Blueprint $table) {
            $table->bigIncrements('id_validacion');
            $table->bigInteger('id_solicitud')->unique('pre_val_solicitud_uq');
            $table->bigInteger('id_revision')->nullable();
            $table->string('estado', 30);
            $table->jsonb('cursos');
            $table->timestampTz('evaluada_en');
            $table->index('id_revision', 'pre_val_rev_idx');
            $table->foreign('id_solicitud', 'pre_val_sol_fk')
                ->references('id_solicitud')
                ->on('prematricula.solicitud_prematricula')
                ->cascadeOnDelete();
            $table->foreign('id_revision', 'pre_val_rev_fk')
                ->references('id_revision')
                ->on('prematricula.revision_solicitud')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE prematricula.validacion_solicitud
            ADD CONSTRAINT pre_val_estado_ck
            CHECK (estado IN ('VALIDA', 'PENDIENTE_RESULTADOS', 'REQUIERE_AJUSTE'))
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('prematricula.validacion_solicitud');
    }
};
