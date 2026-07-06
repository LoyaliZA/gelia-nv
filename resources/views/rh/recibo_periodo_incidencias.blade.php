<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo Periodo {{ $colaborador->folio }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1a1a2e; margin: 0; padding: 24px 32px; }
        .header-accent-bar { width: 100%; height: 4px; background: #2563eb; margin-bottom: 16px; }
        .header-bottom-line { width: 100%; height: 1.5px; background: #bfdbfe; margin-top: 16px; margin-bottom: 18px; }
        .section-title { font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; color: #1e3a8a; padding: 6px 10px; margin: 16px 0 8px; background: #eff6ff; border-left: 3px solid #2563eb; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data th, table.data td { padding: 6px 8px; border: 1px solid #e2e8f0; text-align: left; }
        table.data th { background: #f8fafc; color: #475569; }
        table.detalle th { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; }
        table.detalle td { font-size: 9px; }
        .totales { margin-top: 12px; width: 100%; }
        .totales td { padding: 6px 8px; border: 1px solid #e2e8f0; }
        .totales .label { text-align: right; font-weight: bold; background: #f8fafc; width: 75%; }
        .totales .monto { text-align: right; font-weight: bold; width: 25%; }
        .firmas { margin-top: 24px; display: table; width: 100%; }
        .firma-cell { display: table-cell; width: 50%; padding: 0 12px; text-align: center; }
        .firma-linea { border-top: 1px solid #334155; margin-top: 40px; padding-top: 6px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .footer { margin-top: 20px; padding-top: 8px; border-top: 1px solid #e2e8f0; font-size: 7px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @include('rh.partials.recibo_header', [
        'encabezado' => $encabezado,
        'docLabel' => 'Cierre de periodo',
        'docTitulo' => 'Recibo de Incidencias',
        'docSubtitulo' => $fechaInicio . ' — ' . $fechaFin,
    ])

    <div class="section-title">Colaborador</div>
    <table class="data">
        <tr><th>Nombre</th><td>{{ $colaborador->nombre_completo }}</td><th>Folio</th><td>{{ $colaborador->folio }}</td></tr>
        <tr><th>Departamento</th><td>{{ $colaborador->departamento?->nombre ?? '—' }}</td><th>Área</th><td>{{ $colaborador->area?->nombre ?? '—' }}</td></tr>
    </table>

    <div class="section-title">Incidencias del periodo</div>
    @if($incidencias->isEmpty())
        <p style="font-style:italic; color:#64748b;">No hay incidencias registradas en este periodo.</p>
    @else
        <table class="data detalle">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Folio</th>
                    <th>Concepto</th>
                    <th>Observaciones</th>
                    <th style="text-align:right;">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($incidencias as $inc)
                    <tr>
                        <td>{{ $inc->fecha_ocurrencia?->format('d/m/Y') }}</td>
                        <td>{{ $inc->folio }}</td>
                        <td>{{ $inc->regla_nombre_snapshot ?? $inc->reglaIncidencia?->nombre }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($inc->descripcion_detallada ?? '—', 80) }}</td>
                        <td style="text-align:right;">${{ number_format((float) ($inc->monto_total_final ?? $inc->total_deduccion), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($salidas->isNotEmpty())
        <div class="section-title">Salidas personales del periodo</div>
        <table class="data detalle">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Folio</th>
                    <th>Motivo</th>
                    <th>Minutos</th>
                    <th style="text-align:right;">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($salidas as $sal)
                    <tr>
                        <td>{{ $sal->fecha_evento?->format('d/m/Y') }}</td>
                        <td>{{ $sal->folio }}</td>
                        <td>{{ $sal->motivo }}</td>
                        <td>{{ $sal->minutos_ausente }}</td>
                        <td style="text-align:right;">${{ number_format((float) $sal->monto_a_deducir, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="totales">
        <tr><td class="label">Subtotal incidencias</td><td class="monto">${{ number_format($totalIncidencias, 2) }}</td></tr>
        <tr><td class="label">Subtotal salidas personales</td><td class="monto">${{ number_format($totalSalidas, 2) }}</td></tr>
        <tr><td class="label" style="background:#fef2f2; color:#b91c1c;">Total deducciones del periodo</td><td class="monto" style="background:#fef2f2; color:#b91c1c;">${{ number_format($totalGeneral, 2) }}</td></tr>
    </table>

    <div class="firmas">
        <div class="firma-cell"><div class="firma-linea">Firma del colaborador</div></div>
        <div class="firma-cell"><div class="firma-linea">Firma del gerente / RH</div></div>
    </div>

    <div class="footer">Documento generado por GELIA NV · Uso interno / Confidencial · {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
