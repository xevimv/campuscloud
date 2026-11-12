<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Vendedores ────────────────────────────────────────────────
        Schema::create('vendedores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('distribuidor_id')->constrained('distribuidores')->cascadeOnDelete();

            // Vinculado al usuario del sistema (rol VENDEDOR)
            $table->foreignUuid('usuario_id')
                  ->nullable()
                  ->constrained('usuarios')
                  ->nullOnDelete();

            $table->string('nombre');
            $table->string('email')->unique();

            // Código único de referido — viaja en la URL como ?ref=CODIGO
            $table->string('codigo_referido', 20)->unique();

            // Comisión que el distribuidor le paga al vendedor
            // PORCENTAJE: % sobre el monto vendido
            // FIJO: valor fijo en COP por matrícula cerrada
            $table->enum('comision_tipo', ['PORCENTAJE', 'FIJO']);
            $table->decimal('comision_valor', 10, 2)->default(0);

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['distribuidor_id', 'activo']);
        });

        // ── Agregar vendedor_id a matriculas (Módulo 1) ───────────────
        Schema::table('matriculas', function (Blueprint $table) {
            $table->foreignUuid('vendedor_id')
                  ->nullable()
                  ->after('distribuidor_id')
                  ->constrained('vendedores')
                  ->nullOnDelete();

            $table->index('vendedor_id');
        });

        // ── Agregar distribuidor_id a ietdhs (IETDH como distribuidor) ─
        Schema::table('ietdhs', function (Blueprint $table) {
            $table->foreignUuid('distribuidor_id')
                  ->nullable()
                  ->after('activa')
                  ->constrained('distribuidores')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ietdhs', function (Blueprint $table) {
            $table->dropForeign(['distribuidor_id']);
            $table->dropColumn('distribuidor_id');
        });

        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropForeign(['vendedor_id']);
            $table->dropColumn('vendedor_id');
        });

        Schema::dropIfExists('vendedores');
    }
};
