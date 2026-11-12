<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Tabla de municipios con código DANE.
         * El SIET requiere el código DANE del municipio de origen del estudiante.
         * 1.122 municipios de Colombia.
         * Se puebla con el seeder MunicipiosDaneSeeder.
         */
        Schema::create('municipios_dane', function (Blueprint $table) {
            $table->string('codigo', 10)->primary(); // ej: "05001" = Medellín
            $table->string('departamento', 80);
            $table->string('municipio', 100);
        });

        /**
         * Log de desbloqueos del curso 2 en Moodle.
         * Registra cada vez que el panel desbloquea el acceso al siguiente curso
         * después de que el estudiante completa el formulario SIET.
         */
        Schema::create('logs_desbloqueo_siet', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('estudiante_id')->constrained('estudiantes');
            $table->unsignedBigInteger('moodle_user_id');
            // Curso que se desbloqueó (normalmente el curso 2 de la carrera)
            $table->unsignedBigInteger('moodle_curso_desbloqueado_id');
            $table->enum('estado', ['OK', 'ERROR']);
            $table->string('error_mensaje')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_desbloqueo_siet');
        Schema::dropIfExists('municipios_dane');
    }
};
