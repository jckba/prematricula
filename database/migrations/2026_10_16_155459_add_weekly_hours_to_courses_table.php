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
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('horas_teoria', 5, 2)->default(0);
            $table->decimal('horas_practica', 5, 2)->default(0);
            $table->decimal('horas_laboratorio', 5, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['horas_teoria', 'horas_practica', 'horas_laboratorio']);
        });
    }
};
