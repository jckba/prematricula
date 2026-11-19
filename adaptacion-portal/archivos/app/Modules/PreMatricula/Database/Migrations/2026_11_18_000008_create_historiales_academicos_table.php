<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resultados oficiales importados que usa la prematrícula para validar prerrequisitos.
 * Las notas definitivas pertenecen a gestion_matriculas; como no se permiten FK
 * entre módulos, la prematrícula mantiene su propia copia importada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('prematricula.historial_academico', function (Blueprint $table) {
            $table->bigIncrements('id_historial');
            $table->bigInteger('id_estudiante');
            $table->bigInteger('id_curso_plan');
            $table->bigInteger('id_periodo');
            $table->decimal('nota_final', 5, 2)->nullable();
            $table->string('estado', 20);
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->nullable();
            $table->unique(['id_estudiante', 'id_curso_plan', 'id_periodo'], 'pre_hist_est_curso_per_uq');
            $table->index('id_curso_plan', 'pre_hist_curso_idx');
            $table->index('id_periodo', 'pre_hist_periodo_idx');
            $table->foreign('id_estudiante', 'pre_hist_est_fk')
                ->references('id_estudiante')->on('core.estudiante')->restrictOnDelete();
            $table->foreign('id_curso_plan', 'pre_hist_cp_fk')
                ->references('id_curso_plan')->on('core.curso_plan')->restrictOnDelete();
            $table->foreign('id_periodo', 'pre_hist_per_fk')
                ->references('id_periodo')->on('core.periodo_academico')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE prematricula.historial_academico
            ADD CONSTRAINT pre_hist_estado_ck
            CHECK (estado IN ('EN_CURSO', 'APROBADO', 'DESAPROBADO', 'RETIRADO')),
            ADD CONSTRAINT pre_hist_nota_ck
            CHECK (nota_final IS NULL OR (nota_final >= 0 AND nota_final <= 20))
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('prematricula.historial_academico');
    }
};
