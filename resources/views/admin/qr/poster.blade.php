<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cartel de acceso — imprimir</title>
    <style>
        /* Estilos embebidos y no Tailwind: este cartel se imprime, y una
           hoja externa que no cargue dejaria el cartel roto en la impresora
           sin que nadie lo note hasta tenerlo pegado en el aula. */
        @page { size: A4 portrait; margin: 18mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            text-align: center;
        }

        .no-print { margin-bottom: 16px; }

        @media print { .no-print { display: none; } }

        h1 { margin: 0 0 4px; font-size: 44px; line-height: 1.1; }

        .sub { margin: 0 0 28px; font-size: 22px; color: #475569; }

        .qr { display: inline-block; padding: 14px; border: 3px solid #0f172a; border-radius: 12px; }
        .qr svg { display: block; width: 320px; height: 320px; }

        .steps {
            margin: 30px auto 0;
            max-width: 460px;
            text-align: left;
            font-size: 19px;
            line-height: 1.6;
        }
        .steps li { margin-bottom: 6px; }

        .url { margin-top: 26px; font-family: ui-monospace, monospace; font-size: 12px; color: #94a3b8; word-break: break-all; }

        .foot { margin-top: 10px; font-size: 14px; color: #64748b; }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()"
            style="padding:10px 18px;font-size:14px;border-radius:6px;border:0;background:#0f172a;color:#fff;cursor:pointer">
        Imprimir
    </button>
    <p style="font-size:13px;color:#64748b">Este mismo cartel sirve para todas las aulas del piloto.</p>
</div>

<h1>¿Problemas con el equipo del aula?</h1>
<p class="sub">Escanea este código con tu celular</p>

<div class="qr">{!! $svg !!}</div>

<ol class="steps">
    <li>Escanea el código con la cámara.</li>
    <li>Indica en qué aula estás.</li>
    <li>Dinos qué problema tienes.</li>
    <li>Sigue los pasos que aparecen en pantalla.</li>
    <li>Si no se soluciona, pide apoyo técnico desde ahí mismo.</li>
</ol>

<p class="foot">No necesitas usuario ni contraseña.</p>

<p class="url">{{ $targetUrl }}</p>

</body>
</html>
