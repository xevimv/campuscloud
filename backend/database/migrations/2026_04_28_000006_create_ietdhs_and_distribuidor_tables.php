<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── IETDHs aliadas ────────────────────────────────────────────
        Schema::create('ietdhs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre');
            $table->string('nit', 20)->unique();
            $table->string('departamento', 80);
            $table->string('municipio', 80);
            $table->string('email_contacto');
            $table->string('telefono', 20)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // ── Fees por IETDH y tipo de programa (histórico) ─────────────
        Schema::create('ietdh_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ietdh_id')->constrained('ietdhs')->cascadeOnDelete();
            $table->enum('tipo_programa', ['TECNICOS', 'BACHILLERATO']);
            // Lo que Nomaddi le paga a la IETDH por cada certificado emitido
            $table->decimal('fee_por_certificado', 12, 2);
            $table->date('vigente_desde');
            $table->timestamps();

            $table->index(['ietdh_id', 'tipo_programa', 'vigente_desde']);
        });

        // ── Qué IETDH usa cada distribuidor por tipo de programa ──────
        Schema::create('distribuidor_ietdh', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('distribuidor_id')->constrained('distribuidores')->cascadeOnDelete();
            $table->foreignUuid('ietdh_id')->constrained('ietdhs');
            $table->enum('tipo_programa', ['TECNICOS', 'BACHILLERATO']);
            $table->timestamps();

            // Un distribuidor tiene una sola IETDH por tipo de programa
            $table->unique(['distribuidor_id', 'tipo_programa']);
        });

        // ── IDs de los grupos de Moodle por distribuidor y curso ──────
        // Esta tabla resuelve el TODO de MatriculaService::resolverGroupId()
        Schema::create('distribuidor_grupos_cursos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('distribuidor_id')->constrained('distribuidores')->cascadeOnDelete();
            $table->foreignUuid('curso_id')->constrained('cursos')->cascadeOnDelete();
            // ID del grupo en Moodle
            $table->unsignedBigInteger('moodle_group_id');
            $table->timestamps();

            // Un distribuidor tiene un solo grupo por curso
            $table->unique(['distribuidor_id', 'curso_id']);
            $table->index('moodle_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribuidor_grupos_cursos');
        Schema::dropIfExists('distribuidor_ietdh');
        Schema::dropIfExists('ietdh_fees');
        Schema::dropIfExists('ietdhs');
    }
};
