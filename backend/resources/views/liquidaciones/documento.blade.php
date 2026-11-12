{{-- resources/views/liquidaciones/documento.blade.php --}}
{{-- Usado tanto para el correo HTML como para generar el PDF adjunto --}}
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 13px; color: #333; margin: 0; padding: 0; }
  .wrap { max-width: 760px; margin: 0 auto; padding: 32px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 2px solid #1D9E75; }
  .header-izq h1 { font-size: 18px; color: #1D4E39; margin: 0 0 4px; }
  .header-izq p { font-size: 11px; color: #888; margin: 2px 0; }
  .header-der { text-align: right; }
  .header-der .num-liq { font-size: 22px; font-weight: bold; color: #1D9E75; }
  .header-der p { font-size: 11px; color: #888; margin: 2px 0; }
  .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .06em; color: #888; margin: 24px 0 10px; }
  .datos-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; margin-bottom: 24px; }
  .dato { padding: 8px 12px; background: #f9f9f9; border-radius: 4px; border: 1px solid #eee; }
  .dato-label { font-size: 10px; color: #999; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 3px; }
  .dato-valor { font-size: 14px; font-weight: bold; color: #222; }
  table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 24px; }
  thead th { background: #1D4E39; color: #fff; padding: 8px 10px; text-align: left; font-weight: 500; font-size: 11px; }
  tbody td { padding: 7px 10px; border-bottom: 1px solid #f0f0f0; }
  tbody tr:nth-child(even) td { background: #fafafa; }
  .badge { display: inline-block; font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 10px; }
  .badge-tec { background: #EEF6FF; color: #1D4ED8; }
  .badge-bach { background: #FFF7ED; color: #C2410C; }
  .resumen { background: #F0FBF6; border: 1px solid #A7F3D0; border-radius: 6px; padding: 20px 24px; margin-bottom: 24px; }
  .resumen-fila { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #D1FAE5; font-size: 13px; }
  .resumen-fila:last-child { border-bottom: none; }
  .resumen-fila.total { font-size: 16px; font-weight: bold; color: #1D4E39; padding-top: 12px; margin-top: 4px; border-top: 2px solid #1D9E75; border-bottom: none; }
  .instrucciones { background: #FFF7ED; border: 1px solid #FED7AA; border-radius: 6px; padding: 16px 20px; margin-bottom: 24px; }
  .instrucciones h3 { font-size: 13px; color: #C2410C; margin: 0 0 8px; }
  .instrucciones p, .instrucciones li { font-size: 12px; color: #666; line-height: 1.6; }
  .instrucciones ul { margin: 6px 0 0 16px; }
  .firma-area { margin-top: 40px; display: flex; justify-content: space-between; }
  .firma-bloque { text-align: center; }
  .firma-linea { border-top: 1px solid #333; width: 200px; margin: 0 auto 6px; }
  .firma-nombre { font-size: 12px; font-weight: bold; }
  .firma-cargo { font-size: 11px; color: #888; }
  .footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid #eee; font-size: 10px; color: #aaa; text-align: center; line-height: 1.6; }
</style>
</head>
<body>
<div class="wrap">

  {{-- Encabezado --}}
  <div class="header">
    <div class="header-izq">
      <h1>Nomaddi SAS</h1>
      <p>NIT: [NIT de Nomaddi]</p>
      <p>Villavicencio, Meta, Colombia</p>
      <p>noreply@campusvirtual.edu.co</p>
    </div>
    <div class="header-der">
      <p style="font-size:11px;color:#888;margin-bottom:4px">Liquidación N°</p>
      <div class="num-liq">{{ str_pad($liquidacion->id, 6, '0', STR_PAD_LEFT) }}</div>
      <p>Período: {{ str_pad($liquidacion->periodo_mes, 2, '0', STR_PAD_LEFT) }}/{{ $liquidacion->periodo_anio }}</p>
      <p>Generada: {{ $liquidacion->fecha_generacion->format('d/m/Y') }}</p>
    </div>
  </div>

  {{-- Datos de la IETDH --}}
  <p class="section-title">Institución Receptora</p>
  <div class="datos-grid">
    <div class="dato">
      <p class="dato-label">Institución</p>
      <p class="dato-valor">{{ $ietdh->nombre }}</p>
    </div>
    <div class="dato">
      <p class="dato-label">NIT</p>
      <p class="dato-valor">{{ $ietdh->nit }}</p>
    </div>
    <div class="dato">
      <p class="dato-label">Municipio</p>
      <p class="dato-valor">{{ $ietdh->municipio }}, {{ $ietdh->departamento }}</p>
    </div>
    <div class="dato">
      <p class="dato-label">Email</p>
      <p class="dato-valor">{{ $ietdh->email_contacto }}</p>
    </div>
  </div>

  {{-- Detalle de certificados --}}
  <p class="section-title">Detalle de Certificados Emitidos</p>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Acta / Folio</th>
        <th>Estudiante</th>
        <th>Documento</th>
        <th>Programa</th>
        <th>Tipo</th>
        <th>Fecha</th>
        <th style="text-align:right">Fee</th>
      </tr>
    </thead>
    <tbody>
      @foreach($certificados as $i => $cert)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td><strong>{{ $cert['acta'] }}</strong>-{{ $cert['folio'] }}</td>
        <td>{{ $cert['estudiante'] }}</td>
        <td>{{ $cert['documento'] }}</td>
        <td>{{ $cert['programa'] }}</td>
        <td>
          @if($cert['tipo_programa'] === 'TECNICOS')
            <span class="badge badge-tec">Técnico</span>
          @else
            <span class="badge badge-bach">Bachillerato</span>
          @endif
        </td>
        <td>{{ $cert['fecha_emision'] }}</td>
        <td style="text-align:right">$ {{ number_format($cert['fee_aplicado'], 0, ',', '.') }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  {{-- Resumen financiero --}}
  <p class="section-title">Resumen Financiero</p>
  <div class="resumen">
    @if($resumen['total_tecnicos'] > 0)
    <div class="resumen-fila">
      <span>Certificados Técnicos ({{ $resumen['total_tecnicos'] }} × $ {{ number_format($resumen['fee_tecnicos'], 0, ',', '.') }})</span>
      <span>$ {{ number_format($resumen['subtotal_tecnicos'], 0, ',', '.') }}</span>
    </div>
    @endif
    @if($resumen['total_bachillerato'] > 0)
    <div class="resumen-fila">
      <span>Certificados Bachillerato ({{ $resumen['total_bachillerato'] }} × $ {{ number_format($resumen['fee_bachillerato'], 0, ',', '.') }})</span>
      <span>$ {{ number_format($resumen['subtotal_bachillerato'], 0, ',', '.') }}</span>
    </div>
    @endif
    <div class="resumen-fila total">
      <span>TOTAL A PAGAR</span>
      <span>$ {{ number_format($resumen['valor_total'], 0, ',', '.') }} COP</span>
    </div>
  </div>

  {{-- Instrucciones de pago --}}
  <div class="instrucciones">
    <h3>📋 Instrucciones para el pago</h3>
    <ul>
      <li>Emite tu factura a nombre de <strong>Nomaddi SAS</strong>, NIT [NIT de Nomaddi], por el valor de <strong>$ {{ number_format($resumen['valor_total'], 0, ',', '.') }} COP</strong>.</li>
      <li>Referencia la liquidación N° <strong>{{ str_pad($liquidacion->id, 6, '0', STR_PAD_LEFT) }}</strong> en el concepto de la factura.</li>
      <li>Envía la factura a <strong>facturacion@nomaddi.co</strong>.</li>
      <li>El pago se realizará dentro de los 10 días hábiles siguientes a la recepción de la factura.</li>
    </ul>
  </div>

  {{-- Firmas --}}
  <div class="firma-area">
    <div class="firma-bloque">
      <div class="firma-linea"></div>
      <p class="firma-nombre">Nomaddi SAS</p>
      <p class="firma-cargo">Representante Legal</p>
    </div>
    <div class="firma-bloque">
      <div class="firma-linea"></div>
      <p class="firma-nombre">{{ $ietdh->nombre }}</p>
      <p class="firma-cargo">Institución Receptora</p>
    </div>
  </div>

  {{-- Pie --}}
  <div class="footer">
    <p>Documento generado automáticamente por el sistema de gestión Nomaddi · {{ now()->format('d/m/Y H:i') }}</p>
    <p>Este documento no es una factura. Es un soporte de liquidación para que la institución emita su factura correspondiente.</p>
  </div>

</div>
</body>
</html>
