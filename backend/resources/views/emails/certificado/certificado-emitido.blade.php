{{-- resources/views/emails/certificado-emitido.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; color: #333; }
  .wrap { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  .header { background: #2C1A0E; padding: 30px 40px; text-align: center; }
  .header h1 { color: #C9A84C; font-size: 22px; margin: 0 0 4px; }
  .header p { color: #a89070; font-size: 13px; margin: 0; }
  .body { padding: 36px 40px; }
  .saludo { font-size: 16px; margin-bottom: 20px; color: #2C1A0E; font-weight: bold; }
  .parrafo { font-size: 14px; line-height: 1.7; color: #444; margin-bottom: 16px; }
  .datos-box { background: #FBF7EE; border: 1px solid #E8D5A3; border-radius: 6px; padding: 20px 24px; margin: 24px 0; }
  .dato-fila { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #F0E5C8; font-size: 13px; }
  .dato-fila:last-child { border-bottom: none; }
  .dato-label { color: #888; }
  .dato-valor { font-weight: bold; color: #2C1A0E; }
  .btn-verificar { display: block; text-align: center; background: #8B6914; color: #fff !important; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-size: 14px; font-weight: bold; margin: 28px 0; letter-spacing: 0.5px; }
  .adjunto-aviso { background: #EEF6FF; border-left: 4px solid #3B82F6; padding: 14px 18px; border-radius: 0 6px 6px 0; font-size: 13px; color: #1D4ED8; margin-bottom: 24px; }
  .footer { background: #f9f9f9; padding: 20px 40px; text-align: center; font-size: 11px; color: #aaa; border-top: 1px solid #eee; }
  .footer a { color: #8B6914; text-decoration: none; }
</style>
</head>
<body>
<div class="wrap">

  {{-- Header --}}
  <div class="header">
    <h1>🎓 Tu certificado está listo</h1>
    <p>{{ $certificado->ietdh->nombre }}</p>
  </div>

  {{-- Cuerpo --}}
  <div class="body">

    <p class="saludo">
      ¡Felicitaciones, {{ $certificado->matricula->estudiante->nombres }}!
    </p>

    <p class="parrafo">
      Has completado exitosamente el programa
      <strong>{{ $certificado->matricula->curso->nombre }}</strong>
      certificado por <strong>{{ $certificado->ietdh->nombre }}</strong>.
      Encuentra tu certificado oficial adjunto en este correo.
    </p>

    {{-- Aviso del adjunto --}}
    <div class="adjunto-aviso">
      📎 Tu certificado en PDF está adjunto a este correo.
      Guárdalo en un lugar seguro.
    </div>

    {{-- Datos del certificado --}}
    <div class="datos-box">
      <div class="dato-fila">
        <span class="dato-label">Estudiante</span>
        <span class="dato-valor">
          {{ $certificado->matricula->estudiante->nombres }}
          {{ $certificado->matricula->estudiante->apellidos }}
        </span>
      </div>
      <div class="dato-fila">
        <span class="dato-label">Programa</span>
        <span class="dato-valor">{{ $certificado->matricula->curso->nombre }}</span>
      </div>
      <div class="dato-fila">
        <span class="dato-label">Institución emisora</span>
        <span class="dato-valor">{{ $certificado->ietdh->nombre }}</span>
      </div>
      <div class="dato-fila">
        <span class="dato-label">N° de Acta</span>
        <span class="dato-valor">{{ $certificado->acta->codigo_acta }}</span>
      </div>
      <div class="dato-fila">
        <span class="dato-label">N° de Folio</span>
        <span class="dato-valor">{{ $certificado->folio }}</span>
      </div>
      <div class="dato-fila">
        <span class="dato-label">Fecha de emisión</span>
        <span class="dato-valor">{{ $certificado->fecha_emision->format('d/m/Y') }}</span>
      </div>
    </div>

    <p class="parrafo">
      Cualquier persona o empresa puede verificar la autenticidad de tu certificado
      ingresando al siguiente enlace:
    </p>

    <a href="{{ config('app.url') }}/verificar/{{ $certificado->uuid_verificacion }}"
       class="btn-verificar">
      Verificar autenticidad del certificado
    </a>

    <p class="parrafo" style="font-size:12px; color:#888;">
      Código único de verificación:<br>
      <code style="font-size:11px; color:#8B6914; word-break:break-all;">
        {{ $certificado->uuid_verificacion }}
      </code>
    </p>

    <p class="parrafo">
      Si tienes alguna pregunta sobre tu certificado, comunícate con nosotros a
      <a href="mailto:{{ $certificado->ietdh->email_contacto }}" style="color:#8B6914;">
        {{ $certificado->ietdh->email_contacto }}
      </a>.
    </p>

  </div>

  {{-- Footer --}}
  <div class="footer">
    <p>{{ $certificado->ietdh->nombre }} · {{ $certificado->ietdh->municipio }}, {{ $certificado->ietdh->departamento }}</p>
    <p style="margin-top:6px">NIT: {{ $certificado->ietdh->nit }}</p>
    <p style="margin-top:10px; color:#ccc;">
      Este correo fue generado automáticamente. Por favor no respondas a este mensaje.
    </p>
  </div>

</div>
</body>
</html>
