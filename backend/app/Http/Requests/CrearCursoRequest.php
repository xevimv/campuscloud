<?php
// app/Http/Requests/CrearCursoRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearCursoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'moodle_course_id'      => 'required|integer|unique:cursos,moodle_course_id',
            'nombre'                => 'required|string|max:200',
            'tipo_programa'         => 'required|in:TECNICOS,BACHILLERATO',
            'orden_en_carrera'      => 'required|integer|min:1|max:99',
            'es_ultimo'             => 'boolean',
            'fee_nomaddi'           => 'required|numeric|min:0',
            'precio_minimo_publico' => 'required|numeric|gt:fee_nomaddi',
        ];
    }

    public function messages(): array
    {
        return [
            'moodle_course_id.unique'            => 'Este ID de curso de Moodle ya está registrado.',
            'precio_minimo_publico.gt'           => 'El precio mínimo debe ser mayor al fee de Nomaddi.',
            'tipo_programa.in'                   => 'El tipo de programa debe ser TECNICOS o BACHILLERATO.',
        ];
    }
}
