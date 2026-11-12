{{-- resources/views/emails/reportes/mensual.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:0;color:#333}
  .wrap{max-width:640px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
  .header{background:linear-gradient(135deg,#1D4E89,#1D9E75);padding:28px 36px;text-align:center}
  .header h1{color:#fff;font-size:20px;margin:0 0 4px}
  .header p{color:rgba(255,255,255,.75);font-size:13px;margin:0}
  .body{padding:28px 36px}
  .saludo{font-size:15px;font-weight:bold;color:#1D4E89;margin-bottom:16px}
  .metricas{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:20px 0}
  .metrica{background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px;text-align:center}
  .metrica-num{font-size:24px;font-weight:bold;color:#1D4E89;line-height:1}
  .metrica-label{font-size:10px;color:#6B7280;margin-top:4px}
  .metrica-var{font-size:11px;font-weight:bold;margin-top:4px}
  .var-pos{color:#059669} .var-neg{color:#DC2626}
  .section-title{font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:.06em;color:#9CA3AF;margin:22px 0 10px;padding-bottom:6px;border-bottom:1px solid #F3F4F6}
  table{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:16px}
  th{background:#F3F4F6;color:#6B7280;padding:7px 10px;text-align:left;font-size:11px;text-transform:uppercase}
  td{padding:7px 10px;border-bottom:1px solid #F9FAFB;color:#374151}
  .progreso-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin:12px 0}
  .prog-item{text-align:center;padding:10px;border-radius:6px;font-size:12px}
  .prog-alto{background:#D1FAE5;color:#065F46}
  .prog-medio{background:#FEF3C7;color:#92400E}
  .prog-bajo{background:#FEE2E2;color:#991B1B}
  .prog-num{font-size:22px;font-weight:bold;display:block;margin-bottom:4px}
  .cert-item{background:#F0FDF4;border:1px solid #BBF7D0;border-radius:4px;padding:8px 12px;margin-bottom:6px;font-size:12px;display:flex;justify-content:space-between}
  .footer{background:#F9FAFB;padding:16px 36px;text-align:center;font-size:11px;color:#9CA3AF;border-top:1px solid #F3F4F6}
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <h1>📈 Reporte Mensual — {{ $datos['periodo'] }}</h1>
    <p>{{ $datos['periodo_inicio'] }} al {{ $datos['periodo_fin'] }}</p>
  </div>

  <div class="body">
    <p class="saludo">Hola, {{ $datos['distribuidor']->nombre }}</p>
    <p style="font-size:13px;color:#6B7280;margin-bottom:20px">
      Aquí tienes el resumen completo de tu actividad en {{ $datos['periodo'] }}.
    </p>

    {{-- Métricas clave --}}
    <div class="metricas">
      <div class="metrica">
        <div class="metrica-num">{{ $datos['total_matriculas'] }}</div>
        <div class="metrica-label">Matrículas</div>
        @if($datos['variacion_pct'] !== null)
          <div class="metrica-var {{ $datos['variacion_pct'] >= 0 ? 'var-pos' : 'var-neg' }}">
            {{ $datos['variacion_pct'] >= 0 ? '↑' : '↓' }} {{ abs($datos['variacion_pct']) }}%
          </div>
        @endif
      </div>
      <div class="metrica">
        <div class="metrica-num">{{ $datos['total_estudiantes_activos'] }}</div>
        <div class="metrica-label">Estudiantes activos</div>
      </div>
      <div class="metrica" style="background:#F0FDF4;border-color:#BBF7D0">
        <div class="metrica-num" style="color:#166534">{{ $datos['certificados_emitidos'] }}</div>
        <div class="metrica-label">Certificados</div>
      </div>
      <div class="metrica" style="background:#FFF7ED;border-color:#FED7AA">
        <div class="metrica-num" style="color:#C2410C">{{ $datos['mes_anterior_total'] }}</div>
        <div class="metrica-label">Mes anterior</div>
      </div>
    </div>

    {{-- Top cursos --}}
    @if(count($datos['top_cursos']) > 0)
    <p class="section-title">Top {{ count($datos['top_cursos']) }} cursos del mes</p>
    <table>
      <thead><tr><th>#</th><th>Curso</th><th style="text-align:right">Matrículas</th></tr></thead>
      <tbody>
        @foreach($datos['top_cursos'] as $i => $c)
        <tr>
          <td style="color:#9CA3AF;width:30px">{{ $i + 1 }}</td>
          <td>{{ $c['curso'] }}</td>
          <td style="text-align:right;font-weight:bold">{{ $c['total'] }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif

    {{-- Vendedores --}}
    @if(count($datos['por_vendedor']) > 0)
    <p class="section-title">Rendimiento por vendedor</p>
    <table>
      <thead><tr><th>Vendedor</th><th style="text-align:right">Matrículas</th></tr></thead>
      <tbody>
        @foreach($datos['por_vendedor'] as $v)
        <tr>
          <td>{{ $v['vendedor'] }}</td>
          <td style="text-align:right;font-weight:bold">{{ $v['total'] }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif

    {{-- Progreso estudiantes desde Moodle --}}
    @if($datos['progreso_estudiantes']['total_consultados'] > 0)
    <p class="section-title">Progreso de estudiantes activos</p>
    <div class="progreso-grid">
      <div class="prog-item prog-alto">
        <span class="prog-num">{{ $datos['progreso_estudiantes']['mas_del_50_pct'] }}</span>
        Más del 50% completado
      </div>
      <div class="prog-item prog-medio">
        <span class="prog-num">{{ $datos['progreso_estudiantes']['menos_del_50_pct'] }}</span>
        Menos del 50%
      </div>
      <div class="prog-item prog-bajo">
        <span class="prog-num">{{ $datos['progreso_estudiantes']['sin_actividad'] }}</span>
        Sin actividad reciente
      </div>
    </div>
    @if($datos['progreso_estudiantes']['sin_actividad'] > 0)
    <p style="font-size:12px;color:#6B7280;margin-top:8px">
      💡 Considera contactar a los {{ $datos['progreso_estudiantes']['sin_actividad'] }} estudiantes sin actividad para motivarlos a continuar.
    </p>
    @endif
    @endif

    {{-- Certificados del mes --}}
    @if($datos['certificados_emitidos'] > 0)
    <p class="section-title">Certificados emitidos este mes</p>
    @foreach($datos['detalle_certificados'] as $cert)
    <div class="cert-item">
      <span>🎓 {{ $cert['estudiante'] }}</span>
      <span style="color:#6B7280">{{ $cert['acta_folio'] }} · {{ $cert['fecha'] }}</span>
    </div>
    @endforeach
    @endif

  </div>

  <div class="footer">
    <p>Nomaddi · Campus Virtual · Reporte generado automáticamente el 1° de cada mes.</p>
  </div>

</div>
</body>
</html>
