<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo_institucional', 30)->unique();
            $table->string('nombres', 100);
            $table->string('apellidos', 150);
            $table->string('correo_institucional')->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->decimal('max_horas_semanales', 7, 2)->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE teachers ADD CONSTRAINT teachers_valid_limits CHECK
            (estado IN ('ACTIVO', 'INACTIVO') AND (max_horas_semanales IS NULL OR max_horas_semanales >= 0))");

        Schema::create('teaching_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->decimal('horas_teoria_asignadas', 5, 2)->default(0);
            $table->decimal('horas_practica_asignadas', 5, 2)->default(0);
            $table->decimal('horas_laboratorio_asignadas', 5, 2)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['academic_period_id', 'teacher_id']);
            $table->index(['course_id', 'academic_period_id']);
            $table->index('teacher_id');
        });
        DB::statement('CREATE UNIQUE INDEX teaching_assignment_active_identity ON teaching_assignments
            (academic_period_id, course_id, teacher_id) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE teaching_assignments ADD CONSTRAINT teaching_assignment_positive_hours CHECK
            (horas_teoria_asignadas >= 0 AND horas_practica_asignadas >= 0 AND horas_laboratorio_asignadas >= 0
            AND horas_teoria_asignadas + horas_practica_asignadas + horas_laboratorio_asignadas > 0)');

        Schema::create('teaching_assignment_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('accion', 20);
            $table->jsonb('before')->nullable();
            $table->jsonb('after');
            $table->timestampTz('created_at');
            $table->index(['teaching_assignment_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_assignment_changes');
        Schema::dropIfExists('teaching_assignments');
        Schema::dropIfExists('teachers');
    }
};
