<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 8px; }
        h2 { font-size: 13px; margin: 18px 0 8px; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; }
        .meta { margin-bottom: 12px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-weight: 600; }
        .empty { color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="meta">
        <div>Solicitante: {{ $solicitante }}</div>
        <div>Generado: {{ $generado_at }}</div>
        <div>Tipo: {{ $tipo_reporte }}</div>
    </div>

    <h2>Parámetros</h2>
    @if (empty($secciones['parametros']))
        <p class="empty">Sin parámetros.</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($columnas_parametros as $encabezado)
                        <th>{{ $encabezado }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($secciones['parametros'] as $fila)
                    <tr>
                        @foreach (array_keys($columnas_parametros) as $clave)
                            <td>{{ $fila[$clave] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @foreach (['resguardos' => 'Resguardos', 'turnos' => 'Turnos', 'operacion' => 'Operación', 'desglose_sucursal' => 'Desglose por sucursal'] as $clave => $tituloSeccion)
        <h2>{{ $tituloSeccion }}</h2>
        @if (empty($secciones[$clave]))
            <p class="empty">Sin métricas en esta sección.</p>
        @else
            <table>
                <thead>
                    <tr>
                        @foreach ($columnas_metricas as $encabezado)
                            <th>{{ $encabezado }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($secciones[$clave] as $fila)
                        <tr>
                            @foreach (array_keys($columnas_metricas) as $columna)
                                <td>{{ $fila[$columna] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
</body>
</html>
