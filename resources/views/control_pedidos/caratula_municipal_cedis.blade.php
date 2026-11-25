<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Carátula municipal</title>
    <style>
        @page { margin: 12mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111;
            margin: 0;
        }
        table.pagina { width: 100%; border-collapse: collapse; }
        table.pagina td { vertical-align: middle; padding: 8mm; }
        .bloque { text-align: center; }
        .nombre {
            font-size: 32pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            line-height: 1.15;
            word-wrap: break-word;
            margin: 0 0 10pt 0;
        }
        .telefono {
            font-size: 22pt;
            font-weight: bold;
            margin: 0 0 12pt 0;
        }
        .transporte {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 12pt 0;
        }
        .cobro {
            font-size: 20pt;
            font-weight: bold;
            text-decoration: underline;
            letter-spacing: 0.14em;
            margin: 0;
        }
        .ine { text-align: center; }
        .ine img { max-width: 100%; max-height: 170mm; }
        .sin-id { height: 250mm; }
        .sin-id td { height: 125mm; }
        .con-id { height: 190mm; }
        .con-id .texto { width: 48%; }
        .con-id .ine { width: 52%; }
    </style>
</head>
<body>
@php
    $nombre = mb_strtoupper((string) ($caratula['destinatario_nombre'] ?? '—'), 'UTF-8');
    $telefono = (string) ($caratula['destinatario_telefono'] ?? '—');
    $transporte = (string) ($caratula['transporte'] ?? '—');
    $cobro = (($caratula['modalidad_cobro'] ?? 'PAGADO') === 'POR_COBRAR') ? 'POR COBRAR' : 'PAGADO';
    $variante = $caratula['variante'] ?? 'sin_identificacion';
@endphp
@if($variante === 'con_identificacion')
    <table class="pagina con-id">
        <tr>
            <td class="texto">
                <div class="bloque">
                    <p class="nombre">{{ $nombre }}</p>
                    <p class="telefono">CEL: {{ $telefono }}</p>
                    <p class="transporte">{{ $transporte }}</p>
                    <p class="cobro">{{ $cobro }}</p>
                </div>
            </td>
            <td class="ine">
                @if(!empty($caratula['identificacion_data_uri']))
                    <img src="{{ $caratula['identificacion_data_uri'] }}" alt="">
                @endif
            </td>
        </tr>
    </table>
@else
    <table class="pagina sin-id">
        <tr>
            <td>
                <div class="bloque">
                    <p class="nombre">{{ $nombre }}</p>
                    <p class="telefono">CEL: {{ $telefono }}</p>
                    <p class="transporte">{{ $transporte }}</p>
                    <p class="cobro">{{ $cobro }}</p>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="bloque">
                    <p class="nombre">{{ $nombre }}</p>
                    <p class="telefono">CEL: {{ $telefono }}</p>
                    <p class="transporte">{{ $transporte }}</p>
                    <p class="cobro">{{ $cobro }}</p>
                </div>
            </td>
        </tr>
    </table>
@endif
</body>
</html>
