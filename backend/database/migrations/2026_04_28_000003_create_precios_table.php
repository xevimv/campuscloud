<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('curso_id')->constrained('cursos')->cascadeOnDelete();
            // Lo que Nomaddi cobra por cada matrícula (Piso 1)
            $table->decimal('fee_nomaddi', 12, 2);
            // Precio mínimo que puede cobrar cualquier distribuidor (Piso 2)
            $table->decimal('precio_minimo_publico', 12, 2);
            // Fecha desde la que aplica este precio (permite historial)
            $table->date('vigente_desde');
            $table->timestamps();

            // Solo un precio activo por curso en un momento dado
            $table->index(['curso_id', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios');
    }
};
