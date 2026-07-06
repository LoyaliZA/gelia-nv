<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo Periodo {{ $colaborador->folio }}</title>
    @include('rh.partials.recibo_documento_styles')
</head>
<body class="orientacion-vertical layout-expandido">
    <div class="page-wrap">
        @include('rh.partials.recibo_documento_header', [
            'encabezado' => $encabezado,
            'docLabel' => 'Cierre de periodo',
            'docTitle' => 'Recibo de Incidencias',
            'docMeta' => $fechaInicio.' — '.$fechaFin.' · Folio <strong>'.$colaborador->folio.'</strong>',
        ])

        <div class="page-body">
            <div class="bloque-seccion">
                <div class="section-title primero">Colaborador</div>
                <table class="kv">
                    <tr><td class="label">Nombre</td><td class="value">{{ $colaborador->nombre_completo }}</td></tr>
                    <tr><td class="label">Folio RH</td><td class="value">{{ $colaborador->folio }}</td></tr>
                    <tr><td class="label">Departamento</td><td class="value">{{ $colaborador->departamento?->nombre ?? '—' }}</td></tr>
                    <tr><td class="label">Área</td><td class="value">{{ $colaborador->area?->nombre ?? '—' }}</td></tr>
                </table>
            </div>

            <div class="bloque-seccion">
                <div class="section-title">Incidencias del periodo</div>
                @if($incidencias->isEmpty())
                    <p class="empty-note">No hay incidencias registradas en este periodo.</p>
                @else
                    <table class="data">
                        <thead>
                            <tr>
                                <th class="fecha">Fecha</th>
                                <th class="ref">Folio</th>
                                <th>Concepto</th>
                                <th>Observaciones</th>
                                <th style="text-align:right;">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($incidencias as $inc)
                                <tr>
                                    <td class="fecha">{{ $inc->fecha_ocurrencia?->format('d/m/Y') }}</td>
                                    <td class="ref">{{ $inc->folio }}</td>
                                    <td>{{ $inc->regla_nombre_snapshot ?? $inc->reglaIncidencia?->nombre }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($inc->descripcion_detallada ?? '—', 80) }}</td>
                                    <td class="monto">${{ number_format((float) ($inc->monto_total_final ?? $inc->total_deduccion), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            @if($salidas->isNotEmpty())
                <div class="bloque-seccion">
                    <div class="section-title">Salidas personales del periodo</div>
                    <table class="data">
                        <thead>
                            <tr>
                                <th class="fecha">Fecha</th>
                                <th class="ref">Folio</th>
                                <th>Motivo</th>
                                <th>Minutos</th>
                                <th style="text-align:right;">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($salidas as $sal)
                                <tr>
                                    <td class="fecha">{{ $sal->fecha_evento?->format('d/m/Y') }}</td>
                                    <td class="ref">{{ $sal->folio }}</td>
                                    <td>{{ $sal->motivo }}</td>
                                    <td>{{ $sal->minutos_ausente }}</td>
                                    <td class="monto">${{ number_format((float) $sal->monto_a_deducir, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="bloque-seccion">
                <table class="data">
                    <tbody>
                        <tr><td>Subtotal incidencias</td><td class="monto">${{ number_format($totalIncidencias, 2) }}</td></tr>
                        <tr><td>Subtotal salidas personales</td><td class="monto">${{ number_format($totalSalidas, 2) }}</td></tr>
                    </tbody>
                </table>
                <table class="totales" style="margin-top:8px;">
                    <tr class="neto">
                        <td class="label">Total deducciones del periodo</td>
                        <td class="monto">${{ number_format($totalGeneral, 2) }} MXN</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="page-foot">
            @include('rh.partials.recibo_documento_firmas', [
                'firmas' => [
                    ['label' => 'Firma del colaborador', 'imagen_base64' => null],
                    ['label' => 'Firma del gerente / RH', 'imagen_base64' => null],
                ],
            ])
            <div class="footer">GELIA NV · Confidencial · {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>
</body>
</html>
