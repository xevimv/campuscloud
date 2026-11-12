<?php
// PARCHE para app/Services/EpaycoService.php
// Agregar p_attr_6 (código de referido del vendedor) al método extraerDatos()

// Reemplazar el return del método extraerDatos() con:

    public function extraerDatos(array $payload): array
    {
        return [
            'epayco_ref'       => $payload['x_ref_payco'] ?? null,
            'transaction_id'   => $payload['x_transaction_id'] ?? null,
            'monto'            => (float) ($payload['x_amount'] ?? 0),
            'moneda'           => $payload['x_currency_code'] ?? 'COP',

            // Datos del estudiante
            'email'            => $payload['x_customer_email'] ?? null,
            'nombres'          => $payload['x_customer_name'] ?? null,
            'tipo_documento'   => $payload['p_attr_1'] ?? null,
            'numero_documento' => $payload['p_attr_2'] ?? null,
            'apellidos'        => $payload['p_attr_3'] ?? null,

            // Identificadores del sistema Nomaddi
            'moodle_course_id'  => (int) ($payload['p_attr_4'] ?? 0),
            'distribuidor_id'   => $payload['p_attr_5'] ?? null,

            // NUEVO — código de referido del vendedor (nullable)
            'codigo_referido'   => $payload['p_attr_6'] ?? null,
        ];
    }

// ─────────────────────────────────────────────────────────────────
// PARCHE para app/Services/MatriculaService.php
// Agregar VendedorService al constructor y resolver el vendedor en procesar()

// 1. Agregar al constructor:
//    private VendedorService $vendedorService,

// 2. En el método procesar(), ANTES de crear la Matricula, agregar:

    $vendedor = $this->vendedorService->resolverPorCodigo(
        $datos['codigo_referido'] ?? null,
        $distribuidor->id
    );

// 3. En el Matricula::create(), agregar el campo:
//    'vendedor_id' => $vendedor?->id,

// El constructor completo de MatriculaService queda:
/*
public function __construct(
    private MoodleService       $moodle,
    private DistribuidorService $distribuidorService,
    private VendedorService     $vendedorService,
) {}
*/
