# Nomaddi Panel Central — Módulo 5: Certificados, Actas y Folios

## Archivos de este módulo

```
database/migrations/
  010_create_certificados_tables.php   → actas, certificados,
                                         logs_validacion_certificado, liquidaciones

app/Models/
  Acta.php             → generarCodigo() + siguienteFolio()
  Certificado.php      → referencias, url_validacion
  LogValidacionCertificado.php
  Liquidacion.php

app/Services/
  CertificadoService.php              → emitir(), anular(), validar()
  MoodleService_additions_mod5.php    → actualizarCamposCertificado()

app/Jobs/
  DetectarUltimoCursoCompletado.php   → Job horario

app/Http/Controllers/Api/
  CertificadoController.php

routes/
  api.php
  console.php
```

---

## Instalación

### 1. Ejecutar migraciones
```bash
php artisan migrate
```

### 2. Configurar campos de perfil en Moodle
Administración → Usuarios → Perfil de usuario → Crear campos:

| Nombre corto | Tipo | Descripción |
|---|---|---|
| `siet_completado` | Casilla | Activo cuando el estudiante completó el formulario SIET |
| `cert_acta` | Texto corto | Código del acta (ej: T2604) |
| `cert_folio` | Texto corto | Número de folio (ej: 15) |
| `cert_uuid` | Texto largo | UUID de verificación |
| `cert_ietdh` | Texto largo | Nombre de la IETDH emisora |
| `cert_fecha` | Texto corto | Fecha de emisión (ej: 12/04/2026) |

### 3. Configurar Custom Certificate en Moodle
En cada curso marcado como "último":
1. Agregar actividad → Custom Certificate
2. Diseñar el certificado con los elementos:
   - Logo de la IETDH (imagen)
   - Nombre del estudiante: elemento "Nombre completo"
   - Nombre del programa: elemento "Nombre del curso"
   - IETDH: elemento "Campo de usuario" → cert_ietdh
   - Fecha: elemento "Campo de usuario" → cert_fecha
   - Acta: elemento "Campo de usuario" → cert_acta
   - Folio: elemento "Campo de usuario" → cert_folio
   - UUID/QR: elemento "Campo de usuario" → cert_uuid

### 4. Agregar Jobs al scheduler
El archivo routes/console.php ya tiene los dos Jobs:
- SincronizarCompletitudMoodle → detecta cursos completados
- DetectarUltimoCursoCompletado → emite certificados

Asegurarse que el crontab está activo (ver Módulo 4).

---

## Lógica de acta y folio

```
Acta T2604 = TECNICOS + año 2026 + mes 04 (abril)
Acta B2601 = BACHILLERATO + año 2026 + mes 01 (enero)

Dentro de T2604:
  Folio 1 → primer certificado de técnicos en abril 2026
  Folio 2 → segundo certificado
  ...
  Folio n → n-ésimo certificado

Al llegar mayo 2026:
  Nueva acta T2605 → folio reinicia en 1

Si se anula el folio 3 de T2604:
  Folio 3 queda registrado como ANULADO
  El siguiente certificado recibe folio 4 (no se reutiliza el 3)
```

## Lógica de IETDH como distribuidor

```
Si ietdh.distribuidor_id == matricula.distribuidor_id
  → No se genera devengo de fee de IETDH
  → La IETDH opera como distribuidor y certificadora a la vez
  → El certificado se emite normalmente con el nombre de la IETDH
```

## Flujo automático completo

```
Hora H:   SincronizarCompletitudMoodle detecta que María completó el curso 15
          → Marca matricula estado=COMPLETADA

Hora H+0: DetectarUltimoCursoCompletado detecta la matrícula COMPLETADA del curso 15
          → CertificadoService.emitir()
            → Acta T2604 (o la crea si no existe)
            → Bloqueo pesimista → folio 23
            → UUID: a1b2c3d4-...
            → Crea Certificado en BD
            → Actualiza campos en Moodle (cert_acta, cert_folio, cert_uuid, cert_ietdh, cert_fecha)
            → María puede descargar su certificado desde Moodle (Custom Certificate)
            → El certificado PDF tiene todos los datos + UUID para verificar
```

## Validación pública

```
GET /api/verificar/a1b2c3d4-e5f6-...
→ { valido: true, estudiante: "María García", programa: "...", folio: 23, acta: "T2604", ... }

GET /api/verificar/uuid-de-certificado-anulado
→ { valido: false, anulado: true, motivo: "Errror en datos del estudiante" }

GET /api/verificar/uuid-inexistente
→ { valido: false, mensaje: "Certificado no encontrado." }
```

El endpoint tiene CORS abierto (`Access-Control-Allow-Origin: *`) para que
cualquier dominio (escuelahseq.com, tecnicos.edu.co, etc.) pueda consultar
la validez de un certificado con una llamada fetch desde su propio sitio.

---

## Próximo módulo

**Módulo 6 — Liquidaciones mensuales de IETDHs**
Cierre mensual automático, cálculo de fees por IETDH y tipo de programa,
generación del documento de liquidación, envío por correo a la IETDH,
registro del estado (generada → facturada → pagada).
