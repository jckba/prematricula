<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carga docente semanal estimada por período/curso/docente.
 * Es la planificación de la prematrícula; no reemplaza los horarios de gestion_horarios.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('prematricula.carga_docente', function (Blueprint $table) {
            $table->bigIncrements('id_carga');
            $table->bigInteger('id_periodo');
            $table->bigInteger('id_curso_plan');
            $table->bigInteger('id_docente');
            $table->decimal('horas_teoria', 5, 2)->default(0);
            $table->decimal('horas_practica', 5, 2)->default(0);
            $table->decimal('horas_laboratorio', 5, 2)->default(0);
            $table->bigInteger('creado_por');
            $table->bigInteger('actualizado_por');
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->nullable();
            $table->timestampTz('eliminado_en')->nullable();
            $table->index(['id_periodo', 'id_docente'], 'pre_carga_per_doc_idx');
            $table->index(['id_curso_plan', 'id_periodo'], 'pre_carga_curso_per_idx');
            $table->index('id_docente', 'pre_carga_doc_idx');
            $table->foreign('id_periodo', 'pre_carga_per_fk')
                ->references('id_periodo')->on('core.periodo_academico')->restrictOnDelete();
            $table->foreign('id_curso_plan', 'pre_carga_cp_fk')
                ->references('id_curso_plan')->on('core.curso_plan')->restrictOnDelete();
            $table->foreign('id_docente', 'pre_carga_doc_fk')
                ->references('id_docente')->on('core.docente')->restrictOnDelete();
            $table->foreign('creado_por', 'pre_carga_autor_fk')
                ->references('id_usuario')->on('seguridad.usuario')->restrictOnDelete();
            $table->foreign('actualizado_por', 'pre_carga_editor_fk')
                ->references('id_usuario')->on('seguridad.usuario')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX pre_carga_vigente_uq
            ON prematricula.carga_docente (id_periodo, id_curso_plan, id_docente)
            WHERE eliminado_en IS NULL
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE prematricula.carga_docente
            ADD CONSTRAINT pre_carga_horas_ck CHECK (
                horas_teoria >= 0 AND horas_practica >= 0 AND horas_laboratorio >= 0
                AND horas_teoria + horas_practica + horas_laboratorio > 0
            )
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('prematricula.carga_docente');
    }
};
