<?php
// routes/console.php — AGREGAR al archivo existente

use App\Jobs\SincronizarCompletitudMoodle;
use Illuminate\Support\Facades\Schedule;

// Sincroniza completitud de cursos desde Moodle cada hora
Schedule::job(new SincronizarCompletitudMoodle)->hourly();
