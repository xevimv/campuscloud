<?php
// routes/console.php — AGREGAR al archivo existente

use App\Jobs\DetectarUltimoCursoCompletado;

// Detecta últimos cursos completados y emite certificados — cada hora
Schedule::job(new DetectarUltimoCursoCompletado)->hourly();
