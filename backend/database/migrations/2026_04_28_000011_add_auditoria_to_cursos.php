<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar criterios de auditoría a la tabla cursos existente
        Schema::table('cursos', function (Blueprint $table) {
            // Cuántos módulos (secciones con contenido) debe tener este curso
            $table->unsignedTinyInteger('auditoria_modulos_esperados')->default(3)->after('es_ultimo');
            // Cuántos cuestionarios/exámenes debe tener este curso
            $table->unsignedTinyInteger('auditoria_examenes_esperados')->default(3)->after('auditoria_modulos_esperados');
            // Nombre esperado del curso (para verificar que el título en Moodle es correcto)
            // Si es null, se usa el nombre registrado en el panel
            $table->string('auditoria_titulo_esperado')->nullable()->after('auditoria_examenes_esperados');
        });

        // Tabla de resultados de auditoría (historial de cada corrida)
        Schema::create('auditorias_cursos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('curso_id')->constrained('cursos')->cascadeOnDelete();

            // Resultado global: APROBADO | ADVERTENCIA | REPROBADO
            $table->enum('resultado_global', ['APROBADO', 'ADVERTENCIA', 'REPROBADO']);

            // Checks individuales (null = no revisado, true = OK, false = fallo)
            $table->boolean('check_titulo')->nullable();
            $table->boolean('check_bloque_progreso')->nullable();
            $table->boolean('check_modulos')->nullable();
            $table->boolean('check_examenes')->nullable();
            $table->boolean('check_certificado')->nullable();
            $table->boolean('check_enlaces')->nullable();
            $table->boolean('check_calificacion')->nullable();

            // Detalle de cada check (para mostrar en el dashboard)
            $table->jsonb('detalle')->nullable();

            // Quién disparó la auditoría
            $table->foreignUuid('auditado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('auditado_en')->useCurrent();

            $table->index(['curso_id', 'auditado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias_cursos');
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn([
                'auditoria_modulos_esperados',
                'auditoria_examenes_esperados',
                'auditoria_titulo_esperado',
            ]);
        });
    }
};
