{{-- resources/views/emails/liquidaciones/correo.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:0;color:#333}
  .wrap{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
  .header{background:#1D4E39;padding:28px 40px;text-align:center}
  .header h1{color:#A7F3D0;font-size:20px;margin:0 0 4px}
  .header p{color:#6EE7B7;font-size:13px;margin:0}
  .body{padding:32px 40px}
  .parrafo{font-size:14px;line-height:1.7;color:#444;margin-bottom:16px}
  .caja{background:#F0FBF6;border:1px solid #A7F3D0;border-radius:6px;padding:20px 24px;margin:20px 0}
  .caja-fila{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #D1FAE5;font-size:13px}
  .caja-fila:last-child{border-bottom:none;font-weight:bold;font-size:16px;color:#1D4E39;padding-top:10px}
  .aviso{background:#FFF7ED;border-left:4px solid #F97316;padding:14px 18px;border-radius:0 6px 6px 0;font-size:13px;color:#C2410C;margin:20px 0}
  .pasos{margin:16px 0;padding-left:20px}
  .pasos li{font-size:13px;color:#444;margin-bottom:8px;line-height:1.6}
  .footer{background:#f9f9f9;padding:16px 40px;text-align:center;font-size:11px;color:#aaa;border-top:1px solid #eee}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>Liquidación mensual lista</h1>
    <p>Período {{ str_pad($liquidacion->periodo_mes, 2,'0',STR_PAD_LEFT) }}/{{ $liquidacion->periodo_anio }}</p>
  </div>
  <div class="body">
    <p class="parrafo">Estimados,<br><strong>{{ $ietdh->nombre }}</strong></p>
    <p class="parrafo">
      Adjunto encontrarán el documento de liquidación de certificados emitidos durante el período
      <strong>{{ str_pad($liquidacion->periodo_mes,2,'0',STR_PAD_LEFT) }}/{{ $liquidacion->periodo_anio }}</strong>.
    </p>
    <div class="caja">
      @if($resumen['total_tecnicos'] > 0)
      <div class="caja-fila">
        <span>Certificados Técnicos ({{ $resumen['total_tecnicos'] }})</span>
        <span>$ {{ number_format($resumen['subtotal_tecnicos'],0,',','.') }}</span>
      </div>
      @endif
      @if($resumen['total_bachillerato'] > 0)
      <div class="caja-fila">
        <span>Certificados Bachillerato ({{ $resumen['total_bachillerato'] }})</span>
        <span>$ {{ number_format($resumen['subtotal_bachillerato'],0,',','.') }}</span>
      </div>
      @endif
      <div class="caja-fila">
        <span>TOTAL A FACTURAR</span>
        <span>$ {{ number_format($resumen['valor_total'],0,',','.') }} COP</span>
      </div>
    </div>
    <div class="aviso">
      📋 El documento de liquidación detallado está adjunto en PDF.
    </div>
    <p class="parrafo">Para gestionar el pago, por favor siga estos pasos:</p>
    <ol class="pasos">
      <li>Revise el documento adjunto con el detalle de todos los certificados.</li>
      <li>Emita su factura a nombre de <strong>Nomaddi SAS</strong> por el valor de <strong>$ {{ number_format($resumen['valor_total'],0,',','.') }} COP</strong>.</li>
      <li>Incluya en el concepto: <em>Liquidación N° {{ str_pad($liquidacion->id,6,'0',STR_PAD_LEFT) }}</em>.</li>
      <li>Envíe la factura a <strong>facturacion@nomaddi.co</strong>.</li>
    </ol>
    <p class="parrafo" style="font-size:12px;color:#888;">
      El pago se realizará dentro de los 10 días hábiles siguientes a la recepción de la factura.
    </p>
  </div>
  <div class="footer">
    <p>Nomaddi SAS · Villavicencio, Meta · facturacion@nomaddi.co</p>
    <p>Este correo fue generado automáticamente. No responder a este mensaje.</p>
  </div>
</div>
</body>
</html>
