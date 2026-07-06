<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo {{ $deduccion->folio }}</title>
    @include('rh.partials.recibo_documento_styles')
</head>
<body class="orientacion-vertical layout-expandido">
    <div class="page-wrap">
        @include('rh.partials.recibo_documento_header', [
            'encabezado' => $encabezado,
            'docLabel' => 'Recursos Humanos',
            'docTitle' => 'Recibo de Incidencia',
            'docMeta' => 'Folio <strong>'.$deduccion->folio.'</strong> · Fecha <strong>'.$fecha.'</strong>',
        ])

        <div class="page-body">
            <div class="bloque-seccion">
                <div class="section-title primero">Datos del colaborador</div>
                <table class="kv">
                    <tr><td class="label">Colaborador</td><td class="value">{{ $colaborador->nombre_completo }}</td></tr>
                    <tr><td class="label">Folio RH</td><td class="value">{{ $colaborador->folio }}</td></tr>
                    <tr><td class="label">Departamento</td><td class="value">{{ $colaborador->departamento?->nombre ?? $deduccion->departamento_snapshot ?? '—' }}</td></tr>
                    <tr><td class="label">Área</td><td class="value">{{ $colaborador->area?->nombre ?? $deduccion->area_snapshot ?? '—' }}</td></tr>
                </table>
            </div>

            <div class="bloque-seccion">
                <div class="section-title">Base de cálculo (al momento del registro)</div>
                <table class="data">
                    <tbody>
                        <tr><td>Salario diario (snapshot)</td><td class="monto">${{ number_format((float) ($deduccion->salario_diario_snapshot ?? 0), 2) }}</td></tr>
                        <tr><td>Bono puntualidad diario (snapshot)</td><td class="monto">${{ number_format((float) ($deduccion->bono_puntualidad_diario_snapshot ?? 0), 2) }}</td></tr>
                        <tr><td>Bono productividad diario (snapshot)</td><td class="monto">${{ number_format((float) ($deduccion->bono_productividad_diario_snapshot ?? 0), 2) }}</td></tr>
                        <tr><td>Monto base de deducción</td><td class="monto">${{ number_format((float) ($deduccion->monto_deduccion_base ?? 0), 2) }}</td></tr>
                        <tr><td>Factor multiplicador</td><td class="monto">{{ number_format((float) ($deduccion->factor_multiplicador ?? 1), 2) }}×</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="bloque-seccion">
                <div class="section-title">Detalle de la deducción</div>
                <table class="data">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th>Registrado por</th>
                            <th>Fecha de ocurrencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $deduccion->regla_nombre_snapshot ?? $deduccion->reglaIncidencia?->nombre ?? '—' }}</td>
                            <td>{{ $deduccion->registradoPor?->name ?? '—' }}</td>
                            <td class="fecha">{{ $fecha }}</td>
                        </tr>
                    </tbody>
                </table>
                <table class="data" style="margin-top:8px;">
                    <tbody>
                        <tr><td>Deducción salario base</td><td class="monto">${{ number_format((float) $deduccion->deduccion_salario_base, 2) }}</td></tr>
                        <tr><td>Deducción bono puntualidad</td><td class="monto">${{ number_format((float) $deduccion->deduccion_bono_puntualidad, 2) }}</td></tr>
                        <tr><td>Deducción bono productividad</td><td class="monto">${{ number_format((float) $deduccion->deduccion_bono_productividad, 2) }}</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="bloque-seccion">
                <div class="section-title">Observaciones</div>
                <div class="observaciones-box {{ $deduccion->descripcion_detallada ? '' : 'vacio' }}">
                    {{ $deduccion->descripcion_detallada ?: 'Sin observaciones adicionales.' }}
                </div>
            </div>

            <div class="bloque-seccion">
                <table class="totales">
                    <tr class="neto">
                        <td class="label">Total a descontar</td>
                        <td class="monto">${{ number_format((float) ($deduccion->monto_total_final ?? $deduccion->total_deduccion), 2) }} MXN</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="page-foot">
            @php
                $firmaGerente = ($deduccion->firma_gerente_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($deduccion->firma_gerente_path))
                    ? base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($deduccion->firma_gerente_path))
                    : null;
                $firmaColaborador = ($deduccion->firma_colaborador_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($deduccion->firma_colaborador_path))
                    ? base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($deduccion->firma_colaborador_path))
                    : null;
            @endphp
            @include('rh.partials.recibo_documento_firmas', [
                'firmas' => [
                    ['label' => 'Firma del gerente autorizado', 'imagen_base64' => $firmaGerente],
                    ['label' => 'Firma del colaborador', 'imagen_base64' => $firmaColaborador],
                ],
            ])
            <div class="footer">GELIA NV · Confidencial · {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>
</body>
</html>
