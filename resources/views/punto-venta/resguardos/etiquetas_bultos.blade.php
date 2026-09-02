<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Etiquetas resguardo PDV</title>
    <style>
        @page {
            size: {{ $ancho_mm }}mm {{ $alto_mm }}mm;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #111;
        }

        .etiqueta {
            width: {{ $ancho_mm }}mm;
            height: {{ $alto_mm }}mm;
            page-break-after: always;
            display: table;
            border: 0.2mm solid #ccc;
        }

        .etiqueta:last-child {
            page-break-after: auto;
        }

        .contenido {
            display: table-row;
            height: {{ $alto_mm }}mm;
        }

        .texto, .qr {
            display: table-cell;
            vertical-align: middle;
            padding: 2mm;
        }

        .texto {
            width: 58%;
        }

        .qr {
            width: 42%;
            text-align: center;
        }

        .folio {
            font-size: 9pt;
            font-weight: bold;
            margin: 0 0 1mm;
            word-break: break-word;
        }

        .meta {
            font-size: 7pt;
            margin: 0 0 0.5mm;
            color: #333;
        }

        .codigo {
            font-size: 6.5pt;
            margin: 1mm 0 0;
            letter-spacing: 0.3pt;
            color: #555;
        }

        .qr img {
            width: 22mm;
            height: 22mm;
        }
    </style>
</head>
<body>
@foreach ($items as $item)
    <div class="etiqueta">
        <div class="contenido">
            <div class="texto">
                <p class="folio">{{ $item['folio'] }}</p>
                <p class="meta">{{ strtoupper($item['tipo']) }}</p>
                <p class="meta">{{ $item['referencia_pedido'] }}</p>
                <p class="codigo">{{ $item['codigo_etiqueta'] }}</p>
            </div>
            <div class="qr">
                <img src="data:image/png;base64,{{ $item['qr_base64'] }}" alt="QR">
            </div>
        </div>
    </div>
@endforeach
</body>
</html>
