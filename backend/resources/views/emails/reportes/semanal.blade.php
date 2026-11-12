{{-- resources/views/emails/reportes/semanal.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:0;color:#333}
  .wrap{max-width:620px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
  .header{background:#1D4E89;padding:26px 36px;text-align:center}
  .header h1{color:#BFDBFE;font-size:18px;margin:0 0 4px}
  .header p{color:#93C5FD;font-size:13px;margin:0}
  .body{padding:28px 36px}
  .saludo{font-size:15px;font-weight:bold;color:#1D4E89;margin-bottom:16px}
  .metricas{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin:20px 0}
  .metrica{background:#EFF6FF;border:1px solid #BFDBFE;border-radius:6px;padding:14px;text-align:center}
  .metrica-num{font-size:28px;font-weight:bold;color:#1D4E89;line-height:1}
  .metrica-label{font-size:11px;color:#6B7280;margin-top:4px}
  .metrica-var{font-size:11px;font-weight:bold;margin-top:6px}
  .var-pos{color:#059669} .var-neg{color:#DC2626} .var-neu{color:#9CA3AF}
  .section-title{font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:.06em;color:#9CA3AF;margin:22px 0 10px}
  table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px}
  th{background:#F3F4F6;color:#6B7280;padding:7px 10px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.04em}
  td{padding:7px 10px;border-bottom:1px solid #F3F4F6;color:#374151}
  .badge-pos{background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold}
  .badge-neg{background:#FEE2E2;color:#991B1B;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold}
  .acumulado{background:#F0FDF4;border:1px solid #BBF7D0;border-radius:6px;padding:14px 18px;margin:16px 0;font-size:13px;color:#166534}
  .acumulado strong{font-size:18px}
  .footer{background:#F9FAFB;padding:16px 36px;text-align:center;font-size:11px;color:#9CA3AF;border-top:1px solid #F3F4F6}
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <h1>📊 Reporte Semanal</h1>
    <p>{{ $datos['periodo_inicio'] }} — {{ $datos['periodo_fin'] }} · Semana {{ $datos['semana_numero'] }}</p>
  </div>

  <div class="body">
    <p class="saludo">Hola, {{ $datos['distribuidor']->nombre }}</p>

    {{-- Métricas principales --}}
    <div class="metricas">
      <div class="metrica">
        <div class="metrica-num">{{ $datos['total_matriculas'] }}</div>
        <div class="metrica-label">Matrículas esta semana</div>
        @if($datos['variacion_pct'] !== null)
          <div class="metrica-var {{ $datos['variacion_pct'] >= 0 ? 'var-pos' : 'var-neg' }}">
            {{ $datos['variacion_pct'] >= 0 ? '↑' : '↓' }} {{ abs($datos['variacion_pct']) }}% vs semana anterior
          </div>
        @endif
      </div>
      <div class="metrica">
        <div class="metrica-num">{{ $datos['semana_anterior_total'] }}</div>
        <div class="metrica-label">Semana anterior</div>
      </div>
      <div class="metrica" style="background:#F0FDF4;border-color:#BBF7D0">
        <div class="metrica-num" style="color:#166534">{{ $datos['mes_total_matriculas'] }}</div>
        <div class="metrica-label">Acumulado del mes</div>
      </div>
    </div>

    {{-- Por curso --}}
    @if(count($datos['por_curso']) > 0)
    <p class="section-title">Matrículas por curso</p>
    <table>
      <thead><tr><th>Curso</th><th style="text-align:right">Matrículas</th></tr></thead>
      <tbody>
        @foreach($datos['por_curso'] as $fila)
        <tr>
          <td>{{ $fila['curso'] }}</td>
          <td style="text-align:right;font-weight:bold">{{ $fila['total'] }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif

    {{-- Por vendedor --}}
    @if(count($datos['por_vendedor']) > 0)
    <p class="section-title">Matrículas por vendedor</p>
    <table>
      <thead><tr><th>Vendedor</th><th style="text-align:right">Matrículas</th></tr></thead>
      <tbody>
        @foreach($datos['por_vendedor'] as $fila)
        <tr>
          <td>{{ $fila['vendedor'] }}</td>
          <td style="text-align:right;font-weight:bold">{{ $fila['total'] }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif

    @if($datos['total_matriculas'] === 0)
    <div style="text-align:center;padding:30px;color:#9CA3AF;font-size:14px">
      😴 Sin matrículas esta semana.<br>
      <span style="font-size:12px">¡Esta semana es una nueva oportunidad!</span>
    </div>
    @endif

  </div>

  <div class="footer">
    <p>Nomaddi · Campus Virtual · Este reporte se genera automáticamente cada lunes.</p>
  </div>

</div>
</body>
</html>
