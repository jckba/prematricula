<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Créditos máximos que un estudiante puede prematricular.
 * core.estudiante no tiene este dato y el módulo no debe modificar core.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('prematricula.limite_creditos_estudiante', function (Blueprint $table) {
            $table->bigIncrements('id_limite');
            $table->bigInteger('id_estudiante')->unique('pre_lim_estudiante_uq');
            $table->unsignedSmallInteger('creditos_maximos')->default(22);
            $table->timestampTz('actualizado_en')->useCurrent();
            $table->foreign('id_estudiante', 'pre_lim_est_fk')
                ->references('id_estudiante')
                ->on('core.estudiante')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE prematricula.limite_creditos_estudiante
            ADD CONSTRAINT pre_lim_creditos_ck CHECK (creditos_maximos > 0)
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('prematricula.limite_creditos_estudiante');
    }
};
