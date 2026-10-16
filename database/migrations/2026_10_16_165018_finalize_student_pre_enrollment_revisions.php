<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_enrollment_revisions', function (Blueprint $table): void {
            $table->unsignedInteger('revision_number')->nullable();
            $table->string('receipt_code', 50)->nullable()->unique();
            $table->timestampTz('submitted_at')->nullable();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->text('pdf_base64')->nullable();
            $table->string('pdf_sha256', 64)->nullable();
            $table->unique(['pre_enrollment_request_id', 'revision_number'], 'revision_number_per_request');
            $table->unique(['pre_enrollment_request_id', 'id'], 'revision_request_identity');
        });
        Schema::table('pre_enrollment_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->foreign(['id', 'current_revision_id'], 'current_revision_belongs_to_request')
                ->references(['pre_enrollment_request_id', 'id'])->on('pre_enrollment_revisions')->restrictOnDelete();
        });
        Schema::create('pre_enrollment_revision_courses', function (Blueprint $table): void {
            $table->foreignId('revision_id')->constrained('pre_enrollment_revisions')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->primary(['revision_id', 'course_id']);
        });
        Schema::table('pre_enrollment_validations', function (Blueprint $table): void {
            $table->foreignId('revision_id')->nullable()->constrained('pre_enrollment_revisions')->restrictOnDelete();
        });

        DB::table('pre_enrollment_requests')->orderBy('id')->chunkById(100, function ($requests): void {
            foreach ($requests as $request) {
                $number = 0;
                $lastId = null;
                $revisions = DB::table('pre_enrollment_revisions')->where('pre_enrollment_request_id', $request->id)->orderBy('id')->get();
                foreach ($revisions as $revision) {
                    $snapshot = json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR);
                    if ($revision->motivo === 'ENVIO') {
                        $number++;
                        DB::table('pre_enrollment_revisions')->where('id', $revision->id)->update(['revision_number' => $number,
                            'submitted_at' => $revision->created_at === null ? null : CarbonImmutable::parse($revision->created_at, 'UTC')->toIso8601String()]);
                        $lastId = $revision->id;
                    }
                    foreach ($snapshot['detalles'] ?? [] as $detail) {
                        if (isset($detail['course_id']) && DB::table('courses')->where('id', $detail['course_id'])->exists()) {
                            DB::table('pre_enrollment_revision_courses')->insertOrIgnore(['revision_id' => $revision->id, 'course_id' => $detail['course_id']]);
                        }
                    }
                }
                if ($lastId !== null) {
                    DB::table('pre_enrollment_requests')->where('id', $request->id)->update(['current_revision_id' => $lastId]);
                    DB::table('pre_enrollment_validations')->where('pre_enrollment_request_id', $request->id)->update(['revision_id' => $lastId]);
                }
                if ($request->estado === 'ENVIADA' && $request->correction_window_id !== null) {
                    DB::table('pre_enrollment_requests')->where('id', $request->id)->update(['estado' => 'BORRADOR']);
                }
            }
        });
        DB::statement('ALTER TABLE pre_enrollment_revisions ADD CONSTRAINT positive_revision_number CHECK (revision_number IS NULL OR revision_number > 0)');
        DB::unprepared("ALTER TABLE pre_enrollment_revisions ADD CONSTRAINT complete_new_submitted_revision CHECK (
            schema_version < 2 OR motivo <> 'ENVIO' OR (
                revision_number IS NOT NULL AND receipt_code IS NOT NULL AND submitted_at IS NOT NULL
                AND pdf_base64 IS NOT NULL AND octet_length(pdf_base64) > 0 AND pdf_sha256 IS NOT NULL
                AND length(pdf_sha256) = 64
                AND snapshot ?& array['institution', 'estudiante', 'periodo', 'detalles', 'revision_number', 'fecha_envio', 'receipt_code']
            ))");
        DB::unprepared("CREATE OR REPLACE FUNCTION protect_pre_enrollment_revision() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'Las revisiones enviadas son inmutables'; END; $$;
            CREATE TRIGGER immutable_pre_enrollment_revision BEFORE UPDATE OR DELETE ON pre_enrollment_revisions
            FOR EACH ROW EXECUTE FUNCTION protect_pre_enrollment_revision();");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS immutable_pre_enrollment_revision ON pre_enrollment_revisions');
        DB::statement('DROP FUNCTION IF EXISTS protect_pre_enrollment_revision()');
        Schema::table('pre_enrollment_validations', fn (Blueprint $table) => $table->dropConstrainedForeignId('revision_id'));
        Schema::dropIfExists('pre_enrollment_revision_courses');
        Schema::table('pre_enrollment_requests', function (Blueprint $table): void {
            $table->dropForeign('current_revision_belongs_to_request');
            $table->dropColumn('current_revision_id');
        });
        DB::statement('ALTER TABLE pre_enrollment_revisions DROP CONSTRAINT IF EXISTS positive_revision_number');
        DB::statement('ALTER TABLE pre_enrollment_revisions DROP CONSTRAINT IF EXISTS complete_new_submitted_revision');
        Schema::table('pre_enrollment_revisions', function (Blueprint $table): void {
            $table->dropUnique('revision_number_per_request');
            $table->dropUnique('revision_request_identity');
            $table->dropUnique(['receipt_code']);
            $table->dropColumn(['revision_number', 'receipt_code', 'submitted_at', 'schema_version', 'pdf_base64', 'pdf_sha256']);
        });
    }
};
