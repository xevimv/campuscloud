<?php
// app/Http/Controllers/Api/SietController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompletarSietRequest;
use App\Models\Estudiante;
use App\Models\Ietdh;
use App\Services\SietService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SietController extends Controller
{
    public function __construct(
        private SietService $siet,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // FORMULARIO SIET
    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/siet/estudiante/{estudiante}
     * Estado del formulario SIET de un estudiante.
     * El frontend lo usa para saber si mostrar el formulario o no.
     */
    public function estado(Estudiante $estudiante): JsonResponse
    {
        return response()->json($this->siet->estado($estudiante));
    }

    /**
     * POST /api/siet/estudiante/{estudiante}
     * El estudiante envía los 19 campos SIET al terminar el curso 1.
     * El panel los guarda y desbloquea el curso 2 en Moodle.
     */
    public function completar(
        CompletarSietRequest $request,
        Estudiante $estudiante
    ): JsonResponse {
        $resultado = $this->siet->completar($estudiante, $request->validated());

        $statusCode = $resultado['status'] === 'ok' ? 200 : 200;

        return response()->json($resultado, $statusCode);
    }

    /**
     * GET /api/municipios
     * Lista de municipios para el selector del formulario SIET.
     * Filtrable por departamento.
     */
    public function municipios(Request $request): JsonResponse
    {
        $query = \App\Models\MunicipioDane::orderBy('departamento')->orderBy('municipio');

        if ($request->has('departamento')) {
            $query->where('departamento', 'ilike', "%{$request->departamento}%");
        }

        return response()->json($query->get());
    }

    // ──────────────────────────────────────────────────────────────
    // EXPORTACIÓN XLS PARA SIET
    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/siet/exportar/{ietdh}?desde=2026-01-01&hasta=2026-01-31
     *
     * Genera el archivo XLS en el formato exacto de la plantilla SIET
     * (Excel 97-2003, .xls) para que la IETDH lo suba directamente.
     *
     * Solo NOMADDI_ADMIN puede exportar.
     */
    public function exportar(Request $request, Ietdh $ietdh): StreamedResponse
    {
        $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $datos = $this->siet->exportarParaSiet(
            $ietdh->id,
            $request->desde,
            $request->hasta,
        );

        // Construir el XLS con la estructura exacta de la plantilla SIET
        $spreadsheet = $this->construirSpreadsheetSiet($datos, $ietdh->nombre);
        $writer      = new Xls($spreadsheet);

        $filename = "SIET_{$ietdh->nit}_{$request->desde}_{$request->hasta}.xls";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // CONSTRUCCIÓN DEL SPREADSHEET
    // ──────────────────────────────────────────────────────────────

    private function construirSpreadsheetSiet(array $datos, string $nombreIetdh): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Matriculados');

        // ── Fila 1: Encabezado de bloque (igual a la plantilla original) ──
        $sheet->setCellValue('A1', 'DATOS DE PROGRAMA');
        $sheet->setCellValue('Q1', 'MULTICULTURALIDAD');

        // ── Fila 2: Encabezados de columnas ───────────────────────────────
        $columnas = [
            'A' => 'CONSECUTIVO',
            'B' => 'FECHA DE INGRESO',
            'C' => 'JORNADA',
            'D' => 'TIPO DE IDENTIFICACIÓN',
            'E' => 'NÚMERO DE IDENTIFICACIÓN',
            'F' => 'NOMBRES',
            'G' => 'APELLIDOS',
            'H' => 'SEXO BIOLÓGICO',
            'I' => 'ESTADO CIVIL',
            'J' => 'FECHA NACIMIENTO',
            'K' => 'LUGAR DE ORIGEN',
            'L' => 'ESTRATO',
            'M' => 'RÉGIMEN DE SALUD',
            'N' => 'NIVEL DE FORMACIÓN',
            'O' => 'OCUPACIÓN',
            'P' => 'DISCAPACIDAD',
            'Q' => 'INDÍGENA',
            'R' => 'AFRODESCENDIENTE',
            'S' => 'DESPLAZADO',
            'T' => 'POBLACIÓN DE FRONTERA',
            'U' => 'CABEZA DE FAMILIA',
            'V' => 'REINSERTADO',
            'W' => 'POBLACIÓN ROOM',
        ];

        foreach ($columnas as $col => $titulo) {
            $sheet->setCellValue("{$col}2", $titulo);
        }

        // ── Filas de datos (desde fila 3) ─────────────────────────────────
        $fila = 3;
        foreach ($datos as $registro) {
            $sheet->setCellValue("A{$fila}", $registro['CONSECUTIVO']);
            $sheet->setCellValue("B{$fila}", $registro['FECHA_INGRESO']);
            $sheet->setCellValue("C{$fila}", $registro['JORNADA']);
            $sheet->setCellValue("D{$fila}", $registro['TIPO_IDENTIFICACION']);
            // Forzar texto para el número de documento (evitar notación científica)
            $sheet->setCellValueExplicit(
                "E{$fila}",
                $registro['NUMERO_IDENTIFICACION'],
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $sheet->setCellValue("F{$fila}", $registro['NOMBRES']);
            $sheet->setCellValue("G{$fila}", $registro['APELLIDOS']);
            $sheet->setCellValue("H{$fila}", $registro['SEXO_BIOLOGICO']);
            $sheet->setCellValue("I{$fila}", $registro['ESTADO_CIVIL']);
            $sheet->setCellValue("J{$fila}", $registro['FECHA_NACIMIENTO']);
            $sheet->setCellValue("K{$fila}", $registro['LUGAR_ORIGEN']);
            $sheet->setCellValue("L{$fila}", $registro['ESTRATO']);
            $sheet->setCellValue("M{$fila}", $registro['REGIMEN_SALUD']);
            $sheet->setCellValue("N{$fila}", $registro['NIVEL_FORMACION']);
            $sheet->setCellValue("O{$fila}", $registro['OCUPACION']);
            $sheet->setCellValue("P{$fila}", $registro['DISCAPACIDAD']);
            $sheet->setCellValue("Q{$fila}", $registro['INDIGENA']);
            $sheet->setCellValue("R{$fila}", $registro['AFRODESCENDIENTE']);
            $sheet->setCellValue("S{$fila}", $registro['DESPLAZADO']);
            $sheet->setCellValue("T{$fila}", $registro['POBLACION_FRONTERA']);
            $sheet->setCellValue("U{$fila}", $registro['CABEZA_FAMILIA']);
            $sheet->setCellValue("V{$fila}", $registro['REINSERTADO']);
            $sheet->setCellValue("W{$fila}", $registro['POBLACION_ROOM']);
            $fila++;
        }

        // Autoajustar columnas
        foreach (range('A', 'W') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
