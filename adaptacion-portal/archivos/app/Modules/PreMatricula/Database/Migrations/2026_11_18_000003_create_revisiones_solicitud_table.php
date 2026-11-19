<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada envío de una solicitud genera una revisión inmutable (snapshot + constancia PDF).
 * Equivale a pre_enrollment_revisions del proyecto original.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('prematricula.revision_solicitud', function (Blueprint $table) {
            $table->bigIncrements('id_revision');
            $table->bigInteger('id_solicitud');
            $table->bigInteger('id_cronograma')->nullable();
            $table->string('motivo', 50);
            $table->unsignedInteger('numero_revision')->nullable();
            $table->string('codigo_constancia', 50)->nullable()->unique('pre_rev_constancia_uq');
            $table->jsonb('snapshot');
            $table->unsignedSmallInteger('version_esquema')->default(2);
            $table->text('pdf_base64')->nullable();
            $table->string('pdf_sha256', 64)->nullable();
            $table->timestampTz('enviada_en')->nullable();
            $table->timestampTz('creado_en')->useCurrent();
            $table->unique(['id_solicitud', 'numero_revision'], 'pre_rev_numero_uq');
            $table->unique(['id_solicitud', 'id_revision'], 'pre_rev_solicitud_identidad_uq');
            $table->index('id_cronograma', 'pre_rev_crono_idx');
            $table->foreign('id_solicitud', 'pre_rev_sol_fk')
                ->references('id_solicitud')
                ->on('prematricula.solicitud_prematricula')
                ->restrictOnDelete();
            $table->foreign('id_cronograma', 'pre_rev_crono_fk')
                ->references('id_cronograma')
                ->on('prematricula.cronograma_prematricula')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE prematricula.revision_solicitud
            ADD CONSTRAINT pre_rev_motivo_ck CHECK (motivo IN ('ENVIO', 'LEGACY_CAPTURE')),
            ADD CONSTRAINT pre_rev_numero_ck CHECK (numero_revision IS NULL OR numero_revision > 0),
            ADD CONSTRAINT pre_rev_envio_completo_ck CHECK (
                motivo <> 'ENVIO' OR (
                    numero_revision IS NOT NULL
                    AND codigo_constancia IS NOT NULL
                    AND enviada_en IS NOT NULL
                    AND pdf_base64 IS NOT NULL AND octet_length(pdf_base64) > 0
                    AND pdf_sha256 IS NOT NULL AND length(pdf_sha256) = 64
                )
            )
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prematricula.proteger_revision_solicitud()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Las revisiones de prematrícula son inmutables';
            END;
            $$;

            CREATE TRIGGER pre_revision_inmutable
            BEFORE UPDATE OR DELETE ON prematricula.revision_solicitud
            FOR EACH ROW EXECUTE FUNCTION prematricula.proteger_revision_solicitud();
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS pre_revision_inmutable ON prematricula.revision_solicitud');
        DB::statement('DROP FUNCTION IF EXISTS prematricula.proteger_revision_solicitud()');
        Schema::dropIfExists('prematricula.revision_solicitud');
    }
};
