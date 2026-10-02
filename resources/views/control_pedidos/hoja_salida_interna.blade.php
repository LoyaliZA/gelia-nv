<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Hoja interna de salida {{ $hoja['folio'] ?? '' }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 12pt; margin: 0; }
        h1 { font-size: 16pt; margin: 0 0 6pt 0; text-transform: uppercase; }
        .aviso { font-size: 10pt; color: #444; margin: 0 0 14pt 0; border: 1px solid #666; padding: 8pt; }
        .meta { font-size: 11pt; margin: 4pt 0; }
        .destinatario { font-size: 16pt; font-weight: bold; margin: 12pt 0 6pt 0; }
    </style>
</head>
<body>
    <h1>Hoja interna de salida</h1>
    <p class="aviso">Documento operativo de GELIA. <strong>No es remisión.</strong> Sustituye la toma documental cuando aún no existe remisión externa.</p>
    <p class="meta"><strong>Folio:</strong> {{ $hoja['folio_etiqueta'] ?? 'Folio' }} · {{ $hoja['folio'] ?? '—' }}</p>
    <p class="meta"><strong>Folio interno GELIA:</strong> {{ $hoja['folio_interno'] ?? '—' }}</p>
    <p class="meta"><strong>Fecha:</strong> {{ $hoja['fecha'] ?? '' }}</p>
    <p class="destinatario">{{ $hoja['destinatario_nombre'] ?? '—' }}</p>
    <p class="meta"><strong>Teléfono:</strong> {{ $hoja['destinatario_telefono'] ?? '—' }}</p>
    <p class="meta"><strong>Destino:</strong> {{ $hoja['municipio_destino'] ?? '—' }}</p>
    @if(!empty($hoja['direccion_referencia']))
        <p class="meta"><strong>Referencia:</strong> {{ $hoja['direccion_referencia'] }}</p>
    @endif
    <p class="meta"><strong>Transporte:</strong> {{ $hoja['transporte'] ?? '—' }}</p>
    <p class="meta"><strong>Cobro:</strong> {{ ($hoja['modalidad_cobro'] ?? '') === 'POR_COBRAR' ? 'POR COBRAR' : 'PAGADO' }}</p>
    <p class="meta"><strong>Carátula colocada:</strong> v{{ $hoja['caratula_version'] ?? '—' }}</p>
</body>
</html>
