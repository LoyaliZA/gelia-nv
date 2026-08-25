<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Carátula {{ $caratula['folio'] ?? '' }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111;
            font-size: 12pt;
            margin: 0;
        }
        .municipio {
            font-size: 28pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            line-height: 1.15;
            word-wrap: break-word;
            margin: 0 0 18pt 0;
        }
        .destinatario {
            font-size: 18pt;
            font-weight: bold;
            line-height: 1.2;
            word-wrap: break-word;
            margin: 0 0 8pt 0;
        }
        .telefono {
            font-size: 14pt;
            margin: 0 0 16pt 0;
        }
        .meta {
            font-size: 11pt;
            margin: 4pt 0;
        }
        .cobro {
            display: inline-block;
            border: 2px solid #111;
            padding: 6pt 12pt;
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 0.08em;
            margin: 14pt 0;
        }
        .pie {
            margin-top: 28pt;
            font-size: 9pt;
            border-top: 1px solid #333;
            padding-top: 8pt;
        }
    </style>
</head>
<body>
    <p class="municipio">{{ $caratula['municipio_destino'] ?? '—' }}</p>
    <p class="destinatario">{{ $caratula['destinatario_nombre'] ?? '—' }}</p>
    <p class="telefono">Tel. {{ $caratula['destinatario_telefono'] ?? '—' }}</p>
    <p class="meta">Transporte: {{ $caratula['transporte'] ?? '—' }}</p>
    <p class="cobro">{{ ($caratula['modalidad_cobro'] ?? 'PAGADO') === 'POR_COBRAR' ? 'POR COBRAR' : 'PAGADO' }}</p>
    @if(!empty($caratula['direccion_referencia']))
        <p class="meta">Ref.: {{ $caratula['direccion_referencia'] }}</p>
    @endif
    <p class="meta">Folio: {{ $caratula['folio'] ?? '—' }}</p>
    <div class="pie">
        GELIA · Carátula v{{ $caratula['version'] ?? 1 }} · {{ $caratula['fecha'] ?? '' }}
    </div>
</body>
</html>
