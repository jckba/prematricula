<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_enrollment_requests', function (Blueprint $table): void {
            $table->index(['academic_period_id', 'current_revision_id'], 'requests_period_current_revision_index');
        });
        Schema::table('students', function (Blueprint $table): void {
            $table->index(['apellidos', 'id'], 'students_surname_pagination_index');
        });
    }

    public function down(): void
    {
        Schema::table('pre_enrollment_requests', fn (Blueprint $table) => $table->dropIndex('requests_period_current_revision_index'));
        Schema::table('students', fn (Blueprint $table) => $table->dropIndex('students_surname_pagination_index'));
    }
};
