<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matriculas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('estudiante_id')->constrained('estudiantes');
            $table->foreignUuid('curso_id')->constrained('cursos');
            $table->foreignUuid('distribuidor_id')->constrained('distribuidores');

            // Referencia de pago de ePayco
            $table->string('epayco_ref')->unique();
            $table->decimal('monto_pagado', 12, 2);
            $table->decimal('fee_nomaddi_cobrado', 12, 2);

            // IDs en Moodle (para consultas de progreso vía API)
            $table->unsignedBigInteger('moodle_enrol_id')->nullable();
            $table->unsignedBigInteger('moodle_group_id')->nullable();

            // ACTIVA | COMPLETADA | CANCELADA
            $table->enum('estado', ['ACTIVA', 'COMPLETADA', 'CANCELADA'])->default('ACTIVA');

            $table->timestamp('fecha_matricula')->useCurrent();
            $table->timestamp('fecha_completado')->nullable();
            $table->timestamps();

            // Un estudiante no puede matricularse dos veces al mismo curso
            $table->unique(['estudiante_id', 'curso_id']);
            $table->index(['distribuidor_id', 'estado']);
            $table->index('fecha_matricula');
        });

        // Log de cada webhook recibido — éxito o fracaso
        Schema::create('logs_webhook', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Referencia de ePayco (puede llegar antes de tener la matrícula)
            $table->string('epayco_ref')->index();
            $table->foreignUuid('distribuidor_id')->nullable()->constrained('distribuidores');
            $table->unsignedBigInteger('moodle_course_id')->nullable();
            $table->string('email_estudiante')->nullable();
            $table->decimal('monto', 12, 2)->nullable();

            // RECIBIDO | VALIDADO | RECHAZADO | MATRICULADO | ERROR
            $table->enum('estado', [
                'RECIBIDO',
                'VALIDADO',
                'RECHAZADO',
                'MATRICULADO',
                'ERROR',
            ]);

            // Razón del rechazo o del error (precio bajo, firma inválida, etc.)
            $table->string('motivo_rechazo')->nullable();
            // Payload completo de ePayco para auditoría
            $table->jsonb('payload_raw')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_webhook');
        Schema::dropIfExists('matriculas');
    }
};
