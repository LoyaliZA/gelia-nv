<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Cobranza — Credibox</title>
    <style>
        @page {
            margin: 1.6cm 1.4cm 1.8cm 1.4cm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5px;
            color: #1a1a1a;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }

        /* ── Encabezado ── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .brand-logos img {
            display: block;
            height: 42px;
            max-width: 115px;
        }

        .brand-divider {
            width: 1px;
            height: 38px;
            background: #d4d4d4;
            margin: 0 14px;
        }

        .gelia-mark {
            margin-top: 10px;
        }

        .gelia-mark-table td {
            vertical-align: middle;
            padding: 0;
        }

        .gelia-wordmark {
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #737373;
        }

        .doc-meta {
            text-align: right;
        }

        .doc-eyebrow {
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #a3a3a3;
            margin-bottom: 4px;
        }

        .doc-title {
            font-size: 15px;
            font-weight: bold;
            color: #171717;
            letter-spacing: 0.5px;
            margin: 0;
            line-height: 1.15;
        }

        .doc-subtitle {
            font-size: 8px;
            color: #525252;
            margin-top: 4px;
        }

        .header-rule {
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, #171717 0%, #d4d4d4 55%, transparent 100%);
            margin-bottom: 16px;
        }

        /* ── Filtros y KPIs ── */
        .meta-row {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .meta-row td {
            vertical-align: top;
            width: 50%;
            padding: 0 6px 0 0;
        }

        .meta-row td:last-child {
            padding: 0 0 0 6px;
        }

        .panel {
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            padding: 10px 12px;
            background: #fafafa;
        }

        .panel-label {
            font-size: 6.5px;
            font-weight: bold;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            color: #a3a3a3;
            margin-bottom: 6px;
        }

        .panel-line {
            font-size: 8px;
            color: #404040;
            margin-bottom: 3px;
        }

        .panel-line strong {
            color: #171717;
        }

        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-bottom: 16px;
        }

        .kpi-cell {
            width: 25%;
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            padding: 9px 8px;
            text-align: center;
            background: #fff;
        }

        .kpi-label {
            font-size: 6.5px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #a3a3a3;
            margin-bottom: 4px;
        }

        .kpi-value {
            font-size: 11px;
            font-weight: bold;
            color: #171717;
        }

        /* ── Secciones ── */
        .section-title {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #171717;
            margin: 18px 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e5e5e5;
        }

        .section-empty {
            font-size: 8px;
            color: #737373;
            font-style: italic;
            padding: 6px 0 2px;
        }

        /* ── Gráfica SVG ── */
        .chart-wrap {
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            padding: 12px 14px 10px;
            margin-bottom: 4px;
            background: #fff;
        }

        .chart-caption {
            font-size: 6.5px;
            color: #a3a3a3;
            text-align: right;
            margin-top: 4px;
        }

        /* ── Tablas ── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .data-table th {
            background: #f5f5f5;
            color: #525252;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 6px 7px;
            border-bottom: 1px solid #d4d4d4;
            text-align: left;
        }

        .data-table td {
            padding: 5px 7px;
            border-bottom: 1px solid #ededed;
            font-size: 8px;
            color: #262626;
            vertical-align: top;
        }

        .data-table tr:nth-child(even) td {
            background: #fcfcfc;
        }

        .data-table tr.total-row td {
            background: #f5f5f5;
            font-weight: bold;
            color: #171717;
            border-top: 1px solid #d4d4d4;
            border-bottom: none;
        }

        .text-right { text-align: right; }
        .text-muted { color: #737373; }

        .footer {
            margin-top: 22px;
            padding-top: 8px;
            border-top: 1px solid #e5e5e5;
            font-size: 6.5px;
            color: #a3a3a3;
            text-align: center;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

    {{-- Encabezado --}}
    <table class="header-table">
        <tr>
            <td style="width: 58%; vertical-align: top;">
                <table style="border-collapse: collapse;">
                    <tr>
                        <td style="vertical-align: middle;">
                            @if(!empty($logos['aromas']['base64']))
                                <img src="data:image/png;base64,{{ $logos['aromas']['base64'] }}" alt="{{ $logos['aromas']['alt'] }}" class="brand-logos" style="height:42px; max-width:115px;" />
                            @else
                                <span style="font-size:11px; font-weight:bold;">{{ $logos['aromas']['alt'] }}</span>
                            @endif
                        </td>
                        <td style="vertical-align: middle;">
                            <div class="brand-divider"></div>
                        </td>
                        <td style="vertical-align: middle;">
                            @if(!empty($logos['bellaroma']['base64']))
                                <img src="data:image/png;base64,{{ $logos['bellaroma']['base64'] }}" alt="{{ $logos['bellaroma']['alt'] }}" class="brand-logos" style="height:42px; max-width:115px;" />
                            @else
                                <span style="font-size:11px; font-weight:bold;">{{ $logos['bellaroma']['alt'] }}</span>
                            @endif
                        </td>
                    </tr>
                </table>

                <div class="gelia-mark">
                    <table class="gelia-mark-table">
                        <tr>
                            <td style="padding-right: 6px;">
                                @include('rh.partials.gelia_isotipo_svg', ['size' => 20, 'color' => '#1a1a1a'])
                            </td>
                            <td>
                                <div class="gelia-wordmark">GELIA NV · Credibox</div>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
            <td style="width: 42%; vertical-align: top;" class="doc-meta">
                <div class="doc-eyebrow">Reporte operativo</div>
                <h1 class="doc-title">Cobranza</h1>
                <div class="doc-subtitle">Generado {{ $fechaGeneracion }}</div>
                @if(!empty($usuarioNombre))
                    <div class="doc-subtitle">Solicitado por {{ $usuarioNombre }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="header-rule"></div>

    {{-- Filtros y resumen --}}
    <table class="meta-row">
        <tr>
            <td>
                <div class="panel">
                    <div class="panel-label">Parámetros del reporte</div>
                    <div class="panel-line"><strong>Cliente:</strong> {{ $filtrosResumen['cliente'] ?? 'Todos' }}</div>
                    <div class="panel-line"><strong>Periodo:</strong> {{ $filtrosResumen['periodo'] ?? 'Sin restricción' }}</div>
                </div>
            </td>
            <td>
                <div class="panel">
                    <div class="panel-label">Alcance</div>
                    <div class="panel-line"><strong>Folios pendientes:</strong> {{ count($facturas) }}</div>
                    <div class="panel-line"><strong>Abonos registrados:</strong> {{ count($abonos) }}</div>
                    <div class="panel-line"><strong>Eventos en bitácora:</strong> {{ count($bitacora) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-label">Deuda total</div>
                <div class="kpi-value">${{ number_format($resumen['deuda_total'], 2) }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Abonos</div>
                <div class="kpi-value">${{ number_format($resumen['total_abonos'], 2) }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Clientes con deuda</div>
                <div class="kpi-value">{{ $resumen['clientes_con_deuda'] }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Folios vencidos</div>
                <div class="kpi-value">{{ $resumen['folios_vencidos'] }}</div>
            </td>
        </tr>
    </table>

    {{-- Gráfica Top 5 --}}
    @if(!empty($graficaDeuda))
        <div class="section-title">Concentración de deuda — Top {{ count($graficaDeuda['labels']) }}</div>
        <div class="chart-wrap">
            @php
                $maxValor = max($graficaDeuda['values']) ?: 1;
                $chartW = 480;
                $chartH = 28 * count($graficaDeuda['labels']) + 20;
                $barMaxW = 260;
                $labelW = 145;
                $rowH = 28;
                $startY = 14;
            @endphp
            <svg width="{{ $chartW }}" height="{{ $chartH }}" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                @foreach($graficaDeuda['labels'] as $i => $label)
                    @php
                        $valor = $graficaDeuda['values'][$i];
                        $barW = max(2, ($valor / $maxValor) * $barMaxW);
                        $y = $startY + ($i * $rowH);
                        $pct = $graficaDeuda['total'] > 0 ? round(($valor / $graficaDeuda['total']) * 100, 1) : 0;
                    @endphp
                    <text x="0" y="{{ $y + 10 }}" font-family="DejaVu Sans, sans-serif" font-size="7" fill="#525252">{{ $label }}</text>
                    <rect x="{{ $labelW }}" y="{{ $y }}" width="{{ $barW }}" height="14" rx="2" fill="#262626" opacity="0.88"/>
                    <rect x="{{ $labelW }}" y="{{ $y }}" width="{{ $barW }}" height="14" rx="2" fill="none" stroke="#d4d4d4" stroke-width="0.5"/>
                    <text x="{{ $labelW + $barMaxW + 8 }}" y="{{ $y + 10 }}" font-family="DejaVu Sans, sans-serif" font-size="7" fill="#171717" font-weight="bold">${{ number_format($valor, 0) }}</text>
                    <text x="{{ $labelW + $barMaxW + 58 }}" y="{{ $y + 10 }}" font-family="DejaVu Sans, sans-serif" font-size="6.5" fill="#a3a3a3">{{ $pct }}%</text>
                @endforeach
            </svg>
            <div class="chart-caption">Montos en pesos mexicanos · Barras proporcionales al mayor saldo</div>
        </div>
    @endif

    {{-- Folios pendientes --}}
    <div class="section-title">Folios pendientes</div>
    @if(count($facturas) > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:18%">Folio</th>
                    <th style="width:34%">Cliente</th>
                    <th style="width:18%">Vencimiento</th>
                    <th class="text-right" style="width:15%">Monto</th>
                    <th class="text-right" style="width:15%">Estado</th>
                </tr>
            </thead>
            <tbody>
                @php $totalFacturas = 0; @endphp
                @foreach($facturas as $f)
                    @php
                        $totalFacturas += $f->monto;
                        $vencido = $f->fecha_vencimiento && $f->fecha_vencimiento->isPast();
                    @endphp
                    <tr>
                        <td>{{ $f->folio }}</td>
                        <td>{{ $f->cliente?->nombre ?? '—' }}</td>
                        <td class="{{ $vencido ? '' : 'text-muted' }}">
                            {{ $f->fecha_vencimiento ? $f->fecha_vencimiento->format('d/m/Y') : '—' }}
                        </td>
                        <td class="text-right">${{ number_format($f->monto, 2) }}</td>
                        <td class="text-right {{ $vencido ? '' : 'text-muted' }}">{{ $vencido ? 'Vencido' : 'Vigente' }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" class="text-right">Total pendiente</td>
                    <td class="text-right">${{ number_format($totalFacturas, 2) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    @else
        <p class="section-empty">No hay folios pendientes con los filtros aplicados.</p>
    @endif

    {{-- Abonos --}}
    <div class="section-title">Abonos del periodo</div>
    @if(count($abonos) > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:30%">Cliente</th>
                    <th style="width:22%">Fecha</th>
                    <th class="text-right" style="width:16%">Anterior</th>
                    <th class="text-right" style="width:16%">Nuevo saldo</th>
                    <th class="text-right" style="width:16%">Abono</th>
                </tr>
            </thead>
            <tbody>
                @php $totalAbonos = 0; @endphp
                @foreach($abonos as $a)
                    @php $montoAbono = $a->monto_anterior - $a->monto_nuevo; $totalAbonos += $montoAbono; @endphp
                    <tr>
                        <td>{{ $a->cliente?->nombre ?? '—' }}</td>
                        <td>{{ $a->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-right">${{ number_format($a->monto_anterior, 2) }}</td>
                        <td class="text-right">${{ number_format($a->monto_nuevo, 2) }}</td>
                        <td class="text-right">${{ number_format($montoAbono, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4" class="text-right">Total abonado</td>
                    <td class="text-right">${{ number_format($totalAbonos, 2) }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <p class="section-empty">No hay abonos en el periodo seleccionado.</p>
    @endif

    {{-- Bitácora --}}
    <div class="section-title">Bitácora de eventos</div>
    @if(count($bitacora) > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:24%">Cliente</th>
                    <th style="width:16%">Fecha</th>
                    <th style="width:18%">Evento</th>
                    <th style="width:42%">Descripción</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bitacora as $b)
                    <tr>
                        <td>{{ $b->cliente?->nombre ?? '—' }}</td>
                        <td>{{ $b->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $b->tipo_evento)) }}</td>
                        <td>{{ $b->descripcion }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="section-empty">No hay eventos en bitácora para el periodo seleccionado.</p>
    @endif

    <div class="footer">
        Documento confidencial · Uso interno · GELIA NV · {{ $fechaGeneracion }}
    </div>

</body>
</html>
