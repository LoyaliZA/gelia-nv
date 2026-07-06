<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo Nómina {{ $colaborador->folio }}</title>
    @include('rh.partials.recibo_documento_styles')
</head>
<body class="orientacion-{{ $esHorizontal ? 'horizontal' : 'vertical' }}{{ !empty($modoUltraCompacto) ? ' ultra-compact' : '' }}{{ !empty($layoutExpandido) ? ' layout-expandido' : '' }}">
    <div class="page-wrap">
        @include('rh.partials.recibo_documento_header', [
            'encabezado' => $encabezado,
            'docLabel' => 'Recibo de pago',
            'docTitle' => 'Nómina',
            'docMeta' => $fechaInicio.' — '.$fechaFin.' · Folio <strong>'.$colaborador->folio.'</strong>',
        ])

        <div class="page-body">
            @if($esHorizontal)
                <div class="bloque-seccion">
                <table class="cols-top-horizontal">
                    <tr>
                        <td>
                            <div class="section-title primero">Colaborador</div>
                            <table class="data kv">
                                <tr><td class="label">Nombre</td><td class="value">{{ $colaborador->nombre_completo }}</td></tr>
                                <tr><td class="label">Depto.</td><td class="value">{{ $colaborador->departamento?->nombre ?? '—' }}</td></tr>
                                <tr><td class="label">Área</td><td class="value">{{ $colaborador->area?->nombre ?? '—' }}</td></tr>
                                <tr><td class="label">Puesto</td><td class="value">{{ $colaborador->puesto?->nombre ?? '—' }}</td></tr>
                            </table>
                        </td>
                        <td>
                            <div class="section-title primero">Nómina base · {{ $diasEnRango }} d</div>
                            <table class="data kv">
                                <tr><td class="label">Sal. mensual</td><td class="value">${{ number_format($nominaBase['salario_mensual'], 2) }}</td></tr>
                                <tr><td class="label">Sal. diario</td><td class="value">${{ number_format($nominaBase['salario_diario'], 2) }}</td></tr>
                                <tr><td class="label">Bono punt.</td><td class="value">${{ number_format($nominaBase['bono_puntualidad_diario'], 2) }}/d</td></tr>
                                <tr><td class="label">Bono prod.</td><td class="value">${{ number_format($nominaBase['bono_productividad_diario'], 2) }}/d</td></tr>
                            </table>
                        </td>
                        <td>
                            <div class="section-title primero">Percepciones</div>
                            <table class="data">
                                <tbody>
                                    <tr><td>Salario periodo</td><td class="monto">${{ number_format($percepciones['salario_rango'], 2) }}</td></tr>
                                    <tr><td>Bono puntualidad</td><td class="monto">${{ number_format($percepciones['bono_puntualidad_rango'], 2) }}</td></tr>
                                    <tr><td>Bono productividad</td><td class="monto">${{ number_format($percepciones['bono_productividad_rango'], 2) }}</td></tr>
                                    <tr><td>Horas extra</td><td class="monto">${{ number_format($percepciones['horas_extra'], 2) }}</td></tr>
                                    <tr style="background:#f0f0f0;"><td><strong>Total</strong></td><td class="monto"><strong>${{ number_format($percepciones['total'], 2) }}</strong></td></tr>
                                </tbody>
                            </table>
                        </td>
                        <td>
                            <div class="section-title primero">Deducciones</div>
                            <table class="data">
                                <tbody>
                                    @if($deducciones['totales']['prestamos'] > 0)
                                        <tr><td>Préstamos</td><td class="monto">${{ number_format($deducciones['totales']['prestamos'], 2) }}</td></tr>
                                    @endif
                                    @if($deducciones['totales']['incidencias'] > 0)
                                        <tr><td>Incidencias</td><td class="monto">${{ number_format($deducciones['totales']['incidencias'], 2) }}</td></tr>
                                    @endif
                                    @if($deducciones['totales']['faltas_salario'] > 0)
                                        <tr><td>Faltas salario</td><td class="monto">${{ number_format($deducciones['totales']['faltas_salario'], 2) }}</td></tr>
                                    @endif
                                    @if($deducciones['totales']['faltas_puntualidad'] > 0)
                                        <tr><td>Faltas punt.</td><td class="monto">${{ number_format($deducciones['totales']['faltas_puntualidad'], 2) }}</td></tr>
                                    @endif
                                    @if($deducciones['totales']['faltas_productividad'] > 0)
                                        <tr><td>Faltas prod.</td><td class="monto">${{ number_format($deducciones['totales']['faltas_productividad'], 2) }}</td></tr>
                                    @endif
                                    @if($deducciones['totales']['salidas'] > 0)
                                        <tr><td>Salidas</td><td class="monto">${{ number_format($deducciones['totales']['salidas'], 2) }}</td></tr>
                                    @endif
                                    @if($deducciones['totales']['total'] <= 0)
                                        <tr><td colspan="2" class="empty-note">Sin deducciones</td></tr>
                                    @endif
                                    <tr style="background:#f0f0f0;"><td><strong>Total</strong></td><td class="monto"><strong>${{ number_format($deducciones['totales']['total'], 2) }}</strong></td></tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </table>
                </div>
            @else
                <div class="bloque-seccion">
                <table class="cols-2">
                    <tr>
                        <td>
                            <div class="section-title primero">Colaborador</div>
                            <table class="kv">
                                <tr><td class="label">Nombre</td><td class="value">{{ $colaborador->nombre_completo }}</td></tr>
                                <tr><td class="label">Depto. / Área</td><td class="value">{{ $colaborador->departamento?->nombre ?? '—' }} / {{ $colaborador->area?->nombre ?? '—' }}</td></tr>
                                <tr><td class="label">Puesto</td><td class="value">{{ $colaborador->puesto?->nombre ?? '—' }}</td></tr>
                            </table>
                        </td>
                        <td>
                            <div class="section-title primero">Nómina base · {{ $diasEnRango }} días</div>
                            <table class="inline-base">
                                <tr>
                                    <td><span class="lbl">Sal. mensual</span>${{ number_format($nominaBase['salario_mensual'], 2) }}</td>
                                    <td><span class="lbl">Sal. diario</span>${{ number_format($nominaBase['salario_diario'], 2) }}</td>
                                    <td><span class="lbl">Bono punt.</span>${{ number_format($nominaBase['bono_puntualidad_diario'], 2) }}/d</td>
                                    <td><span class="lbl">Bono prod.</span>${{ number_format($nominaBase['bono_productividad_diario'], 2) }}/d</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                </div>

                <div class="bloque-seccion">
                <table class="cols-perc-ded">
                    <tr>
                        <td>
                            <div class="section-title">Percepciones</div>
                            <table class="data">
                                <tbody>
                                    <tr><td>Salario del periodo</td><td class="monto">${{ number_format($percepciones['salario_rango'], 2) }}</td></tr>
                                    <tr><td>Bono puntualidad</td><td class="monto">${{ number_format($percepciones['bono_puntualidad_rango'], 2) }}</td></tr>
                                    <tr><td>Bono productividad</td><td class="monto">${{ number_format($percepciones['bono_productividad_rango'], 2) }}</td></tr>
                                    <tr><td>Horas extra</td><td class="monto">${{ number_format($percepciones['horas_extra'], 2) }}</td></tr>
                                    <tr style="background:#f0f0f0;"><td><strong>Total percepciones</strong></td><td class="monto"><strong>${{ number_format($percepciones['total'], 2) }}</strong></td></tr>
                                </tbody>
                            </table>
                        </td>
                        <td>
                            <div class="section-title">Resumen deducciones</div>
                            <table class="data">
                                <tbody>
                                    @if($deducciones['totales']['prestamos'] > 0)
                                        <tr><td>Préstamos / cuotas</td><td class="monto">${{ number_format($deducciones['totales']['prestamos'], 2) }}</td></tr>
                                    @endif
                                    @if($deducciones['totales']['incidencias'] > 0)
                                        <tr><td>Incidencias operativas</td><td class="monto">${{ number_format($deducciones['totales']['incidencias'], 2) }}</td></tr>
                                    @endif
                                    @if($deducciones['totales']['faltas_salario'] > 0)
                                        <tr><td>Faltas (salario)</td><td class="monto">${{ number_format($deducciones['totales']['faltas_salario'], 2) }}</td></tr>
                                    @endif
                                    @if($deducciones['totales']['faltas_puntualidad'] > 0)
                                        <tr><td>Faltas (puntualidad)</td><td class="monto">${{ number_format($deducciones['totales']['faltas_puntualidad'], 2) }}</td></tr>
                                    @endif
                                    @if($deducciones['totales']['faltas_productividad'] > 0)
                                        <tr><td>Faltas (productividad)</td><td class="monto">${{ number_format($deducciones['totales']['faltas_productividad'], 2) }}</td></tr>
                                    @endif
                                    @if($deducciones['totales']['salidas'] > 0)
                                        <tr><td>Salidas personales</td><td class="monto">${{ number_format($deducciones['totales']['salidas'], 2) }}</td></tr>
                                    @endif
                                    @if($deducciones['totales']['total'] <= 0)
                                        <tr><td colspan="2" class="empty-note">Sin deducciones en el periodo</td></tr>
                                    @endif
                                    <tr style="background:#f0f0f0;"><td><strong>Total deducciones</strong></td><td class="monto"><strong>${{ number_format($deducciones['totales']['total'], 2) }}</strong></td></tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </table>
                </div>
            @endif

            @if($movimientos->isNotEmpty())
                <div class="bloque-seccion movimientos-container">
                    <div class="section-title">
                        Detalle de movimientos
                        @if(($movimientosOmitidos ?? 0) > 0)
                            <span style="font-weight:normal; font-size:0.85em;">({{ $movimientosOmitidos }} adicionales en resumen)</span>
                        @endif
                    </div>
                    @include('rh.partials.recibo_nomina_movimientos', [
                        'compacto' => $esHorizontal && ($columnasDetalle ?? 1) > 1,
                    ])
                </div>
            @endif
        </div>

        <div class="page-foot">
            <table class="footer-row">
                <tr>
                    <td style="width:{{ $esHorizontal ? '50%' : '58%' }};">
                        <table class="totales">
                            <tr class="neto">
                                <td class="label">Neto a pagar</td>
                                <td class="monto">${{ number_format($neto, 2) }} MXN</td>
                            </tr>
                        </table>
                    </td>
                    <td style="width:{{ $esHorizontal ? '50%' : '42%' }}%;">
                        <div class="firma-block">
                            <div class="firma-img-slot">
                                @if(!empty($firmaColaboradorBase64))
                                    <img
                                        src="data:image/png;base64,{{ $firmaColaboradorBase64 }}"
                                        class="firma-img"
                                        alt="Firma del colaborador"
                                    />
                                @endif
                            </div>
                            <div class="firma-linea">Firma del colaborador</div>
                        </div>
                    </td>
                </tr>
            </table>
            <div class="footer">GELIA NV · Confidencial · {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>
</body>
</html>
