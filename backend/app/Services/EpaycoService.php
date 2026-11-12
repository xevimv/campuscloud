<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * EpaycoService
 *
 * Valida la autenticidad de los webhooks entrantes de ePayco
 * y extrae los datos normalizados del payload.
 *
 * Documentación de firma ePayco:
 * md5(p_cust_id_cliente~p_key~x_ref_payco~x_transaction_id~x_amount~x_currency_code)
 */
class EpaycoService
{
    private string $custId;
    private string $secretKey;

    public function __construct()
    {
        $this->custId    = config('services.epayco.cust_id');
        $this->secretKey = config('services.epayco.secret_key');
    }

    /**
     * Valida la firma del webhook de ePayco.
     * Retorna true si la firma es válida.
     */
    public function validarFirma(array $payload): bool
    {
        $firma_recibida = $payload['x_signature'] ?? null;

        if (!$firma_recibida) {
            Log::warning('ePayco webhook: sin firma', ['ref' => $payload['x_ref_payco'] ?? null]);
            return false;
        }

        $firma_calculada = md5(implode('~', [
            $this->custId,
            $this->secretKey,
            $payload['x_ref_payco']      ?? '',
            $payload['x_transaction_id'] ?? '',
            $payload['x_amount']         ?? '',
            $payload['x_currency_code']  ?? '',
        ]));

        $valida = hash_equals($firma_calculada, strtolower($firma_recibida));

        if (!$valida) {
            Log::warning('ePayco webhook: firma inválida', [
                'ref'       => $payload['x_ref_payco'] ?? null,
                'calculada' => $firma_calculada,
                'recibida'  => $firma_recibida,
            ]);
        }

        return $valida;
    }

    /**
     * Verifica que la transacción fue aprobada (estado 1 = Aceptada).
     */
    public function estaAprobada(array $payload): bool
    {
        // x_cod_response: 1=Aceptada, 2=Rechazada, 3=Pendiente, 4=Fallida
        return (int) ($payload['x_cod_response'] ?? 0) === 1;
    }

    /**
     * Extrae y normaliza los datos del payload de ePayco.
     * Los campos extra (curso_id, distribuidor_id) viajan en los
     * campos de referencia extra de ePayco (p_attr_1, p_attr_2...).
     */
    public function extraerDatos(array $payload): array
    {
        return [
            'epayco_ref'      => $payload['x_ref_payco'] ?? null,
            'transaction_id'  => $payload['x_transaction_id'] ?? null,
            'monto'           => (float) ($payload['x_amount'] ?? 0),
            'moneda'          => $payload['x_currency_code'] ?? 'COP',

            // Datos del estudiante (vienen del checkout de WooCommerce)
            'email'           => $payload['x_customer_email'] ?? null,
            'nombres'         => $payload['x_customer_name'] ?? null,
            'tipo_documento'  => $payload['p_attr_1'] ?? null,
            'numero_documento'=> $payload['p_attr_2'] ?? null,
            'apellidos'       => $payload['p_attr_3'] ?? null,

            // Identificadores del sistema Nomaddi
            'moodle_course_id'   => (int) ($payload['p_attr_4'] ?? 0),
            'distribuidor_id'    => $payload['p_attr_5'] ?? null,
        ];
    }
}
