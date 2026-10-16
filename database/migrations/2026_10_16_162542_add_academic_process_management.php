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
        Schema::table('academic_periods', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
        });

        Schema::create('pre_enrollment_windows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $table->string('tipo', 20);
            $table->timestampTz('fecha_inicio');
            $table->timestampTz('fecha_fin');
            $table->text('motivo');
            $table->string('timezone', 64);
            $table->string('origen', 20)->default('API');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['academic_period_id', 'fecha_inicio', 'fecha_fin']);
        });
        DB::statement("CREATE UNIQUE INDEX windows_one_ordinary_per_period ON pre_enrollment_windows (academic_period_id) WHERE tipo = 'ORDINARIA'");
        DB::statement("ALTER TABLE pre_enrollment_windows ADD CONSTRAINT windows_valid_range CHECK (fecha_inicio < fecha_fin), ADD CONSTRAINT windows_valid_type CHECK (tipo IN ('ORDINARIA', 'EXTRAORDINARIA'))");

        DB::table('academic_periods')->whereNotNull('fecha_inicio_prematricula')
            ->whereNotNull('fecha_fin_prematricula')->whereColumn('fecha_inicio_prematricula', '<', 'fecha_fin_prematricula')
            ->orderBy('id')->chunkById(200, function ($periods): void {
                foreach ($periods as $period) {
                    DB::table('pre_enrollment_windows')->insert([
                        'academic_period_id' => $period->id, 'tipo' => 'ORDINARIA',
                        'fecha_inicio' => CarbonImmutable::parse($period->fecha_inicio_prematricula, config('academic.legacy_timezone'))->utc()->toIso8601String(),
                        'fecha_fin' => CarbonImmutable::parse($period->fecha_fin_prematricula, config('academic.legacy_timezone'))->utc()->toIso8601String(),
                        'motivo' => 'Migración de fechas heredadas; autor original no registrado.',
                        'timezone' => config('academic.legacy_timezone'), 'origen' => 'MIGRACION',
                        'created_by' => null, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });

        Schema::create('academic_history_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('sha256', 64);
            $table->text('motivo');
            $table->jsonb('summary');
            $table->timestamps();
        });
        Schema::create('academic_history_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_history_import_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_history_id')->constrained()->restrictOnDelete();
            $table->jsonb('before')->nullable();
            $table->jsonb('after');
            $table->timestamps();
        });
        Schema::create('pre_enrollment_validations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pre_enrollment_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('estado', 30);
            $table->jsonb('cursos');
            $table->timestampTz('evaluated_at');
            $table->timestamps();
        });
        Schema::table('pre_enrollment_requests', function (Blueprint $table): void {
            $table->foreignId('correction_window_id')->nullable()->constrained('pre_enrollment_windows')->restrictOnDelete();
        });
        Schema::create('pre_enrollment_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pre_enrollment_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('window_id')->nullable()->constrained('pre_enrollment_windows')->restrictOnDelete();
            $table->string('motivo', 50);
            $table->jsonb('snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_enrollment_revisions');
        Schema::table('pre_enrollment_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('correction_window_id'));
        Schema::dropIfExists('pre_enrollment_validations');
        Schema::dropIfExists('academic_history_changes');
        Schema::dropIfExists('academic_history_imports');
        Schema::dropIfExists('pre_enrollment_windows');
        Schema::table('academic_periods', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
