<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('moodle_course_id')->unique();
            $table->string('nombre');
            // TECNICOS | BACHILLERATO
            $table->enum('tipo_programa', ['TECNICOS', 'BACHILLERATO']);
            // Posición dentro de la carrera (1 = primero, 15 = último en técnicos)
            $table->unsignedTinyInteger('orden_en_carrera')->default(1);
            // true = al completarlo se dispara la generación del certificado
            $table->boolean('es_ultimo')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos');
    }
};
