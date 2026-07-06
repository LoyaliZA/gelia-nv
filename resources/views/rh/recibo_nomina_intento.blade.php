<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo Nómina {{ $colaborador->folio }}</title>
    {{-- He comentado tu include y agregado estilos base para asegurar el layout flexbox --}}
    {{-- @include('rh.partials.recibo_nomina_styles') --}}
    <style>
        @page {
            margin: 10mm; /* Márgenes de la página para impresión */
            size: A4 portrait; /* O landscape dependiendo de tu necesidad */
        }
        
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px; /* Aumenté un poco el tamaño base si decías que estaba pequeño */
            margin: 0;
            padding: 0;
            /* Flexbox es clave aquí para que ocupe todo el alto */
            display: flex;
            flex-direction: column;
            min-height: 100vh; /* Asegura que el body ocupe toda la ventana/página */
        }

        /* Contenedor principal flex */
        .page-wrap {
            display: flex;
            flex-direction: column;
            flex-grow: 1; /* Permite que este contenedor crezca para llenar el body */
            width: 100%;
            height: 100%;
        }

        /* El cuerpo del documento crecerá para empujar el footer hacia abajo */
        .page-body {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        /* Hacemos que el último elemento (movimientos) ocupe el espacio restante si es necesario */
        .movimientos-container {
             flex-grow: 1; 
        }

        /* Estilos generales de tablas */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        /* Estilos específicos de tu HTML original adaptados */
        .header-rule {
            border-top: 2px solid #000;
            margin-bottom: 10px;
        }

        .doc-label { font-size: 0.9em; text-transform: uppercase; color: #555;}
        .doc-title { font-size: 1.5em; font-weight: bold; margin: 5px 0;}
        .doc-meta { font-size: 0.9em; color: #666;}
        
        .section-title {
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 2px solid #333;
            margin-bottom: 5px;
            padding-bottom: 3px;
            font-size: 1.1em;
            margin-top: 15px;
        }

        .kv td { border: none; padding: 4px 0; }
        .kv .label { font-weight: bold; width: 40%; color: #444;}
        .kv .value { text-align: left; }

        .data th, .data td { padding: 6px; }
        .data .monto { text-align: right; }
        .empty-note { text-align: center; font-style: italic; color: #888; }

        /* Footer siempre abajo */
        .page-foot {
            margin-top: auto; /* Empuja el footer hacia abajo si flex-grow no es suficiente en algunos visores HTML a PDF */
            padding-top: 20px;
            border-top: 1px solid #ccc;
        }

        .totales .neto td {
            background-color: #000;
            color: #fff;
            font-size: 1.2em;
            font-weight: bold;
            padding: 10px;
            border: none;
        }
        .totales .neto .monto { text-align: right; }

        .firma-block {
            text-align: center;
            margin-top: 20px;
        }
        .firma-linea {
            border-top: 1px solid #000;
            padding-top: 5px;
            display: inline-block;
            width: 80%;
            font-size: 0.9em;
            text-transform: uppercase;
        }

        .footer {
            text-align: center;
            font-size: 0.8em;
            color: #888;
            margin-top: 10px;
        }

        /* Columnas */
        .cols-2 > tbody > tr > td { width: 50%; vertical-align: top; border: none; padding: 0 10px; }
        .cols-2 > tbody > tr > td:first-child { padding-left: 0; }
        .cols-2 > tbody > tr > td:last-child { padding-right: 0; }
        
        .cols-perc-ded > tbody > tr > td { width: 50%; vertical-align: top; border: none; padding: 0 10px; }
        .cols-perc-ded > tbody > tr > td:first-child { padding-left: 0; }
        .cols-perc-ded > tbody > tr > td:last-child { padding-right: 0; }

        .inline-base td { border: 1px solid #ddd; text-align: center; padding: 5px; }
        .inline-base .lbl { display: block; font-size: 0.8em; color: #666; text-transform: uppercase; margin-bottom: 3px;}

    </style>
</head>
<body class="orientacion-{{ $esHorizontal ? 'horizontal' : 'vertical' }}{{ !empty($modoUltraCompacto) ? ' ultra-compact' : '' }}{{ !empty($layoutExpandido) ? ' layout-expandido' : '' }}">
    <div class="page-wrap">
        
        {{-- ENCABEZADO --}}
        <div>
            <div class="header-rule"></div>
            <table style="width:100%; border:none; margin-bottom:15px;">
                <tr>
                    <td style="width:48%; vertical-align:middle; border:none; padding:0;">
                        @foreach($encabezado['logos'] as $logo)
                            @if(!empty($logo['base64']))
                                <img
                                    src="data:image/png;base64,{{ $logo['base64'] }}"
                                    style="height:{{ $esHorizontal ? '60px' : '50px' }}; max-width:180px; display:block; filter: grayscale(100%);"
                                    alt="{{ $logo['alt'] }}"
                                />
                            @else
                                <span style="font-size:12px; font-weight:bold;">{{ $logo['alt'] }}</span>
                            @endif
                        @endforeach
                    </td>
                    <td style="width:52%; vertical-align:middle; text-align:right; border:none; padding:0;">
                        <div class="doc-label">Recibo de pago</div>
                        <div class="doc-title">NÓMINA</div>
                        <div class="doc-meta">
                            {{ $fechaInicio }} — {{ $fechaFin }} · Folio <strong>{{ $colaborador->folio }}</strong>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- CUERPO QUE CRECE --}}
        <div class="page-body">
                
                @if($esHorizontal)
                    {{-- Lógica Horizontal (omitida o simplificada aquí para enfocar en el problema principal que parece ser vertical según la imagen) --}}
                     <div style="color:red; text-align:center;">Vista horizontal no optimizada en este ejemplo base.</div>
                @else
                    {{-- Vertical: 2 columnas apiladas --}}
                    <table class="cols-2">
                        <tr>
                            <td>
                                <div class="section-title" style="margin-top:0;">Colaborador</div>
                                <table class="kv" style="margin-bottom: 0;">
                                    <tr><td class="label">Nombre</td><td class="value">{{ $colaborador->nombre_completo }}</td></tr>
                                    <tr><td class="label">Depto. / Área</td><td class="value">{{ $colaborador->departamento?->nombre ?? '—' }} / {{ $colaborador->area?->nombre ?? '—' }}</td></tr>
                                    <tr><td class="label">Puesto</td><td class="value">{{ $colaborador->puesto?->nombre ?? '—' }}</td></tr>
                                </table>
                            </td>
                            <td>
                                <div class="section-title" style="margin-top:0;">Nómina base · {{ $diasEnRango }} días</div>
                                <table class="inline-base" style="width:100%; margin-bottom: 0;">
                                    <tr>
                                        <td><span class="lbl">Sal. Mensual</span>${{ number_format($nominaBase['salario_mensual'], 2) }}</td>
                                        <td><span class="lbl">Sal. Diario</span>${{ number_format($nominaBase['salario_diario'], 2) }}</td>
                                        <td><span class="lbl">Bono Punt.</span>${{ number_format($nominaBase['bono_puntualidad_diario'], 2) }}/d</td>
                                        <td><span class="lbl">Bono Prod.</span>${{ number_format($nominaBase['bono_productividad_diario'], 2) }}/d</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

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
                                        <tr style="background:#f2f2f2;"><td><strong>Total percepciones</strong></td><td class="monto"><strong>${{ number_format($percepciones['total'], 2) }}</strong></td></tr>
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
                                        <tr style="background:#f2f2f2;"><td><strong>Total deducciones</strong></td><td class="monto"><strong>${{ number_format($deducciones['totales']['total'], 2) }}</strong></td></tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </table>
                @endif

                {{-- CONTENEDOR DE MOVIMIENTOS QUE SE EXPANDE --}}
                @if($movimientos->isNotEmpty())
                    <div class="movimientos-container">
                        <div class="section-title">
                            Detalle de movimientos
                            @if(($movimientosOmitidos ?? 0) > 0)
                                <span style="font-weight:normal; font-size:0.8em;">({{ $movimientosOmitidos }} adicionales en resumen)</span>
                            @endif
                        </div>
                        
                        {{-- He expandido el partial de movimientos aquí para aplicar estilos directos. 
                             Si prefieres seguir usando @include, asegúrate de que la tabla dentro del partial 
                             tenga width:100% y padding adecuado. --}}
                        <table class="data" style="margin-bottom:0;">
                            <thead>
                                <tr>
                                    <th>TIPO</th>
                                    <th>FECHA</th>
                                    <th>REF.</th>
                                    <th>CONCEPTO</th>
                                    <th style="text-align:right;">MONTO</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($movimientos as $mov)
                                <tr>
                                    <td>{{ $mov->tipo }}</td>
                                    <td>{{ $mov->fecha }}</td>
                                    <td>{{ $mov->referencia }}</td>
                                    <td>{{ $mov->concepto }}</td>
                                    <td class="monto">${{ number_format($mov->monto, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
        </div>

        {{-- PIE DE PÁGINA SIEMPRE AL FINAL --}}
        <div class="page-foot">
            <table style="width:100%; border:none; margin-bottom:0;">
                <tr>
                    <td style="width:50%; vertical-align:bottom; border:none; padding:0;">
                        <table class="totales" style="margin:0; width:100%;">
                            <tr class="neto">
                                <td>Neto a pagar</td>
                                <td class="monto">${{ number_format($neto, 2) }} MXN</td>
                            </tr>
                        </table>
                    </td>
                    <td style="width:50%; vertical-align:bottom; text-align:center; border:none; padding:0;">
                        <div class="firma-block">
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