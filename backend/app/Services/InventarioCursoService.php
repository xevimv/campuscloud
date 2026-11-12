<?php

namespace App\Services;

use App\Models\Curso;
use Illuminate\Support\Collection;

/**
 * InventarioCursoService
 *
 * Genera una tabla de contenido completa de cada curso de Moodle
 * sin necesidad de plugins adicionales.
 *
 * Fuente de datos: API REST de Moodle
 *  - core_course_get_courses    → título, descripción, imagen
 *  - core_course_get_contents   → secciones, módulos, archivos adjuntos
 *
 * Tipos de contenido detectados automáticamente:
 *  VIDEO     → resource con mimetype video/* o .mp4/.webm/.avi/.mov
 *  AUDIO     → resource con mimetype audio/* o .mp3/.wav/.ogg
 *  PDF       → resource con mimetype application/pdf
 *  IMAGEN    → resource con mimetype image/*
 *  PAGINA    → page (página de texto HTML en Moodle)
 *  ETIQUETA  → label (contenido HTML inline entre actividades)
 *  LIBRO     → book (libro con capítulos)
 *  EXAMEN    → quiz (cuestionario)
 *  TAREA     → assign
 *  FORO      → forum
 *  ENLACE    → url (enlace externo)
 *  H5P       → h5pactivity / hvp (contenido interactivo H5P)
 *  SCORM     → scorm
 *  GLOSARIO  → glossary
 *  LECCION   → lesson
 *  CARPETA   → folder
 *  ENCUESTA  → survey / feedback
 *  CERTIFICADO → customcert / certificate / simplecertificate
 *  OTRO      → cualquier otro tipo de módulo
 */
