<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de altas, cambios y bajas de la carga docente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('prematricula.cambio_carga_docente', function (Blueprint $table) {
            $table->bigIncrements('id_cambio');
            $table->bigInteger('id_carga');
            $table->bigInteger('id_actor');
            $table->string('accion', 20);
            $table->jsonb('antes')->nullable();
            $table->jsonb('despues');
            $table->timestampTz('creado_en')->useCurrent();
            $table->index(['id_carga', 'id_cambio'], 'pre_camb_carga_idx');
            $table->foreign('id_carga', 'pre_camb_carga_fk')
                ->references('id_carga')->on('prematricula.carga_docente')->restrictOnDelete();
            $table->foreign('id_actor', 'pre_camb_carga_actor_fk')
                ->references('id_usuario')->on('seguridad.usuario')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE prematricula.cambio_carga_docente
            ADD CONSTRAINT pre_camb_carga_accion_ck
            CHECK (accion IN ('ALTA', 'CAMBIO', 'BAJA'))
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('prematricula.cambio_carga_docente');
    }
};
