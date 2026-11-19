<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de cada archivo XLSX de resultados importado por el coordinador.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('prematricula.importacion_historial', function (Blueprint $table) {
            $table->bigIncrements('id_importacion');
            $table->bigInteger('creado_por');
            $table->string('sha256', 64);
            $table->text('motivo');
            $table->jsonb('resumen');
            $table->timestampTz('creado_en')->useCurrent();
            $table->index('creado_por', 'pre_imp_autor_idx');
            $table->foreign('creado_por', 'pre_imp_autor_fk')
                ->references('id_usuario')->on('seguridad.usuario')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('prematricula.importacion_historial');
    }
};
