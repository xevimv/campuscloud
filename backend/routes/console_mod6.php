<?php
// routes/console.php — AGREGAR al archivo existente

use App\Jobs\CerrarMesLiquidaciones;

// Cierre mensual de liquidaciones — día 1 de cada mes a las 6:00 AM
Schedule::job(new CerrarMesLiquidaciones)->monthlyOn(1, '06:00');
