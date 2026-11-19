<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índice relacional de los cursos contenidos en cada revisión.
 * Permite calcular la demanda por curso sin leer el JSON del snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('prematricula.revision_curso', function (Blueprint $table) {
            $table->bigInteger('id_revision');
            $table->bigInteger('id_curso_plan');
            $table->string('preferencia_turno', 30)->default('SIN_PREFERENCIA');
            $table->primary(['id_revision', 'id_curso_plan'], 'pre_rev_curso_pk');
            $table->index('id_curso_plan', 'pre_rev_curso_curso_idx');
            $table->foreign('id_revision', 'pre_rev_curso_rev_fk')
                ->references('id_revision')
                ->on('prematricula.revision_solicitud')
                ->restrictOnDelete();
            $table->foreign('id_curso_plan', 'pre_rev_curso_cp_fk')
                ->references('id_curso_plan')
                ->on('core.curso_plan')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE prematricula.revision_curso
            ADD CONSTRAINT pre_rev_curso_turno_ck
            CHECK (preferencia_turno IN ('MANANA', 'TARDE', 'SIN_PREFERENCIA'))
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('prematricula.revision_curso');
    }
};
