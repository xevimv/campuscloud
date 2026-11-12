<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Usuarios del panel central.
         * Tres roles posibles:
         *
         *  NOMADDI_ADMIN  → Javier y equipo Nomaddi. Acceso total.
         *  DIST_ADMIN     → Administrador de un distribuidor.
         *                   Ve solo sus estudiantes, vendedores y reportes.
         *  VENDEDOR       → Vendedor de un distribuidor.
         *                   Ve solo sus propias matrículas y su comisión.
         *
         * Un usuario está vinculado a un distribuidor (nullable para NOMADDI_ADMIN).
         * Un vendedor además tiene su propio registro en la tabla vendedores.
         */
        Schema::create('usuarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre');
            $table->string('email')->unique();
            $table->string('password');

            $table->enum('rol', ['NOMADDI_ADMIN', 'DIST_ADMIN', 'VENDEDOR']);

            // Nullable para NOMADDI_ADMIN, obligatorio para DIST_ADMIN y VENDEDOR
            $table->foreignUuid('distribuidor_id')
                  ->nullable()
                  ->constrained('distribuidores')
                  ->nullOnDelete();

            $table->boolean('activo')->default(true);
            $table->rememberToken();
            $table->timestamps();

            $table->index(['distribuidor_id', 'rol']);
        });

        // Tokens de Sanctum
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('usuarios');
    }
};
