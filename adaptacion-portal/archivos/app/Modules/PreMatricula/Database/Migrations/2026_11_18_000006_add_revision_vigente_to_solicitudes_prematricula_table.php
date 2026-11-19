<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La solicitud apunta a su revisión vigente y, si está en corrección,
 * a la ventana extraordinaria que la habilitó.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('prematricula.solicitud_prematricula', function (Blueprint $table) {
            $table->bigInteger('id_revision_vigente')->nullable();
            $table->bigInteger('id_cronograma_correccion')->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->timestampTz('actualizado_en')->nullable();
            $table->index(['id_periodo', 'id_revision_vigente'], 'pre_sol_periodo_rev_idx');
            $table->index('id_cronograma_correccion', 'pre_sol_crono_corr_idx');
            // FK compuesta: la revisión vigente debe pertenecer a esta misma solicitud.
            $table->foreign(['id_solicitud', 'id_revision_vigente'], 'pre_sol_rev_vigente_fk')
                ->references(['id_solicitud', 'id_revision'])
                ->on('prematricula.revision_solicitud')
                ->restrictOnDelete();
            $table->foreign('id_cronograma_correccion', 'pre_sol_crono_corr_fk')
                ->references('id_cronograma')
                ->on('prematricula.cronograma_prematricula')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // dropIndex() no califica el índice con el esquema; se usa SQL explícito.
        DB::statement('DROP INDEX IF EXISTS prematricula.pre_sol_periodo_rev_idx');
        DB::statement('DROP INDEX IF EXISTS prematricula.pre_sol_crono_corr_idx');

        Schema::table('prematricula.solicitud_prematricula', function (Blueprint $table) {
            $table->dropForeign('pre_sol_rev_vigente_fk');
            $table->dropForeign('pre_sol_crono_corr_fk');
            $table->dropColumn(['id_revision_vigente', 'id_cronograma_correccion', 'creado_en', 'actualizado_en']);
        });
    }
};
