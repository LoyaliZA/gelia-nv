<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page {
            margin: 2cm 1.5cm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8px;
            color: #1e293b;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        header {
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title {
            font-size: 16px;
            font-weight: bold;
            color: #4f46e5;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .header-subtitle {
            font-size: 9px;
            color: #64748b;
            margin-top: 4px;
        }
        .header-meta {
            text-align: right;
            font-size: 9px;
            color: #64748b;
        }
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin-bottom: 20px;
        }
        .kpi-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background-color: #f8fafc;
            padding: 10px;
            text-align: center;
            width: 25%;
        }
        .kpi-label {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .kpi-value {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
        }
        .kpi-value.positive {
            color: #10b981;
        }
        .kpi-value.negative {
            color: #ef4444;
        }
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .main-table th {
            background-color: #4f46e5;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
            padding: 6px 8px;
            border: 1px solid #4f46e5;
        }
        .main-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            font-size: 8px;
        }
        .main-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .amount {
            text-align: right;
            font-weight: bold;
            white-space: nowrap;
        }
        .text-center {
            text-align: center;
        }
        .text-bold {
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            color: #ffffff;
        }
        .badge-venta { background-color: #3b82f6; }
        .badge-reembolso { background-color: #eab308; }
        .badge-contracargo { background-color: #ef4444; }
        .badge-pendiente { background-color: #f59e0b; color: #ffffff; }
        .badge-conciliado { background-color: #10b981; color: #ffffff; }
        .badge-transferido { background-color: #6366f1; color: #ffffff; }
        .product-list {
            margin: 0;
            padding-left: 10px;
            list-style-type: square;
            color: #475569;
        }
        .product-item {
            margin-bottom: 2px;
        }
        .badge-prod-normal {
            color: #64748b;
            font-style: italic;
        }
        .badge-prod-devuelto {
            color: #f59e0b;
            font-weight: bold;
        }
        .badge-prod-perdido {
            color: #ef4444;
            font-weight: bold;
        }
        .no-records {
            text-align: center;
            padding: 20px;
            font-weight: bold;
            color: #64748b;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <header>
        <table class="header-table">
            <tr>
                <td>
                    <h1 class="header-title">{{ $titulo }}</h1>
                    <div class="header-subtitle">Conciliación de comisiones y utilidad neta e-commerce · <strong>Periodo: {{ $periodo }}</strong></div>
                </td>
                <td class="header-meta">
                    <div>Generado: {{ $fecha }}</div>
                    <div>Gelia Contabilidad</div>
                </td>
            </tr>
        </table>
    </header>

    <table class="kpi-table">
        <tr>
            <td class="kpi-card">
                <div class="kpi-label">Pedidos Filtrados</div>
                <div class="kpi-value">{{ $kpis['total'] }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Ventas Brutas</div>
                <div class="kpi-value">$ {{ number_format($kpis['ventas'], 2) }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Comisiones Retenidas</div>
                <div class="kpi-value" style="color: #f97316;">$ {{ number_format($kpis['comisiones'], 2) }}</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-label">Utilidad Neta</div>
                <div class="kpi-value {{ $kpis['utilidad'] >= 0 ? 'positive' : 'negative' }}">
                    $ {{ number_format($kpis['utilidad'], 2) }}
                </div>
            </td>
        </tr>
    </table>

    @if($pedidos->isEmpty())
        <div class="no-records">
            No se encontraron pedidos registrados para los filtros seleccionados en este periodo.
        </div>
    @else
        <table class="main-table">
            <thead>
                <tr>
                    <th style="width: 8%;">Fecha</th>
                    <th style="width: 8%;">Pedido</th>
                    <th style="width: 14%;">Cliente</th>
                    <th style="width: 10%;">Plataforma</th>
                    <th style="width: 10%;">Tipo Trans.</th>
                    <th style="width: 8%;">Estatus Pago</th>
                    <th style="width: 9%;">Venta Bruta</th>
                    <th style="width: 9%;">Comisión</th>
                    <th style="width: 9%;">Utilidad</th>
                    <th style="width: 25%;">Productos Incluidos</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pedidos as $pedido)
                    <tr>
                        <td class="text-center">{{ $pedido->fecha_salida ? $pedido->fecha_salida->format('d/m/Y') : '—' }}</td>
                        <td class="text-bold" style="color: #4f46e5;">{{ $pedido->numero_pedido }}</td>
                        <td style="text-transform: uppercase;">{{ $pedido->cliente_nombre ?? '—' }}</td>
                        <td>{{ $pedido->plataformaPago?->nombre ?? '—' }}</td>
                        <td class="text-center">
                            @php
                                $codigoTipo = strtolower($pedido->tipoTransaccion?->codigo ?? 'venta');
                                $tipoClass = 'badge-venta';
                                if (str_contains($codigoTipo, 'reembolso')) $tipoClass = 'badge-reembolso';
                                if (str_contains($codigoTipo, 'contracargo')) $tipoClass = 'badge-contracargo';
                            @endphp
                            <span class="badge {{ $tipoClass }}">{{ $pedido->tipoTransaccion?->nombre ?? 'Venta' }}</span>
                        </td>
                        <td class="text-center">
                            @php
                                $codigoEstatus = strtolower($pedido->estatusPago?->codigo ?? 'pendiente');
                                $estatusClass = 'badge-pendiente';
                                if ($codigoEstatus === 'conciliado') $estatusClass = 'badge-conciliado';
                                if ($codigoEstatus === 'transferido') $estatusClass = 'badge-transferido';
                            @endphp
                            <span class="badge {{ $estatusClass }}">{{ $pedido->estatusPago?->nombre ?? 'Pendiente' }}</span>
                        </td>
                        <td class="amount">$ {{ number_format($pedido->venta_total, 2) }}</td>
                        <td class="amount" style="color: #f97316;">$ {{ number_format($pedido->comision_plataforma, 2) }}</td>
                        <td class="amount {{ $pedido->utilidad_total >= 0 ? 'positive' : 'negative' }}" style="color: {{ $pedido->utilidad_total >= 0 ? '#10b981' : '#ef4444' }};">
                            $ {{ number_format($pedido->utilidad_total, 2) }}
                        </td>
                        <td>
                            <ul class="product-list">
                                @foreach($pedido->lineas as $linea)
                                    <li class="product-item">
                                        <strong>{{ $linea->sku }}</strong>: {{ $linea->nombre_producto }} 
                                        ({{ $linea->piezas }} pzas)
                                        @if($linea->tipo_devolucion === 'devuelto')
                                            <span class="badge-prod-devuelto">[Devuelto]</span>
                                        @elseif($linea->tipo_devolucion === 'perdido_danado')
                                            <span class="badge-prod-perdido">[Perdido/Dañado]</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
