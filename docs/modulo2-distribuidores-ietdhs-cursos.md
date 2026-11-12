# Nomaddi Panel Central — Módulo 2: Distribuidores, IETDHs y Cursos

## Archivos de este módulo

```
database/migrations/
  006_create_ietdhs_and_distribuidor_tables.php
    → ietdhs, ietdh_fees, distribuidor_ietdh, distribuidor_grupos_cursos

app/Models/
  Ietdh.php
  IetdhFee.php
  DistribuidorIetdh.php
  DistribuidorGrupoCurso.php        ← resuelve el TODO del Módulo 1

app/Services/
  DistribuidorService.php           ← onboarding completo con Moodle
  CursoService.php                  ← crea curso + grupos en todos los distribuidores
  IetdhService.php                  ← gestión de IETDHs y fees

app/Http/Requests/
  CrearDistribuidorRequest.php
  CrearCursoRequest.php
  CrearIetdhRequest.php

app/Http/Controllers/Api/
  DistribuidorController.php
  CursoController.php
  IetdhController.php

routes/api.php                      ← rutas del módulo
PARCHE_MatriculaService.php         ← resolver el TODO del Módulo 1
```

---

## Instalación

### 1. Ejecutar la migración nueva
```bash
php artisan migrate
```

### 2. Aplicar el parche al MatriculaService del Módulo 1

Editar `app/Services/MatriculaService.php`:

```php
// Cambiar el constructor de:
public function __construct(
    private MoodleService $moodle,
) {}

// A:
public function __construct(
    private MoodleService       $moodle,
    private DistribuidorService $distribuidorService,
) {}
```

Y reemplazar el método `resolverGroupId()`:

```php
private function resolverGroupId(Distribuidor $distribuidor, Curso $curso): ?int
{
    return $this->distribuidorService->obtenerGroupId($distribuidor, $curso);
}
```

### 3. Agregar funciones al token de Moodle

En Moodle → Administración → Plugins → Servicios externos → tu token:
- `core_cohort_create_cohorts`
- `core_cohort_delete_cohorts`
- `core_group_create_groups`
- `core_group_delete_groups`

### 4. Agregar rutas al routes/api.php existente

Copiar el contenido de `routes/api.php` de este módulo al final del archivo existente.

---

## Flujo de onboarding de un distribuidor

```
POST /api/distribuidores
{
  "nombre": "Escuela HSEQ",
  "dominio": "escuelahseq.com",
  "email_contacto": "admin@escuelahseq.com",
  "epayco_receiver_id": "abc123",
  "ietdhs": [
    { "ietdh_id": "uuid-ietdh-medellin", "tipo_programa": "TECNICOS" }
  ]
}

→ Crea Distribuidor en BD
→ Crea cohorte "Escuela HSEQ" en Moodle
→ Guarda moodle_cohort_id en BD
→ Crea grupo "Escuela HSEQ" en cada uno de los N cursos activos de Moodle
→ Guarda cada moodle_group_id en distribuidor_grupos_cursos
→ Asigna IETDH para TECNICOS
→ Devuelve el distribuidor completo
```

## Flujo al registrar un curso nuevo

```
POST /api/cursos
{
  "moodle_course_id": 47,
  "nombre": "Trabajo en alturas nivel avanzado",
  "tipo_programa": "TECNICOS",
  "orden_en_carrera": 8,
  "es_ultimo": false,
  "fee_nomaddi": 30000,
  "precio_minimo_publico": 80000
}

→ Crea Curso en BD
→ Registra precio inicial en historial
→ Crea grupo en Moodle para cada distribuidor activo
→ Guarda cada moodle_group_id en distribuidor_grupos_cursos
→ Devuelve el curso con precio vigente
```

---

## Endpoints disponibles

| Método | URL | Acción |
|--------|-----|--------|
| GET    | /api/distribuidores | Listar todos |
| POST   | /api/distribuidores | Crear + onboarding Moodle |
| GET    | /api/distribuidores/{id} | Ver detalle |
| PUT    | /api/distribuidores/{id} | Actualizar datos básicos |
| POST   | /api/distribuidores/{id}/desactivar | Desactivar |
| POST   | /api/distribuidores/{id}/reactivar | Reactivar |
| POST   | /api/distribuidores/{id}/ietdh | Asignar/cambiar IETDH |
| GET    | /api/cursos | Listar todos con precio vigente |
| POST   | /api/cursos | Crear + sincronizar grupos Moodle |
| PUT    | /api/cursos/{id}/precio | Actualizar precio (histórico) |
| POST   | /api/cursos/{id}/ultimo | Marcar como último de la carrera |
| POST   | /api/cursos/{id}/desactivar | Desactivar |
| GET    | /api/ietdhs | Listar todas |
| POST   | /api/ietdhs | Crear con fees iniciales |
| GET    | /api/ietdhs/{id} | Ver detalle |
| POST   | /api/ietdhs/{id}/fee | Actualizar fee (histórico) |
| POST   | /api/ietdhs/{id}/desactivar | Desactivar |
| POST   | /api/ietdhs/{id}/reactivar | Reactivar |

---

## Próximo módulo

**Módulo 3 — Autenticación del panel**
Sanctum + roles (Nomaddi admin) para proteger todas las rutas de este módulo.
