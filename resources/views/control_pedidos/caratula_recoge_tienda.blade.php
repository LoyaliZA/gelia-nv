<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Carátula recoge en tienda {{ $caratula['folio'] ?? '' }}</title>
    <style>
        @page { margin: 16mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 11pt; margin: 0; }
        h1 { font-size: 18pt; margin: 0 0 4pt; }
        .meta { margin: 2pt 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12pt; }
        th, td { border: 1px solid #222; padding: 4pt 6pt; text-align: left; font-size: 10pt; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Recoge en tienda</h1>
    <p class="meta">Mismo día · Almacén PDV · Apartado confirmado</p>
    <p class="meta"><strong>{{ $caratula['folio_etiqueta'] ?? 'Folio' }}:</strong> {{ $caratula['folio'] ?? '—' }}</p>
    <p class="meta"><strong>Cliente:</strong> {{ $caratula['cliente'] ?? '—' }}</p>
    <p class="meta"><strong>Origen:</strong> {{ $caratula['origen'] ?? '—' }}</p>
    <p class="meta"><strong>Fecha:</strong> {{ $caratula['fecha'] ?? '' }}</p>
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Descripción</th>
                <th>Cantidad</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($caratula['piezas'] ?? []) as $pieza)
                <tr>
                    <td>{{ $pieza['sku'] ?? '—' }}</td>
                    <td>{{ $pieza['descripcion'] ?? '—' }}</td>
                    <td>{{ $pieza['cantidad'] ?? '—' }}</td>
                    <td>{{ $pieza['estado'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
