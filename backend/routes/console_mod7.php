<?php
// routes/console.php — AGREGAR al archivo existente

use App\Jobs\EnviarReportesSemanal;
use App\Jobs\EnviarReportesMensual;

// Reporte semanal — cada lunes a las 8:00 AM
Schedule::job(new EnviarReportesSemanal)->weeklyOn(1, '08:00');

// Reporte mensual — día 1 de cada mes a las 8:30 AM
// (30 min después del cierre de liquidaciones)
Schedule::job(new EnviarReportesMensual)->monthlyOn(1, '08:30');
