# Nomaddi Panel Central — Módulo 6: Liquidaciones Mensuales de IETDHs

## Archivos de este módulo

```
app/Services/
  LiquidacionService.php          → cierre mensual, cálculo, estados
  PdfLiquidacionService.php       → genera PDF del documento

app/Mail/
  LiquidacionGenerada.php         → Mailable con PDF adjunto

app/Jobs/
  CerrarMesLiquidaciones.php      → Job día 1 de cada mes a las 6am

app/Http/Controllers/Api/
  LiquidacionController.php

resources/views/
  liquidaciones/documento.blade.php         → diseño del documento PDF
  emails/liquidaciones/correo.blade.php     → correo HTML a la IETDH

routes/
  api.php
  console.php                     → monthlyOn(1, '06:00')
```

---

## Flujo automático mensual

```
Día 1 del mes a las 6:00 AM — Job CerrarMesLiquidaciones:

Para cada IETDH activa:
  → Busca certificados del mes anterior sin liquidacion_id
  → Excluye los de IETDHs que operan como distribuidoras propias
  → Agrupa por tipo: TECNICOS y BACHILLERATO
  → Consulta el fee vigente en esa fecha para cada tipo
  → Crea Liquidacion en BD
  → Vincula los certificados (liquidacion_id)
  → Genera el PDF con PdfLiquidacionService
  → Encola el correo con LiquidacionGenerada (PDF adjunto)
  → Marca la liquidación como ENVIADA

La IETDH recibe el correo con:
  → Resumen: X certificados técnicos + Y bachillerato = $ total
  → PDF adjunto con el detalle completo
  → Instrucciones para emitir la factura

Nomaddi recibe la factura de la IETDH y:
  → POST /api/liquidaciones/{id}/facturada  → estado = FACTURADA
  → POST /api/liquidaciones/{id}/pagada     → estado = PAGADA + fecha_pago

```

## Estados de la liquidación

```
GENERADA   → recién creada por el Job
    ↓
ENVIADA    → correo enviado automáticamente
    ↓
FACTURADA  → Nomaddi registra que recibió la factura de la IETDH
    ↓
PAGADA     → Nomaddi registra que realizó el pago
```

## Lógica IETDH como distribuidora

Cuando una IETDH tiene `distribuidor_id` vinculado, sus propios
certificados NO se incluyen en la liquidación.
La razón: esa IETDH ya pagó su fee como distribuidora (vía ePayco).
Cobrarle también como certificadora sería doble cobro.

## Endpoints

| Método | URL | Descripción |
|--------|-----|-------------|
| GET | /api/liquidaciones | Listar con filtros |
| GET | /api/liquidaciones/pendientes | Dashboard de pendientes de pago |
| POST | /api/liquidaciones/generar | Generación manual por IETDH y período |
| GET | /api/liquidaciones/{id} | Detalle con todos los certificados |
| GET | /api/liquidaciones/{id}/pdf | Descargar PDF del documento |
| POST | /api/liquidaciones/{id}/facturada | Marcar como FACTURADA |
| POST | /api/liquidaciones/{id}/pagada | Marcar como PAGADA |

---

## Próximo módulo

**Módulo 7 — Reportes automáticos a distribuidores**
Correo semanal cada lunes con matrículas de la semana,
correo mensual el día 1 con cierre y comisiones de vendedores,
datos de progreso consultados desde la API de Moodle.
