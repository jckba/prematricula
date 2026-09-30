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
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('codigo_universitario', 30)
                ->unique();

            $table->string('nombres', 100);

            $table->string('apellidos', 150);

            $table->unsignedSmallInteger('creditos_maximos')
                ->default(22);

            $table->string('estado', 20)
                ->default('ACTIVO');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
