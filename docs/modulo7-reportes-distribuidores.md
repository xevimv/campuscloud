# Nomaddi Panel Central — Módulo 7: Reportes Automáticos a Distribuidores

## Archivos de este módulo

```
app/Services/
  ReporteDistribuidorService.php    → construye los datos de ambos reportes

app/Mail/
  ReporteSemanal.php
  ReporteMensual.php

app/Jobs/
  EnviarReportesSemanal.php         → corre lunes 8:00 AM
  EnviarReportesMensual.php         → corre día 1 de cada mes 8:30 AM

resources/views/emails/reportes/
  semanal.blade.php                 → diseño del correo semanal
  mensual.blade.php                 → diseño del correo mensual

routes/console.php                  → scheduler de ambos Jobs
```

---

## Scheduler completo del sistema (todos los módulos)

```
routes/console.php — resumen de todos los Jobs programados:

Cada hora:
  SincronizarCompletitudMoodle     → detecta cursos completados
  DetectarUltimoCursoCompletado    → emite certificados automáticamente

Cada lunes 8:00 AM:
  EnviarReportesSemanal            → reporte de la semana a distribuidores

Día 1 de cada mes 6:00 AM:
  CerrarMesLiquidaciones           → genera liquidaciones de IETDHs

Día 1 de cada mes 8:30 AM:
  EnviarReportesMensual            → reporte del mes a distribuidores
```

---

## Contenido de cada reporte

### Reporte Semanal (cada lunes)
- Total de matrículas de la semana anterior (lunes a domingo)
- Variación % respecto a la semana anterior
- Desglose por curso
- Desglose por vendedor
- Acumulado del mes en curso

### Reporte Mensual (día 1 de cada mes)
- Total de matrículas del mes cerrado
- Variación % respecto al mes anterior
- Top 5 cursos más vendidos
- Rendimiento por vendedor
- Progreso de estudiantes activos (desde Moodle API):
  - Cuántos completaron más del 50%
  - Cuántos completaron menos del 50%
  - Cuántos sin actividad reciente
- Certificados emitidos ese mes con detalle

---

## Lógica de envío inteligente

El reporte semanal se omite si el distribuidor no tiene ningún historial
(distribuidor recién registrado sin matrículas).

El reporte mensual se omite si el distribuidor no tuvo matrículas NI
certificados ese mes (evita correos vacíos desalentadores).

---

## Próximo módulo

**Módulo 8 — Dashboard en tiempo real (Next.js)**
Panel visual para Nomaddi admin:
fees generados, ventas por distribuidor, matrículas del día,
liquidaciones pendientes, distribuidores activos/inactivos,
tendencias con gráficas, alertas de webhooks fallidos.
