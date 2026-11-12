# Nomaddi Panel Central — Módulo 5B: PDF del Certificado + Correo Automático

## Archivos de este módulo

```
app/Services/
  PdfCertificadoService.php           → genera PDF con DomPDF + QR
  NotificacionCertificadoService.php  → orquesta PDF + correo + Moodle

app/Mail/
  CertificadoEmitido.php              → Mailable con PDF adjunto

resources/views/
  certificados/plantilla.blade.php    → diseño del certificado (ya en Módulo 5)
  emails/certificado-emitido.blade.php → correo HTML al estudiante

PARCHE_CertificadoService.php         → integrar al flujo de emisión
AGREGAR_CertificadoController_pdf.php → endpoint de descarga
```

---

## Instalación

### 1. Instalar dependencias
```bash
composer require barryvdh/laravel-dompdf
composer require endroid/qr-code
```

### 2. Publicar config de DomPDF
```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

### 3. Configurar storage público
```bash
php artisan storage:link
```
Los PDFs se guardan en:
`storage/app/public/certificados/{uuid}.pdf`
→ accesibles en: `https://panel.campusvirtual.edu.co/storage/certificados/{uuid}.pdf`

### 4. Subir logos de las IETDHs
```
storage/app/public/logos-ietdh/{ietdh_id}.png
storage/app/public/logos-ietdh/{ietdh_id}.jpg
```
Si no hay logo, el certificado se genera igualmente sin imagen.

### 5. Aplicar el parche a CertificadoService
Ver archivo `PARCHE_CertificadoService.php`

### 6. Agregar endpoint de descarga al CertificadoController
Ver archivo `AGREGAR_CertificadoController_pdf.php`

### 7. Agregar ruta de descarga en routes/api.php
```php
Route::middleware('auth:sanctum')
    ->get('/certificados/{certificado}/pdf',
          [CertificadoController::class, 'descargarPdf']);
```

### 8. Configurar la cola de emails
```bash
php artisan queue:work --queue=emails
```
O agregar al supervisor del VPS para que corra en segundo plano.

---

## Flujo completo post-emisión

```
CertificadoService::emitir()
  → Crea Certificado en BD (transacción confirmada)
  → NotificacionCertificadoService::notificar()
      → PdfCertificadoService::generar()
          → Genera QR del UUID como base64 PNG
          → Carga logo de la IETDH como base64
          → Renderiza plantilla.blade.php con todos los datos
          → DomPDF genera el PDF en orientación landscape
          → Guarda en storage/app/public/certificados/{uuid}.pdf
      → Mail::to($estudiante)->queue(new CertificadoEmitido(...))
          → Cola 'emails' → envía correo con PDF adjunto
          → Asunto: "Tu certificado está listo — {IETDH}"
          → Reply-to: email de la IETDH
      → MoodleService::actualizarCamposCertificado()
          → Actualiza campos de perfil en Moodle (cert_acta, cert_folio, etc.)
```

## Diseño del certificado

La plantilla `certificados/plantilla.blade.php` incluye:
- Marco decorativo dorado (doble borde)
- Logo de la IETDH (cargado desde storage)
- Nombre completo del estudiante en cursiva grande
- Tipo y número de documento
- Nombre del programa en mayúsculas
- N° de acta y folio en caja destacada
- Espacio para firmas (Representante Legal + Coordinador Académico)
- Pie con URL de verificación + QR de 70px

Para personalizar el diseño por IETDH o tipo de programa,
crear variantes de la plantilla:
- `certificados/plantilla-tecnicos.blade.php`
- `certificados/plantilla-bachillerato.blade.php`

Y en PdfCertificadoService::generar() seleccionar la vista según
`$certificado->tipo_programa`.

---

## Módulo 5 completo — resumen de lo que hace

```
Detecta último curso completado (Job horario)
  → Emite certificado: acta + folio + UUID (Módulo 5)
  → Genera PDF profesional con QR (Módulo 5B)
  → Envía correo al estudiante con PDF adjunto (Módulo 5B)
  → Registra devengo del fee de IETDH (Módulo 5 → liquida en Módulo 6)
  → El estudiante puede verificar en línea desde cualquier dominio (Módulo 5)
  → El estudiante puede descargar el PDF desde el panel en cualquier momento
```

---

## Próximo módulo

**Módulo 6 — Liquidaciones mensuales de IETDHs**
Cierre automático al primer día de cada mes.
Agrupa todos los certificados sin liquidacion_id por IETDH.
Calcula: certificados técnicos × fee_tecnicos + certificados bachillerato × fee_bachillerato.
Genera el documento de liquidación.
Lo envía por correo a la IETDH.
Registra estados: GENERADA → ENVIADA → FACTURADA → PAGADA.
