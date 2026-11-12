<?php
// app/Http/Requests/CrearIetdhRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearIetdhRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nombre'                        => 'required|string|max:200',
            'nit'                           => 'required|string|max:20|unique:ietdhs,nit',
            'departamento'                  => 'required|string|max:80',
            'municipio'                     => 'required|string|max:80',
            'email_contacto'                => 'required|email',
            'telefono'                      => 'nullable|string|max:20',

            'fees'                          => 'required|array|min:1',
            'fees.*.tipo_programa'          => 'required|in:TECNICOS,BACHILLERATO',
            'fees.*.fee_por_certificado'    => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'nit.unique'                        => 'Ya existe una IETDH con ese NIT.',
            'fees.required'                     => 'Debe definir al menos un fee por tipo de programa.',
            'fees.*.tipo_programa.in'           => 'Tipo de programa inválido.',
            'fees.*.fee_por_certificado.min'    => 'El fee no puede ser negativo.',
        ];
    }
}
