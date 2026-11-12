<?php
// app/Http/Requests/CompletarSietRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los 19 campos SIET que el estudiante llena
 * al terminar el curso 1.
 *
 * Las validaciones de número de documento se aplican
 * en el checkout (Módulo 1). Aquí solo validamos los
 * campos demográficos y de multiculturalidad.
 */
class CompletarSietRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Datos personales
            'fecha_nacimiento'   => 'required|date|before:today',
            'municipio_dane'     => 'required|string|exists:municipios_dane,codigo',
            'sexo_biologico'     => 'required|in:M,F',
            'estado_civil'       => 'required|in:SOLTERO,CASADO,UNION_LIBRE,SEPARADO,VIUDO',

            // Datos académicos y socioeconómicos
            'jornada'            => 'required|integer|between:1,4',
            'estrato'            => 'required|integer|between:1,6',
            'regimen_salud'      => 'required|in:CONTRIBUTIVO,SUBSIDIADO,ESPECIAL,NINGUNO',
            'nivel_formacion'    => 'required|string|max:100',
            'ocupacion'          => 'required|string|max:100',
            'discapacidad'       => 'required|boolean',

            // Multiculturalidad — todos requeridos, todos booleanos
            'indigena'           => 'required|boolean',
            'afrodescendiente'   => 'required|boolean',
            'desplazado'         => 'required|boolean',
            'poblacion_frontera' => 'required|boolean',
            'cabeza_familia'     => 'required|boolean',
            'reinsertado'        => 'required|boolean',
            'poblacion_room'     => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'municipio_dane.exists'    => 'El municipio seleccionado no es válido. Selecciona un municipio de la lista.',
            'fecha_nacimiento.before'  => 'La fecha de nacimiento debe ser anterior a hoy.',
            'jornada.between'          => 'La jornada debe ser: 1=Diurna, 2=Nocturna, 3=Fines de semana, 4=Virtual.',
            'estrato.between'          => 'El estrato debe estar entre 1 y 6.',
            'sexo_biologico.in'        => 'El sexo biológico debe ser M o F.',
        ];
    }

    /**
     * Prepara los datos antes de la validación.
     * Convierte strings "true"/"false" a booleanos
     * (WooCommerce puede enviar los checkboxes como strings).
     */
    protected function prepareForValidation(): void
    {
        $booleans = [
            'discapacidad', 'indigena', 'afrodescendiente',
            'desplazado', 'poblacion_frontera', 'cabeza_familia',
            'reinsertado', 'poblacion_room',
        ];

        foreach ($booleans as $campo) {
            if ($this->has($campo)) {
                $this->merge([
                    $campo => filter_var($this->input($campo), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
    }
}
