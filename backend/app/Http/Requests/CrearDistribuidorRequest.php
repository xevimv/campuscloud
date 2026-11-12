<?php
// app/Http/Requests/CrearDistribuidorRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearDistribuidorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nombre'                        => 'required|string|max:150',
            'dominio'                       => 'required|string|max:150|unique:distribuidores,dominio',
            'email_contacto'                => 'required|email',
            'epayco_receiver_id'            => 'nullable|string|max:100',

            // IETDHs: array de asignaciones (opcionales al crear, se pueden asignar después)
            'ietdhs'                        => 'nullable|array',
            'ietdhs.*.ietdh_id'             => 'required_with:ietdhs|uuid|exists:ietdhs,id',
            'ietdhs.*.tipo_programa'        => 'required_with:ietdhs|in:TECNICOS,BACHILLERATO',
        ];
    }

    public function messages(): array
    {
        return [
            'dominio.unique'                => 'Ya existe un distribuidor con ese dominio.',
            'ietdhs.*.ietdh_id.exists'     => 'Una de las IETDHs seleccionadas no existe.',
            'ietdhs.*.tipo_programa.in'    => 'El tipo de programa debe ser TECNICOS o BACHILLERATO.',
        ];
    }
}
