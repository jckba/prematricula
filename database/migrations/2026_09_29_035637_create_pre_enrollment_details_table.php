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
        Schema::create('pre_enrollment_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pre_enrollment_request_id')
                ->constrained('pre_enrollment_requests')
                ->cascadeOnDelete();

            $table->foreignId('course_id')
                ->constrained('courses')
                ->restrictOnDelete();

            $table->string('preferencia_turno', 30)
                ->default('SIN_PREFERENCIA');

            $table->timestamps();

            $table->unique([
                'pre_enrollment_request_id',
                'course_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_enrollment_details');
    }
};
