<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pre_enrollment_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('students')
                ->restrictOnDelete();

            $table->foreignId('academic_period_id')
                ->constrained('academic_periods')
                ->restrictOnDelete();

            $table->string('estado', 20)
                ->default('BORRADOR');

            $table->timestamp('fecha_envio')->nullable();
            $table->timestamp('fecha_revision')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('comentarios_coordinador')->nullable();

            $table->timestamps();

            $table->unique([
                'student_id',
                'academic_period_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_enrollment_request');
    }
};
