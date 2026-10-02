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
        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)
                ->unique();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->timestamp('fecha_inicio_prematricula')
                ->nullable();
            $table->timestamp('fecha_fin_prematricula')
                ->nullable();
            $table->string('estado', 30)
                ->default('PLANIFICACION');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_periods');
    }
};
