<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $comprobante->folio }}</title>
    <style>
        @page { margin: 3mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: {{ ($perfil ?? '80mm') === '58mm' ? '11px' : (($perfil ?? '80mm') === 'carta' ? '13px' : '12px') }};
            font-weight: bold;
            color: #000;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        .center { text-align: center; }
        .logo { max-width: 98%; max-height: 136px; margin: 0 auto 8px; display: block; }
        .titulo { font-size: 0.95em; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; margin: 4px 0 0; }
        .badge { font-weight: bold; letter-spacing: 1px; font-size: 0.85em; margin-bottom: 6px; }
        .rule { border-top: 1.5px dashed #000; margin: 8px 0; }
        .row { width: 100%; border-collapse: collapse; margin: 3px 0; }
        .row td { padding: 1px 0; vertical-align: top; font-weight: bold; }
        .row td.r { text-align: right; white-space: nowrap; }
        .section { font-size: 0.9em; font-weight: bold; text-transform: uppercase; margin: 8px 0 3px; }
        .item { margin: 5px 0 0; padding-left: 3px; border-left: 2.5px solid #000; }
        .total { font-weight: bold; font-size: 1.15em; }
        .muted { color: #222; font-size: 0.95em; }
        .legal { margin-top: 12px; margin-bottom: 18px; font-size: 0.95em; }
        .sig { margin-top: 48px; text-align: center; font-size: 0.95em; }
        .sig-line { border-top: 1.5px solid #000; width: 75%; margin: 0 auto 3px; }
        .footer { margin-top: 12px; font-weight: bold; font-size: 0.95em; }
    </style>
</head>
<body>
    @php
        $logo = ($encabezado['logos'][0] ?? null);
        $marca = $logo['alt']
            ?? ($encabezado['mostrar_bellaroma'] ?? false ? 'Bellaroma' : (($encabezado['mostrar_aromas'] ?? false) ? 'Aromas' : 'GELIA'));
    @endphp

    @if(!empty($esReimpresion))
        <div class="center badge">REIMPRESIÓN</div>
    @endif

    <div class="center">
        @if(!empty($logo['base64']))
            <img class="logo" src="data:image/png;base64,{{ $logo['base64'] }}" alt="{{ $logo['alt'] ?? $marca }}" />
        @else
            <div style="font-weight:bold; text-transform:uppercase;">{{ $marca }}</div>
        @endif
        <div class="titulo">Aplicación de saldo a favor</div>
    </div>

    <div class="rule"></div>

    <div><strong>Folio:</strong> {{ $comprobante->folio }}</div>
    <div><strong>Fecha:</strong> {{ optional($comprobante->aplicado_at ?? $comprobante->created_at)->format('d/m/Y H:i') }}</div>
    @if($comprobante->sucursal)
        <div><strong>Sucursal:</strong> {{ $comprobante->sucursal }}</div>
    @endif
    @if($comprobante->caja || $comprobante->referencia_venta)
        <div>
            @if($comprobante->caja)<strong>Caja:</strong> {{ $comprobante->caja }}@endif
            @if($comprobante->referencia_venta)
                @if($comprobante->caja) · @endif
                <strong>Venta:</strong> {{ $comprobante->referencia_venta }}
            @endif
        </div>
    @endif

    <div class="rule"></div>

    <div><strong>Cliente:</strong> {{ $comprobante->cliente?->nombre }}</div>
    <div><strong>No. cliente:</strong> {{ $comprobante->cliente?->numero_cliente }}</div>

    <div class="rule"></div>

    <table class="row"><tr>
        <td>Saldo anterior</td>
        <td class="r">${{ number_format((float) $comprobante->saldo_anterior, 2) }}</td>
    </tr></table>

    <div class="section">Saldos utilizados</div>
    @foreach(($comprobante->creditos_detalle ?? []) as $item)
        <div class="item">
            <div><strong>{{ $item['folio'] ?? '' }}</strong> · {{ $item['canal_origen'] ?? '' }}</div>
            @if(!empty($item['documento_origen']))
                <div class="muted">Origen: {{ $item['documento_origen'] }}</div>
            @endif
            <table class="row"><tr>
                <td>Aplicado</td>
                <td class="r">${{ number_format((float) ($item['monto'] ?? 0), 2) }}</td>
            </tr></table>
        </div>
    @endforeach

    <div class="rule"></div>

    <table class="row total"><tr>
        <td>TOTAL USADO</td>
        <td class="r">${{ number_format((float) $comprobante->monto_aplicado, 2) }}</td>
    </tr></table>
    <table class="row"><tr>
        <td>Saldo restante</td>
        <td class="r">${{ number_format((float) $comprobante->saldo_restante, 2) }}</td>
    </tr></table>

    <div class="rule"></div>

    @php
        $generadores = collect($comprobante->creditos_detalle ?? [])->pluck('generado_por')->filter()->unique()->implode(', ');
    @endphp
    @if($generadores)
        <div class="muted">SAF registrado por: {{ $generadores }}</div>
    @endif
    <div class="muted">Encargado de caja: {{ $comprobante->generadoPor?->name }}</div>

    <p class="legal">Firma de autorización.</p>

    <div class="sig"><div class="sig-line"></div>Firma del cliente</div>
    <div class="sig"><div class="sig-line"></div>Firma de la cajera</div>

    <div class="center footer">No es un comprobante fiscal.</div>
</body>
</html>
