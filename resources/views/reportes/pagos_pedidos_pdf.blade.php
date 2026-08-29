<!DOCTYPE html>
<html lang="es">
@php($encabezado = $encabezado ?? [])
<head>
    <meta charset="utf-8">
    <title>{{ $encabezado['titulo'] ?? 'Reporte Pagos de Pedidos' }}</title>
    <style>
        @page {
            margin: 1.4cm 1.2cm 1.6cm 1.2cm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #1a1a1a;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }

        /* ── Encabezado administrativo ── */
        .admin-header {
            padding: 14px 16px 12px;
            margin-bottom: 0;
            border-radius: 4px;
        }

        .admin-header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .brand-name {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .brand-sub {
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #737373;
            margin-top: 3px;
        }

        .doc-kicker {
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #a3a3a3;
            margin-bottom: 4px;
        }

        .doc-title {
            font-size: 13px;
            font-weight: bold;
            color: #171717;
            margin: 0;
            line-height: 1.2;
        }

        .admin-meta-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-cell {
            width: 50%;
            vertical-align: top;
            padding: 5px 10px 5px 0;
        }

        .meta-cell:nth-child(even) {
            padding-right: 0;
            padding-left: 10px;
        }

        .meta-label {
            display: block;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #525252;
            margin-bottom: 2px;
        }

        .meta-value {
            display: block;
            font-size: 9px;
            font-weight: bold;
            color: #171717;
        }

        .meta-badge {
            color: #be185d;
        }

        .admin-header-rule {
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, #ec4899 0%, #fbcfe8 45%, #e5e5e5 100%);
            margin-bottom: 12px;
        }

        /* ── Parámetros aplicados ── */
        .params-block {
            margin-bottom: 14px;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            overflow: hidden;
        }

        .params-title {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #525252;
            margin: 0;
            padding: 6px 10px;
            background: #fafafa;
            border-bottom: 1px solid #e5e5e5;
        }

        .params-table {
            width: 100%;
            border-collapse: collapse;
        }

        .params-table th {
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #737373;
            text-align: left;
            padding: 4px 10px;
            background: #f5f5f5;
            border-bottom: 1px solid #e5e5e5;
        }

        .params-table td {
            font-size: 8.5px;
            padding: 4px 10px;
            border-bottom: 1px solid #eeeeee;
            vertical-align: top;
        }

        .params-table tr:last-child td {
            border-bottom: none;
        }

        .params-key {
            width: 32%;
            font-weight: bold;
            color: #404040;
        }

        .params-val {
            color: #171717;
        }

        /* ── Resumen del periodo ── */
        .resumen-block {
            margin-bottom: 14px;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            overflow: hidden;
        }

        .resumen-title {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #525252;
            margin: 0;
            padding: 6px 10px;
            background: #fafafa;
            border-bottom: 1px solid #e5e5e5;
        }

        .kpi-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .kpi-cell {
            width: 33.33%;
            vertical-align: top;
            padding: 8px 10px;
            border-right: 1px solid #eeeeee;
            border-bottom: 1px solid #eeeeee;
        }

        .kpi-cell:nth-child(3n) {
            border-right: none;
        }

        .kpi-cell--empty {
            background: #fafafa;
        }

        .kpi-label {
            display: block;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: #737373;
            margin-bottom: 3px;
            line-height: 1.25;
        }

        .kpi-value {
            display: block;
            font-size: 11px;
            font-weight: bold;
            color: #171717;
            line-height: 1.2;
        }

        .kpi-alcance {
            display: block;
            font-size: 6.5px;
            font-weight: bold;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            color: #be185d;
            margin-top: 3px;
        }

        .info-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #fdf2f8;
        }

        .info-cell {
            width: 25%;
            vertical-align: top;
            padding: 6px 10px;
            border-right: 1px solid #fbcfe8;
        }

        .info-cell:last-child {
            border-right: none;
        }

        .info-label {
            display: block;
            font-size: 6.5px;
            font-weight: bold;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            color: #9d174d;
            margin-bottom: 2px;
            line-height: 1.25;
        }

        .info-value {
            display: block;
            font-size: 9px;
            font-weight: bold;
            color: #831843;
        }

        .resumen-nota {
            font-size: 7px;
            color: #737373;
            margin: 0;
            padding: 5px 10px 6px;
            border-top: 1px solid #eeeeee;
            line-height: 1.35;
        }

        /* ── Resumen por día ── */
        .dia-block {
            margin-top: 16px;
            margin-bottom: 10px;
            padding: 8px 10px 7px;
            border-left: 3px solid #ec4899;
            background: #fafafa;
            page-break-inside: avoid;
        }

        .dia-fecha {
            font-size: 11px;
            font-weight: bold;
            color: #171717;
            margin: 0;
            line-height: 1.25;
        }

        .dia-meta {
            font-size: 8.5px;
            font-weight: bold;
            color: #525252;
            margin: 4px 0 0;
            line-height: 1.35;
        }

        .dia-meta--alerta {
            color: #92400e;
        }

        .dia-alcance {
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            color: #be185d;
        }

        /* ── Ficha por pedido ── */
        .pedido-ficha {
            margin-bottom: 12px;
            border: 1px solid #e5e5e5;
            border-left: 3px solid #ec4899;
            border-radius: 4px;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .pedido-meta-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .pedido-meta-cell {
            width: 50%;
            vertical-align: top;
            padding: 6px 10px;
            border-bottom: 1px solid #eeeeee;
        }

        .pedido-meta-cell:nth-child(odd) {
            border-right: 1px solid #eeeeee;
        }

        .pedido-meta-label {
            display: block;
            font-size: 6.5px;
            font-weight: bold;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: #737373;
            margin-bottom: 2px;
        }

        .pedido-meta-valor {
            display: block;
            font-size: 8.5px;
            font-weight: bold;
            color: #171717;
            line-height: 1.3;
        }

        .pedido-badges {
            padding: 5px 10px;
            background: #fafafa;
            border-bottom: 1px solid #eeeeee;
        }

        .pedido-badge {
            display: inline-block;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            padding: 2px 6px;
            border-radius: 10px;
            margin-right: 6px;
        }

        .pedido-badge--cierre {
            background: #f5f5f5;
            color: #404040;
            border: 1px solid #d4d4d4;
        }

        .pedido-badge--cobertura {
            background: #fdf2f8;
            color: #be185d;
            border: 1px solid #fbcfe8;
        }

        .pedido-bloques {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .pedido-bloque {
            width: 50%;
            vertical-align: top;
            padding: 8px 10px 6px;
        }

        .pedido-bloque:first-child {
            border-right: 1px solid #eeeeee;
        }

        .pedido-bloque-titulo {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #404040;
            margin: 0 0 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e5e5e5;
        }

        .pedido-fin-fila {
            display: table;
            width: 100%;
            padding: 3px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .pedido-fin-fila:last-child {
            border-bottom: none;
        }

        .pedido-fin-fila--destacado .pedido-fin-label,
        .pedido-fin-fila--destacado .pedido-fin-valor {
            font-weight: bold;
        }

        .pedido-fin-label {
            display: table-cell;
            font-size: 8px;
            color: #525252;
            width: 58%;
        }

        .pedido-fin-valor {
            display: table-cell;
            font-size: 8.5px;
            font-weight: bold;
            color: #171717;
            text-align: right;
            width: 42%;
        }

        .pedido-fin-valor--exito { color: #059669; }
        .pedido-fin-valor--advertencia { color: #b45309; }
        .pedido-fin-valor--critico { color: #dc2626; }
        .pedido-fin-valor--info { color: #0369a1; }

        .pedido-fin-alcance {
            display: block;
            font-size: 6px;
            font-weight: bold;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            color: #be185d;
            margin-top: 1px;
        }

        /* ── Exhibiciones ── */
        .exhibiciones-block {
            margin-bottom: 10px;
        }

        .exhibiciones-titulo {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #404040;
            margin: 0 0 4px;
        }

        .exhibiciones-contexto {
            font-size: 7.5px;
            color: #525252;
            margin: 0 0 3px;
            padding: 5px 8px;
            background: #fdf2f8;
            border: 1px solid #fbcfe8;
            line-height: 1.35;
        }

        .exhibiciones-nota {
            font-size: 7px;
            color: #737373;
            margin: 0 0 5px;
            line-height: 1.3;
        }

        table.data-exhibiciones th,
        table.data-exhibiciones td {
            font-size: 6.5px;
            padding: 2px 3px;
            vertical-align: top;
        }

        table.data-exhibiciones .mono {
            font-family: DejaVu Sans Mono, monospace;
        }

        .cobertura-exito { color: #059669; font-weight: bold; }
        .cobertura-info { color: #0369a1; font-weight: bold; }
        .cobertura-neutro { color: #737373; font-weight: bold; }

        /* ── Anexo A ── */
        .anexo-section {
            page-break-before: always;
            margin-top: 8px;
        }

        .anexo-titulo {
            font-size: 12px;
            font-weight: bold;
            color: #171717;
            margin: 0 0 10px;
            padding-bottom: 4px;
            border-bottom: 2px solid #ec4899;
        }

        .anexo-subtitulo {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #404040;
            margin: 0 0 4px;
        }

        .anexo-nota {
            font-size: 7px;
            color: #737373;
            margin: 0 0 6px;
            line-height: 1.35;
        }

        .anexo-remisiones {
            margin-bottom: 14px;
        }

        table.anexo-remisiones-table th,
        table.anexo-remisiones-table td {
            font-size: 7.5px;
        }

        .anexo-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }

        .anexo-grid-cell {
            width: 50%;
            vertical-align: top;
            padding: 0 4px 8px 0;
        }

        .anexo-grid-cell:nth-child(2) {
            padding: 0 0 8px 4px;
        }

        .anexo-grid-cell--empty {
            background: transparent;
        }

        .anexo-pagina-completa {
            page-break-inside: avoid;
            margin-bottom: 8px;
        }

        .anexo-pagina-completa--salto,
        .anexo-grid--salto {
            page-break-before: always;
        }

        .anexo-voucher-card {
            border: 1px solid #d4d4d4;
            border-radius: 3px;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .anexo-voucher-header {
            background: #fafafa;
            border-bottom: 1px solid #e5e5e5;
            padding: 5px 6px;
        }

        .anexo-voucher-meta {
            width: 100%;
            border-collapse: collapse;
        }

        .anexo-voucher-meta td {
            width: 50%;
            vertical-align: top;
            padding: 2px 4px 3px 0;
            font-size: 6.5px;
        }

        .anexo-meta-label {
            display: block;
            font-weight: bold;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            color: #737373;
            margin-bottom: 1px;
        }

        .anexo-meta-valor {
            display: block;
            font-weight: bold;
            color: #171717;
            line-height: 1.25;
        }

        .anexo-voucher-body {
            padding: 6px;
            text-align: center;
            background: #fff;
        }

        .anexo-voucher-img {
            display: block;
            margin: 0 auto;
            border: 1px solid #d4d4d4;
        }

        .anexo-voucher-img--medio {
            max-width: 100%;
            max-height: 300px;
            width: auto;
            height: auto;
        }

        .anexo-voucher-img--completo {
            max-width: 100%;
            max-height: 640px;
            width: auto;
            height: auto;
        }

        .anexo-voucher-pdf {
            font-size: 7.5px;
            color: #525252;
            margin: 8px 4px;
            line-height: 1.4;
            text-align: left;
        }

        /* ── Contenido ── */
        h2 { font-size: 11px; margin: 12px 0 4px; border-bottom: 1px solid #d4d4d4; color: #171717; }
        h3 { font-size: 10px; margin: 8px 0 4px; color: #262626; }
        .muted { color: #525252; font-size: 8px; }
        table.data { width: 100%; border-collapse: collapse; margin: 4px 0 8px; }
        table.data th, table.data td { border: 1px solid #d4d4d4; padding: 3px 4px; text-align: left; font-size: 8px; }
        table.data th { background: #f5f5f5; color: #404040; font-weight: bold; }
        .right { text-align: right; }
        .badge-hist { color: #b45309; font-size: 7px; }

        .pdf-footer-note {
            font-size: 7px;
            color: #737373;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    @include('reportes.partials.pagos_pedidos_pdf_encabezado', ['encabezado' => $encabezado])

    @include('reportes.partials.pagos_pedidos_pdf_parametros', ['parametros_aplicados' => $parametros_aplicados ?? []])

    @include('reportes.partials.pagos_pedidos_pdf_resumen', ['resumen_periodo' => $resumen_periodo ?? []])

    @foreach ($dias as $dia)
        @include('reportes.partials.pagos_pedidos_pdf_dia', ['dia' => $dia])
        @foreach ($dia['pedidos'] as $pedido)
            @include('reportes.partials.pagos_pedidos_pdf_pedido', ['pedido' => $pedido])
        @endforeach
    @endforeach

    @include('reportes.partials.pagos_pedidos_pdf_anexo', ['anexo' => $anexo ?? []])
</body>
</html>
