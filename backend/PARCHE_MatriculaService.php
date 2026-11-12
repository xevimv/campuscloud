<?php
// PARCHE para app/Services/MatriculaService.php
// Reemplazar el método resolverGroupId() del Módulo 1 con esta implementación:

// 1. Agregar al constructor de MatriculaService:
//    private DistribuidorService $distribuidorService,

// 2. Reemplazar el método resolverGroupId() con:

    private function resolverGroupId(Distribuidor $distribuidor, Curso $curso): ?int
    {
        return $this->distribuidorService->obtenerGroupId($distribuidor, $curso);
    }

// ─────────────────────────────────────────────────────────────────
// El constructor completo de MatriculaService queda así:

/*
public function __construct(
    private MoodleService       $moodle,
    private DistribuidorService $distribuidorService,
) {}
*/

// Laravel inyecta DistribuidorService automáticamente por el contenedor.
// No se necesita ningún cambio adicional en AppServiceProvider.
