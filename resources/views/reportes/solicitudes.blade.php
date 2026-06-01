<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; line-height: 1.4; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .muted { color: #666; font-size: 9px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 4px 5px; text-align: left; font-size: 8px; vertical-align: top; }
        th { background: #f0f0f0; font-weight: bold; }
        td.respuesta { max-width: 180px; word-wrap: break-word; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <p class="muted">Generado: {{ $fecha }} | Total de registros: {{ $total }}</p>

    @if (count($filas) === 0)
        <p>No se encontraron solicitudes con los filtros aplicados.</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach (array_keys($filas[0]) as $columna)
                        <th>{{ $columna }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        @foreach ($fila as $columna => $valor)
                            <td @if($columna === 'Respuesta / resolución') class="respuesta" @endif>{{ $valor }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
