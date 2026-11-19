<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite varias ventanas por período: una ORDINARIA y posteriores EXTRAORDINARIA.
 * Antes la tabla solo admitía un cronograma por período (pre_crono_periodo_uq).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('prematricula.cronograma_prematricula', function (Blueprint $table) {
            $table->string('tipo', 20)->default('ORDINARIA');
            $table->text('motivo')->nullable();
            $table->string('zona_horaria', 64)->default('America/Lima');
            $table->bigInteger('creado_por')->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->dropUnique('pre_crono_periodo_uq');
            $table->index(['id_periodo', 'fecha_inicio', 'fecha_fin'], 'pre_crono_periodo_rango_idx');
            $table->foreign('creado_por', 'pre_crono_autor_fk')
                ->references('id_usuario')
                ->on('seguridad.usuario')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE prematricula.cronograma_prematricula
            ADD CONSTRAINT pre_crono_tipo_ck
            CHECK (tipo IN ('ORDINARIA', 'EXTRAORDINARIA'))
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX pre_crono_una_ordinaria_uq
            ON prematricula.cronograma_prematricula (id_periodo)
            WHERE tipo = 'ORDINARIA'
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS prematricula.pre_crono_una_ordinaria_uq');
        DB::statement('DROP INDEX IF EXISTS prematricula.pre_crono_periodo_rango_idx');
        DB::statement('ALTER TABLE prematricula.cronograma_prematricula DROP CONSTRAINT IF EXISTS pre_crono_tipo_ck');

        Schema::table('prematricula.cronograma_prematricula', function (Blueprint $table) {
            $table->dropForeign('pre_crono_autor_fk');
            $table->dropColumn(['tipo', 'motivo', 'zona_horaria', 'creado_por', 'creado_en']);
            // Falla a propósito si ya existen varias ventanas en un período: no se borran datos.
            $table->unique('id_periodo', 'pre_crono_periodo_uq');
        });
    }
};
