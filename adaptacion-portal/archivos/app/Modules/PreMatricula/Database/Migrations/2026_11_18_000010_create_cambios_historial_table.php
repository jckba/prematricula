<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Valor anterior y nuevo de cada resultado modificado por una importación.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('prematricula.cambio_historial', function (Blueprint $table) {
            $table->bigIncrements('id_cambio');
            $table->bigInteger('id_importacion');
            $table->bigInteger('id_historial');
            $table->jsonb('antes')->nullable();
            $table->jsonb('despues');
            $table->timestampTz('creado_en')->useCurrent();
            $table->index('id_importacion', 'pre_camb_hist_imp_idx');
            $table->index('id_historial', 'pre_camb_hist_hist_idx');
            $table->foreign('id_importacion', 'pre_camb_hist_imp_fk')
                ->references('id_importacion')->on('prematricula.importacion_historial')->restrictOnDelete();
            $table->foreign('id_historial', 'pre_camb_hist_hist_fk')
                ->references('id_historial')->on('prematricula.historial_academico')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('prematricula.cambio_historial');
    }
};
