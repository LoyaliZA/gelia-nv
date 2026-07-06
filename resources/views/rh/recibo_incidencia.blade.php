<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo {{ $deduccion->folio }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5px; color: #1a1a2e; margin: 0; padding: 28px 36px; }
        .header-accent-bar { width: 100%; height: 4px; background: #2563eb; margin-bottom: 16px; }
        .header-bottom-line { width: 100%; height: 1.5px; background: #bfdbfe; margin-top: 16px; margin-bottom: 18px; }
        .section-title { font-size: 9.5px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; color: #1e3a8a; padding: 7px 12px; margin: 18px 0 8px; background: #eff6ff; border-left: 3px solid #2563eb; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.data th, table.data td { padding: 7px 10px; border: 1px solid #e2e8f0; text-align: left; font-size: 10px; }
        table.data th { background: #f8fafc; color: #475569; width: 28%; }
        .total-box { margin-top: 12px; padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; }
        .total-box strong { color: #b91c1c; font-size: 14px; }
        .observaciones { padding: 10px; border: 1px dashed #cbd5e1; min-height: 48px; white-space: pre-wrap; }
        .firmas { margin-top: 28px; display: table; width: 100%; }
        .firma-cell { display: table-cell; width: 50%; padding: 0 12px; text-align: center; vertical-align: bottom; }
        .firma-linea { border-top: 1px solid #334155; margin-top: 48px; padding-top: 6px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 7.5px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @include('rh.partials.recibo_header', [
        'encabezado' => $encabezado,
        'docLabel' => 'Recursos Humanos',
        'docTitulo' => 'Recibo de Incidencia',
        'docSubtitulo' => $deduccion->folio,
    ])

    <div class="section-title">Datos del colaborador</div>
    <table class="data">
        <tr><th>Colaborador</th><td>{{ $colaborador->nombre_completo }}</td></tr>
        <tr><th>Folio RH</th><td>{{ $colaborador->folio }}</td></tr>
        <tr><th>Departamento</th><td>{{ $colaborador->departamento?->nombre ?? $deduccion->departamento_snapshot ?? '—' }}</td></tr>
        <tr><th>Área</th><td>{{ $colaborador->area?->nombre ?? $deduccion->area_snapshot ?? '—' }}</td></tr>
        <tr><th>Fecha de ocurrencia</th><td>{{ $fecha }}</td></tr>
    </table>

    <div class="section-title">Detalle de la deducción</div>
    <table class="data">
        <tr><th>Concepto</th><td>{{ $deduccion->regla_nombre_snapshot ?? $deduccion->reglaIncidencia?->nombre ?? '—' }}</td></tr>
        @if((float) $deduccion->deduccion_salario_base > 0)
            <tr><th>Deducción salario base</th><td>${{ number_format((float) $deduccion->deduccion_salario_base, 2) }}</td></tr>
        @endif
        @if((float) $deduccion->deduccion_bono_puntualidad > 0)
            <tr><th>Deducción bono puntualidad</th><td>${{ number_format((float) $deduccion->deduccion_bono_puntualidad, 2) }}</td></tr>
        @endif
        @if((float) $deduccion->deduccion_bono_productividad > 0)
            <tr><th>Deducción bono productividad</th><td>${{ number_format((float) $deduccion->deduccion_bono_productividad, 2) }}</td></tr>
        @endif
        <tr><th>Registrado por</th><td>{{ $deduccion->registradoPor?->name ?? '—' }}</td></tr>
    </table>

    <div class="total-box">
        <strong>Total a descontar: ${{ number_format((float) ($deduccion->monto_total_final ?? $deduccion->total_deduccion), 2) }} MXN</strong>
    </div>

    <div class="section-title">Observaciones</div>
    <div class="observaciones">{{ $deduccion->descripcion_detallada ?: 'Sin observaciones adicionales.' }}</div>

    <div class="firmas">
        <div class="firma-cell">
            <div class="firma-linea">Firma del colaborador</div>
        </div>
        <div class="firma-cell">
            <div class="firma-linea">Firma del gerente autorizado</div>
        </div>
    </div>

    <div class="footer">Documento generado por GELIA NV · Uso interno / Confidencial · {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