class InventarioCursoService
{
    public function __construct(
        private MoodleService $moodle,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // INVENTARIO DE UN CURSO
    // ──────────────────────────────────────────────────────────────

    /**
     * Genera el inventario completo de un curso.
     * Devuelve metadatos del curso + desglose de contenido por tipo
     * + detalle sección por sección.
     */
    public function inventariar(Curso $curso): array
    {
        // ── Datos generales del curso ─────────────────────────────
        $infoCurso  = $this->moodle->obtenerCurso($curso->moodle_course_id);
        $contenidos = $this->moodle->obtenerContenidoCurso($curso->moodle_course_id);

        // ── Metadatos del curso ───────────────────────────────────
        $metadatos = $this->extraerMetadatos($infoCurso);

        // ── Clasificar todos los módulos ──────────────────────────
        $modulos = collect($contenidos)
            ->flatMap(fn($seccion) => collect($seccion['modules'] ?? [])
                ->map(fn($modulo) => array_merge($modulo, [
                    '_seccion_num'    => $seccion['section'],
                    '_seccion_nombre' => $seccion['name'],
                ]))
            );

        $clasificados = $modulos->map(fn($m) => $this->clasificarModulo($m));

        // ── Resumen por tipo ──────────────────────────────────────
        $resumenTipos = $clasificados
            ->groupBy('tipo')
            ->map(fn($grupo, $tipo) => [
                'tipo'     => $tipo,
                'cantidad' => $grupo->count(),
                'icono'    => $this->iconoTipo($tipo),
                'label'    => $this->labelTipo($tipo),
            ])
            ->sortByDesc('cantidad')
            ->values();

        // ── Secciones con su contenido clasificado ────────────────
        $secciones = collect($contenidos)
            ->filter(fn($s) => $s['section'] > 0 || !empty($s['modules']))
            ->map(fn($s) => [
                'numero'      => $s['section'],
                'nombre'      => $s['name'] ?? "Sección {$s['section']}",
                'visible'     => (bool) ($s['visible'] ?? true),
                'total_items' => count($s['modules'] ?? []),
                'modulos'     => collect($s['modules'] ?? [])
                    ->map(fn($m) => $this->clasificarModulo(
                        array_merge($m, [
                            '_seccion_num'    => $s['section'],
                            '_seccion_nombre' => $s['name'],
                        ])
                    ))
                    ->values()
                    ->toArray(),
            ])
            ->values();

        // ── Totales globales ──────────────────────────────────────
        $totales = [
            'secciones_con_contenido' => $secciones->filter(fn($s) => $s['total_items'] > 0)->count(),
            'actividades_total'       => $clasificados->count(),
            'videos'                  => $clasificados->where('tipo', 'VIDEO')->count(),
            'audios'                  => $clasificados->where('tipo', 'AUDIO')->count(),
            'pdfs'                    => $clasificados->where('tipo', 'PDF')->count(),
            'paginas'                 => $clasificados->whereIn('tipo', ['PAGINA', 'LIBRO', 'ETIQUETA'])->count(),
            'examenes'                => $clasificados->where('tipo', 'EXAMEN')->count(),
            'h5p'                     => $clasificados->where('tipo', 'H5P')->count(),
            'enlaces'                 => $clasificados->where('tipo', 'ENLACE')->count(),
            'tareas'                  => $clasificados->where('tipo', 'TAREA')->count(),
            'foros'                   => $clasificados->where('tipo', 'FORO')->count(),
            'certificados'            => $clasificados->where('tipo', 'CERTIFICADO')->count(),
            'otros'                   => $clasificados->where('tipo', 'OTRO')->count(),
        ];

        return [
            'curso_id'         => $curso->id,
            'moodle_course_id' => $curso->moodle_course_id,
            'metadatos'        => $metadatos,
            'totales'          => $totales,
            'resumen_tipos'    => $resumenTipos->toArray(),
            'secciones'        => $secciones->toArray(),
            'inventariado_en'  => now()->format('d/m/Y H:i'),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // INVENTARIO DE TODOS LOS CURSOS (resumen)
    // ──────────────────────────────────────────────────────────────

    /**
     * Genera un resumen rápido de todos los cursos activos.
     * Muestra los totales de cada tipo sin el desglose sección por sección.
     * Más rápido que inventariar curso por curso.
     */
    public function resumenTodos(): array
    {
        $cursos = Curso::where('activo', true)
            ->orderBy('tipo_programa')
            ->orderBy('orden_en_carrera')
            ->get();

        $resultado = [];

        foreach ($cursos as $curso) {
            try {
                $infoCurso  = $this->moodle->obtenerCurso($curso->moodle_course_id);
                $contenidos = $this->moodle->obtenerContenidoCurso($curso->moodle_course_id);

                $modulos = collect($contenidos)
                    ->flatMap(fn($s) => $s['modules'] ?? []);

                $clasificados = $modulos->map(fn($m) => $this->clasificarModulo($m));

                $resultado[] = [
                    'curso_id'         => $curso->id,
                    'moodle_course_id' => $curso->moodle_course_id,
                    'nombre'           => $infoCurso['fullname'],
                    'nombre_corto'     => $infoCurso['shortname'],
                    'tipo_programa'    => $curso->tipo_programa,
                    'orden_en_carrera' => $curso->orden_en_carrera,
                    'imagen'           => $this->extraerImagenCurso($infoCurso),
                    'descripcion_corta'=> strip_tags($infoCurso['summary'] ?? ''),
                    'secciones'        => collect($contenidos)->filter(fn($s) => $s['section'] > 0 && !empty($s['modules']))->count(),
                    'total_actividades'=> $clasificados->count(),
                    'videos'           => $clasificados->where('tipo', 'VIDEO')->count(),
                    'audios'           => $clasificados->where('tipo', 'AUDIO')->count(),
                    'pdfs'             => $clasificados->where('tipo', 'PDF')->count(),
                    'paginas'          => $clasificados->whereIn('tipo', ['PAGINA', 'LIBRO', 'ETIQUETA'])->count(),
                    'examenes'         => $clasificados->where('tipo', 'EXAMEN')->count(),
                    'h5p'              => $clasificados->where('tipo', 'H5P')->count(),
                    'enlaces'          => $clasificados->where('tipo', 'ENLACE')->count(),
                    'tareas'           => $clasificados->where('tipo', 'TAREA')->count(),
                    'certificado'      => $clasificados->where('tipo', 'CERTIFICADO')->count() > 0,
                ];

                usleep(200_000); // 200ms entre cursos

            } catch (\Exception $e) {
                $resultado[] = [
                    'curso_id'   => $curso->id,
                    'nombre'     => $curso->nombre,
                    'error'      => $e->getMessage(),
                ];
            }
        }

        return $resultado;
    }

    // ──────────────────────────────────────────────────────────────
    // CLASIFICACIÓN DE UN MÓDULO
    // ──────────────────────────────────────────────────────────────

    private function clasificarModulo(array $modulo): array
    {
        $modname  = $modulo['modname'] ?? 'unknown';
        $nombre   = $modulo['name']    ?? 'Sin nombre';
        $archivos = $modulo['contents'] ?? [];

        $tipo = $this->detectarTipo($modname, $archivos);

        return [
            'id'              => $modulo['id'] ?? null,
            'nombre'          => $nombre,
            'tipo'            => $tipo,
            'tipo_label'      => $this->labelTipo($tipo),
            'icono'           => $this->iconoTipo($tipo),
            'modname_moodle'  => $modname,
            'visible'         => (bool) ($modulo['visible'] ?? true),
            'seccion'         => $modulo['_seccion_num'] ?? null,
            'seccion_nombre'  => $modulo['_seccion_nombre'] ?? null,
            'url_moodle'      => $modulo['url'] ?? null,
            'archivos'        => collect($archivos)->map(fn($a) => [
                'nombre'    => $a['filename'] ?? null,
                'tipo_mime' => $a['mimetype'] ?? null,
                'tamaño'    => isset($a['filesize']) ? $this->formatBytes($a['filesize']) : null,
            ])->toArray(),
        ];
    }

    private function detectarTipo(string $modname, array $archivos): string
    {
        // Tipos directos por modname
        return match ($modname) {
            'quiz'                                      => 'EXAMEN',
            'assign'                                    => 'TAREA',
            'forum'                                     => 'FORO',
            'page'                                      => 'PAGINA',
            'label'                                     => 'ETIQUETA',
            'book'                                      => 'LIBRO',
            'url'                                       => 'ENLACE',
            'h5pactivity', 'hvp'                        => 'H5P',
            'scorm'                                     => 'SCORM',
            'glossary'                                  => 'GLOSARIO',
            'lesson'                                    => 'LECCION',
            'folder'                                    => 'CARPETA',
            'survey', 'feedback', 'choice'              => 'ENCUESTA',
            'customcert', 'certificate', 'simplecertificate' => 'CERTIFICADO',
            'resource', 'file' => $this->detectarTipoArchivo($archivos),
            default => 'OTRO',
        };
    }

    private function detectarTipoArchivo(array $archivos): string
    {
        if (empty($archivos)) return 'OTRO';

        $mime      = strtolower($archivos[0]['mimetype'] ?? '');
        $extension = strtolower(pathinfo($archivos[0]['filename'] ?? '', PATHINFO_EXTENSION));

        if (str_starts_with($mime, 'video/') || in_array($extension, ['mp4', 'webm', 'avi', 'mov', 'mkv', 'flv'])) {
            return 'VIDEO';
        }
        if (str_starts_with($mime, 'audio/') || in_array($extension, ['mp3', 'wav', 'ogg', 'm4a', 'aac'])) {
            return 'AUDIO';
        }
        if ($mime === 'application/pdf' || $extension === 'pdf') {
            return 'PDF';
        }
        if (str_starts_with($mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])) {
            return 'IMAGEN';
        }
        if (in_array($mime, ['application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'])) {
            return 'PRESENTACION';
        }
        if (in_array($mime, ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) {
            return 'DOCUMENTO';
        }

        return 'ARCHIVO';
    }

    // ──────────────────────────────────────────────────────────────
    // METADATOS DEL CURSO
    // ──────────────────────────────────────────────────────────────

    private function extraerMetadatos(array $infoCurso): array
    {
        return [
            'titulo'           => $infoCurso['fullname']  ?? '',
            'titulo_corto'     => $infoCurso['shortname'] ?? '',
            'descripcion'      => strip_tags($infoCurso['summary'] ?? ''),
            'descripcion_html' => $infoCurso['summary']   ?? '',
            'imagen'           => $this->extraerImagenCurso($infoCurso),
            'idioma'           => $infoCurso['lang']      ?? 'es',
            'formato'          => $infoCurso['format']    ?? 'topics',
            'visible'          => (bool) ($infoCurso['visible'] ?? true),
            'fecha_inicio'     => isset($infoCurso['startdate'])
                ? date('d/m/Y', $infoCurso['startdate'])
                : null,
            'fecha_fin'        => isset($infoCurso['enddate']) && $infoCurso['enddate'] > 0
                ? date('d/m/Y', $infoCurso['enddate'])
                : null,
            'id_moodle'        => $infoCurso['id'] ?? null,
            'categoria'        => $infoCurso['categoryname'] ?? null,
        ];
    }

    private function extraerImagenCurso(array $infoCurso): ?string
    {
        // La imagen del curso está en overviewfiles
        $overviewfiles = $infoCurso['overviewfiles'] ?? [];
        if (!empty($overviewfiles)) {
            return $overviewfiles[0]['fileurl'] ?? null;
        }
        // Algunos Moodle la exponen como courseimage
        return $infoCurso['courseimage'] ?? null;
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────

    private function labelTipo(string $tipo): string
    {
        return match ($tipo) {
            'VIDEO'        => 'Video',
            'AUDIO'        => 'Audio',
            'PDF'          => 'PDF',
            'IMAGEN'       => 'Imagen',
            'PAGINA'       => 'Página de texto',
            'ETIQUETA'     => 'Etiqueta',
            'LIBRO'        => 'Libro',
            'EXAMEN'       => 'Examen / Cuestionario',
            'TAREA'        => 'Tarea',
            'FORO'         => 'Foro',
            'ENLACE'       => 'Enlace externo',
            'H5P'          => 'Contenido H5P',
            'SCORM'        => 'SCORM',
            'GLOSARIO'     => 'Glosario',
            'LECCION'      => 'Lección',
            'CARPETA'      => 'Carpeta de archivos',
            'ENCUESTA'     => 'Encuesta',
            'CERTIFICADO'  => 'Certificado',
            'PRESENTACION' => 'Presentación',
            'DOCUMENTO'    => 'Documento Word',
            'ARCHIVO'      => 'Archivo',
            default        => 'Otro',
        };
    }

    private function iconoTipo(string $tipo): string
    {
        return match ($tipo) {
            'VIDEO'        => '🎬',
            'AUDIO'        => '🎵',
            'PDF'          => '📄',
            'IMAGEN'       => '🖼️',
            'PAGINA'       => '📝',
            'ETIQUETA'     => '🏷️',
            'LIBRO'        => '📚',
            'EXAMEN'       => '📋',
            'TAREA'        => '✏️',
            'FORO'         => '💬',
            'ENLACE'       => '🔗',
            'H5P'          => '🎮',
            'SCORM'        => '📦',
            'GLOSARIO'     => '📖',
            'LECCION'      => '🎯',
            'CARPETA'      => '📁',
            'ENCUESTA'     => '📊',
            'CERTIFICADO'  => '🏆',
            'PRESENTACION' => '📊',
            'DOCUMENTO'    => '📃',
            default        => '⚙️',
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024)       return "{$bytes} B";
        if ($bytes < 1048576)    return round($bytes / 1024, 1) . " KB";
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . " MB";
        return round($bytes / 1073741824, 2) . " GB";
    }
}
