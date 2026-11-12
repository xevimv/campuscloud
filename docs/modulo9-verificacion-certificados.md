# Nomaddi Panel Central — Módulo 9: Validación Pública de Certificados

## Archivos de este módulo

```
app/verificar/
  page.tsx                   → Búsqueda manual por UUID (/verificar)
  [uuid]/page.tsx            → Verificación directa por UUID (/verificar/{uuid})

components/verificar/
  VerificarCliente.tsx       → Componente con 4 estados: cargando, válido, anulado, inválido

public/
  widget-verificar.html      → Widget embebible para WordPress de distribuidores
```

---

## Cómo funciona

### Desde el panel Next.js (campusvirtual.edu.co)

El QR del certificado apunta a:
```
https://campusvirtual.edu.co/verificar/{uuid}
```
O el usuario puede buscar manualmente en:
```
https://campusvirtual.edu.co/verificar
```

El componente consulta el endpoint público de Laravel:
```
GET /api/verificar/{uuid}
→ { valido: true, estudiante, programa, institucion, fecha, acta, folio }
→ { valido: false, mensaje }
→ { valido: false, anulado: true, motivo }
```

---

### Desde el sitio web de un distribuidor (cualquier dominio)

Cada distribuidor puede agregar el widget a su WordPress:

**Opción A — Redirigir al panel central:**
```html
<!-- El QR del certificado apunta a: -->
https://campusvirtual.edu.co/verificar/{uuid}
```

**Opción B — Widget embebido en su sitio:**
1. Copiar el archivo `widget-verificar.html`
2. Cambiar la variable `NV_API` a la URL real de tu API
3. Cambiar la variable CSS `--color-primario` al color de la marca
4. Pegar el bloque `<div id="nomaddi-verificar">...</script>` en WordPress
   con un bloque "HTML personalizado"

El widget incluye:
- Campo de entrada del UUID con validación de formato
- Verificación al presionar Enter o clic en el botón
- Auto-verificación si la URL tiene `?uuid=xxxx`
- 3 estados visuales: válido (verde), anulado (ámbar), inválido (rojo)
- Diseño completamente personalizable con variables CSS

**Opción C — Página de verificación con su propio dominio:**
```
https://verificar.escuelahseq.com/?uuid={uuid}
```
Configurar un subdominio que cargue el widget-verificar.html
como página principal. El QR del certificado puede apuntar
directamente a este subdominio.

---

## Ruta en Laravel para el log (ya existe en Módulo 5)

```php
// Endpoint público de verificación — sin autenticación — CORS abierto
Route::get('/verificar/{uuid}', [CertificadoController::class, 'verificar']);
```

---

## URLs de verificación configurables por distribuidor

El PDF del certificado puede tener distintas URLs de verificación
según el distribuidor. En `CertificadoService::emitir()`, al construir
la `$urlVerificacion` se puede consultar el dominio del distribuidor:

```php
// En lugar de siempre usar config('app.url'):
$urlBase = $distribuidor->dominio_verificacion
    ?? config('app.url');
$urlVerificacion = "{$urlBase}/verificar/{$certificado->uuid_verificacion}";
```

Agregar columna `dominio_verificacion` nullable a la tabla `distribuidores`
si se quiere esta personalización por distribuidor.

---

## ✅ SISTEMA COMPLETO — Resumen de todos los módulos

| # | Módulo | Archivos entregados |
|---|--------|---------------------|
| 1 | Webhook + Matrícula | 5 migraciones, 7 modelos, 3 servicios, 1 controlador |
| 2 | Distribuidores, IETDHs, Cursos | 1 migración, 4 modelos, 3 servicios, 3 controladores |
| 3 | Auth + Vendedores | 2 migraciones, 2 modelos, 3 servicios, 2 controladores |
| 4 | Formulario SIET + XLS | 1 migración, 2 modelos, 1 servicio, 1 controlador, 1 Job |
| 5 | Certificados, Actas, Folios | 1 migración, 4 modelos, 1 servicio, 1 Job, 1 controlador |
| 5B | PDF del certificado + correo | 2 servicios, 1 Mailable, 2 vistas Blade |
| 6 | Liquidaciones IETDHs | 1 servicio, 1 PDF service, 1 Mailable, 1 Job, 1 controlador |
| 7 | Reportes a distribuidores | 1 servicio, 2 Mailables, 2 Jobs, 2 vistas |
| 8 | Dashboard Next.js | 8 páginas, 2 componentes, 1 cliente API, 1 controlador Laravel |
| 9 | Validación pública | 2 páginas, 1 componente, 1 widget HTML embebible |
