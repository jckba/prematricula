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
        Schema::table('pre_enrollment_requests', function (Blueprint $table): void {
            // InnoDB reuses the composite index for the academic_period_id foreign key
            // (dropping its implicit one), so give the key its own index first.
            $table->index('academic_period_id', 'pre_enrollment_requests_academic_period_id_foreign');
            $table->dropIndex('requests_period_current_revision_index');
        });
        Schema::table('students', fn (Blueprint $table) => $table->dropIndex('students_surname_pagination_index'));
    }
};
