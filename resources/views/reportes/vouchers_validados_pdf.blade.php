<!DOCTYPE html>
<html lang="es">
@php($encabezado = $encabezado ?? [])
<head>
    <meta charset="utf-8">
    <title>{{ $encabezado['titulo'] ?? 'Vouchers validados' }}</title>
    <style>
        @page { margin: 1.4cm 1.2cm 1.6cm 1.2cm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; line-height: 1.45; margin: 0; }
        .admin-header { padding: 14px 16px 12px; border-radius: 4px; }
        .admin-header-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .brand-name { font-size: 14px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
        .brand-sub { font-size: 7px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; color: #737373; margin-top: 3px; }
        .doc-kicker { font-size: 7px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: #a3a3a3; margin-bottom: 4px; }
        .doc-title { font-size: 13px; font-weight: bold; color: #171717; margin: 0; }
        .admin-meta-grid { width: 100%; border-collapse: collapse; }
        .meta-cell { width: 50%; vertical-align: top; padding: 5px 10px 5px 0; }
        .meta-label { display: block; font-size: 7px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #525252; margin-bottom: 2px; }
        .meta-value { display: block; font-size: 9px; font-weight: bold; color: #171717; }
        .meta-badge { color: #be185d; }
        .admin-header-rule { width: 100%; height: 2px; background: linear-gradient(90deg, #ec4899 0%, #fbcfe8 45%, #e5e5e5 100%); margin-bottom: 12px; }
        .params-block { margin-bottom: 14px; border: 1px solid #e5e5e5; border-radius: 4px; overflow: hidden; }
        .params-title { font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; padding: 8px 12px; background: #fafafa; border-bottom: 1px solid #e5e5e5; }
        .params-table { width: 100%; border-collapse: collapse; }
        .params-table td { padding: 5px 12px; border-bottom: 1px solid #f0f0f0; font-size: 8px; }
        .params-table td:first-child { color: #525252; width: 35%; }
        .metricas-grid { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .metricas-grid td { padding: 6px 8px; border: 1px solid #e5e5e5; font-size: 8px; }
        .metricas-label { font-weight: bold; color: #525252; }
        .metricas-value { font-weight: bold; text-align: right; }
        .grupo-titulo { font-size: 10px; font-weight: bold; margin: 14px 0 6px; color: #171717; }
        .grupo-meta { font-size: 8px; color: #525252; margin-bottom: 6px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data th { background: #fafafa; font-size: 7px; font-weight: bold; text-transform: uppercase; padding: 5px 4px; border: 1px solid #e5e5e5; text-align: left; }
        table.data td { font-size: 8px; padding: 4px; border: 1px solid #e5e5e5; vertical-align: top; }
        .importe { text-align: right; font-variant-numeric: tabular-nums; }
        .anexo-section { page-break-before: always; }
        .anexo-titulo { font-size: 11px; font-weight: bold; margin-bottom: 10px; }
    </style>
</head>
<body>
@include('reportes.partials.pagos_pedidos_pdf_encabezado')

@if (! empty($parametros_aplicados))
    <div class="params-block">
        <div class="params-title">Parámetros aplicados</div>
        <table class="params-table">
            @foreach ($parametros_aplicados as $row)
                <tr>
                    <td>{{ $row['parametro'] }}</td>
                    <td>{{ $row['seleccion'] }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif

@php($m = $metricas ?? [])
<table class="metricas-grid">
    <tr>
        <td class="metricas-label">Total validado para bancos</td>
        <td class="metricas-value">${{ $m['total_ingreso_bancario'] ?? '0.00' }}</td>
        <td class="metricas-label">Vouchers validados</td>
        <td class="metricas-value">{{ number_format((int) ($m['vouchers_validados'] ?? 0)) }}</td>
    </tr>
    <tr>
        <td class="metricas-label">Pedidos relacionados</td>
        <td class="metricas-value">{{ number_format((int) ($m['pedidos_relacionados'] ?? 0)) }}</td>
        <td class="metricas-label">Bancos involucrados</td>
        <td class="metricas-value">{{ number_format((int) ($m['bancos_involucrados'] ?? 0)) }}</td>
    </tr>
    <tr>
        <td class="metricas-label">Reportados posteriormente</td>
        <td class="metricas-value">{{ number_format((int) ($m['reportados_posteriormente'] ?? 0)) }}</td>
        <td class="metricas-label">Posibles duplicados</td>
        <td class="metricas-value">{{ number_format((int) ($m['posibles_duplicados'] ?? 0)) }}</td>
    </tr>
    <tr>
        <td class="metricas-label">Total SAF relacionado</td>
        <td class="metricas-value">${{ $m['total_saf_relacionado'] ?? '0.00' }}</td>
        <td class="metricas-label">Con observaciones</td>
        <td class="metricas-value">{{ number_format((int) ($m['con_observaciones'] ?? 0)) }}</td>
    </tr>
</table>

@foreach ($grupos ?? [] as $grupo)
    <div class="grupo-block">
        <div class="grupo-titulo">{{ $grupo['label'] ?? '—' }}</div>
        <div class="grupo-meta">
            {{ $grupo['resumen']['vouchers'] ?? 0 }} vouchers ·
            Total validado ${{ $grupo['resumen']['total_validado'] ?? '0.00' }} ·
            {{ $grupo['resumen']['pedidos'] ?? 0 }} pedidos
        </div>
        <table class="data">
            <thead>
                <tr>
                    <th>Exhib.</th>
                    <th>Pedido</th>
                    <th>Remisión</th>
                    <th>Cliente</th>
                    <th class="importe">Monto</th>
                    <th>Forma</th>
                    <th>Banco</th>
                    <th>Referencia</th>
                    <th>Movimiento</th>
                    <th>Estado</th>
                    <th>Indicadores</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($grupo['exhibiciones'] ?? [] as $ex)
                    <tr>
                        <td>#{{ $ex['numero_exhibicion'] }}</td>
                        <td>{{ $ex['folio_pedido'] ?? '—' }}</td>
                        <td>{{ $ex['folio_remision'] ?? '—' }}</td>
                        <td>{{ $ex['cliente']['nombre'] ?? '—' }}</td>
                        <td class="importe">${{ number_format((float) ($ex['monto'] ?? 0), 2) }}</td>
                        <td>{{ $ex['forma_pago_label'] ?? '—' }}</td>
                        <td>{{ $ex['banco'] ?? '—' }}</td>
                        <td>{{ $ex['referencia'] ?? '—' }}</td>
                        <td>{{ $ex['fecha_pago_label'] ?? '—' }}</td>
                        <td>{{ $ex['estado_validacion_label'] ?? '—' }}</td>
                        <td>
                            @if ($ex['reportado_posteriormente']) Posterior; @endif
                            @if ($ex['posible_duplicado']) Duplicado; @endif
                            @if ($ex['con_saf_relacionado']) SAF; @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach

@if (! empty($anexo) && ! ($anexo['vacio'] ?? true))
    @include('reportes.partials.pagos_pedidos_pdf_anexo', ['anexo' => $anexo])
@endif
</body>
</html>
