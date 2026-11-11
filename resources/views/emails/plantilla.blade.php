<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:0;background:#000000">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#000000;padding:28px 12px">
<tr><td align="center">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#0c0c0f;border:1px solid #2a2a33;border-radius:16px">
<tr><td style="padding:26px 28px 6px;font-family:Segoe UI,Arial,sans-serif">
    <div style="font-size:12px;letter-spacing:2px;font-weight:700;color:#B026FF">SMARTCAMPUS AI</div>
    <h1 style="margin:10px 0 16px;font-size:22px;color:#ffffff">{{ $titulo }}</h1>
    <p style="margin:0 0 14px;font-size:15px;color:#ffffff">{{ $saludo }}</p>
    @foreach ($parrafos as $parrafo)
        <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#c9cedb">{{ $parrafo }}</p>
    @endforeach
    @if ($botonTexto && $botonUrl)
        <p style="margin:22px 0"><a href="{{ $botonUrl }}" style="display:inline-block;background:#00FF66;color:#000000;font-weight:700;font-size:15px;text-decoration:none;padding:13px 26px;border-radius:10px">{{ $botonTexto }}</a></p>
        <p style="margin:0 0 14px;font-size:12px;line-height:1.5;color:#8c94a3">Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
            <a href="{{ $botonUrl }}" style="color:#00FF66;word-break:break-all">{{ $botonUrl }}</a></p>
    @endif
</td></tr>
<tr><td style="padding:14px 28px 24px;font-family:Segoe UI,Arial,sans-serif;border-top:1px solid #2a2a33">
    <p style="margin:0;font-size:12px;line-height:1.5;color:#8c94a3">{{ $pie }}</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
