<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Datos básicos capturados en el checkout
        Schema::create('estudiantes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('tipo_documento', [
                'RC',   // Registro civil
                'TI',   // Tarjeta de identidad
                'CC',   // Cédula de ciudadanía
                'CE',   // Cédula de extranjería
                'PA',   // Pasaporte
                'PPT',  // Permiso por Protección Temporal
            ]);
            $table->string('numero_documento', 20);
            $table->string('nombres', 150);
            $table->string('apellidos', 150);
            $table->string('email')->unique();
            // ID del usuario en Moodle (se asigna al crear la cuenta)
            $table->unsignedBigInteger('moodle_user_id')->nullable()->unique();
            // Distribuidor por el que se registró por primera vez
            $table->foreignUuid('distribuidor_id')->constrained('distribuidores');
            $table->timestamps();

            // Un documento solo existe una vez en el sistema
            $table->unique(['tipo_documento', 'numero_documento']);
        });

        // Datos SIET — capturados al terminar el curso 1
        Schema::create('estudiantes_siet', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('estudiante_id')
                  ->unique()
                  ->constrained('estudiantes')
                  ->cascadeOnDelete();

            $table->date('fecha_nacimiento')->nullable();
            // Código DANE del municipio de origen
            $table->string('municipio_dane', 10)->nullable();
            $table->enum('sexo_biologico', ['M', 'F'])->nullable();
            $table->enum('estado_civil', [
                'SOLTERO', 'CASADO', 'UNION_LIBRE', 'SEPARADO', 'VIUDO'
            ])->nullable();
            // 1=Diurna 2=Nocturna 3=Fines de semana 4=Virtual
            $table->unsignedTinyInteger('jornada')->nullable();
            $table->unsignedTinyInteger('estrato')->nullable();
            $table->enum('regimen_salud', ['CONTRIBUTIVO', 'SUBSIDIADO', 'ESPECIAL', 'NINGUNO'])->nullable();
            $table->string('nivel_formacion', 50)->nullable();
            $table->string('ocupacion', 100)->nullable();

            // Campos de multiculturalidad (booleanos)
            $table->boolean('discapacidad')->default(false);
            $table->boolean('indigena')->default(false);
            $table->boolean('afrodescendiente')->default(false);
            $table->boolean('desplazado')->default(false);
            $table->boolean('poblacion_frontera')->default(false);
            $table->boolean('cabeza_familia')->default(false);
            $table->boolean('reinsertado')->default(false);
            $table->boolean('poblacion_room')->default(false);

            // Cuando el estudiante completó el formulario
            $table->timestamp('completado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiantes_siet');
        Schema::dropIfExists('estudiantes');
    }
};
