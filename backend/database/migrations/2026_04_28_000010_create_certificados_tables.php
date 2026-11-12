<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Actas ─────────────────────────────────────────────────────
        // Una acta agrupa todos los certificados de una IETDH
        // en un tipo de programa y mes determinado.
        // Código: T/B + año 2 dígitos + mes 2 dígitos → ej: T2604
        Schema::create('actas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ietdh_id')->constrained('ietdhs');
            $table->enum('tipo_programa', ['TECNICOS', 'BACHILLERATO']);
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            // Código generado automáticamente: T2604, B2601, etc.
            $table->string('codigo_acta', 10);
            $table->timestamps();

            // Una sola acta por IETDH, tipo y mes
            $table->unique(['ietdh_id', 'tipo_programa', 'anio', 'mes']);
            $table->index('codigo_acta');
        });

        // ── Certificados ──────────────────────────────────────────────
        Schema::create('certificados', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Relaciones
            $table->foreignUuid('matricula_id')
                  ->unique() // un certificado por matrícula del último curso
                  ->constrained('matriculas');
            $table->foreignUuid('acta_id')->constrained('actas');
            $table->foreignUuid('ietdh_id')->constrained('ietdhs');

            // Se puebla cuando se genera la liquidación mensual
            $table->foreignUuid('liquidacion_id')
                  ->nullable()
                  ->constrained('liquidaciones');

            // Número de folio: consecutivo por acta, reinicia en 1 cada mes
            $table->unsignedInteger('folio');

            // UUID único para validación pública en línea
            $table->uuid('uuid_verificacion')->unique();

            $table->enum('tipo_programa', ['TECNICOS', 'BACHILLERATO']);
            $table->timestamp('fecha_emision')->useCurrent();

            // Anulación
            $table->boolean('anulado')->default(false);
            $table->string('motivo_anulacion')->nullable();
            $table->timestamp('fecha_anulacion')->nullable();

            $table->timestamps();

            // Folio único dentro del acta
            $table->unique(['acta_id', 'folio']);
            $table->index('uuid_verificacion');
            $table->index(['ietdh_id', 'fecha_emision']);
        });

        // ── Log de consultas de validación pública ────────────────────
        Schema::create('logs_validacion_certificado', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid_verificacion')->index();
            $table->string('ip_consultante', 45)->nullable();
            $table->string('dominio_consultante')->nullable();
            $table->enum('resultado', ['VALIDO', 'INVALIDO', 'ANULADO']);
            $table->timestamp('created_at')->useCurrent();
        });

        // ── Tabla de liquidaciones (necesaria para la FK de certificados) ─
        // Si no se creó en un módulo anterior, crearla aquí
        if (!Schema::hasTable('liquidaciones')) {
            Schema::create('liquidaciones', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('ietdh_id')->constrained('ietdhs');
                $table->unsignedSmallInteger('periodo_anio');
                $table->unsignedTinyInteger('periodo_mes');
                $table->unsignedInteger('total_tecnicos')->default(0);
                $table->unsignedInteger('total_bachillerato')->default(0);
                $table->decimal('fee_tecnicos', 12, 2)->default(0);
                $table->decimal('fee_bachillerato', 12, 2)->default(0);
                $table->decimal('valor_total', 12, 2)->default(0);
                // GENERADA | ENVIADA | FACTURADA | PAGADA
                $table->enum('estado', ['GENERADA', 'ENVIADA', 'FACTURADA', 'PAGADA'])
                      ->default('GENERADA');
                $table->timestamp('fecha_generacion')->useCurrent();
                $table->timestamp('fecha_pago')->nullable();
                $table->timestamps();

                $table->unique(['ietdh_id', 'periodo_anio', 'periodo_mes']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_validacion_certificado');
        Schema::dropIfExists('certificados');
        Schema::dropIfExists('actas');
    }
};
