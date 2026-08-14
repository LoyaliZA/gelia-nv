<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $comprobante->folio }}</title>
    <style>
        /* DomPDF: solo normal/bold (DejaVu no tiene medium 500 → faux-bold borroso al imprimir). Usar pt. */
        @page { margin: 3mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: {{ ($perfil ?? '80mm') === '58mm' ? '9pt' : (($perfil ?? '80mm') === 'carta' ? '11pt' : '10pt') }};
            font-weight: normal;
            color: #000;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        strong { font-weight: bold; }
        .center { text-align: center; }
        .logo { max-width: 75%; max-height: 95px; margin: 0 auto 5px; display: block; }
        .titulo { font-size: 0.95em; font-weight: bold; text-transform: uppercase; margin: 3px 0 0; }
        .badge { font-weight: bold; font-size: 0.85em; margin-bottom: 4px; text-transform: uppercase; }
        .rule { border-top: 1px dashed #000; margin: 7px 0; }
        .meta { margin: 3px 0; font-weight: normal; }
        .row { width: 100%; border-collapse: collapse; margin: 3px 0; }
        .row td { padding: 1.5px 0; vertical-align: top; font-weight: normal; color: #000; }
        .row td.r { text-align: right; white-space: nowrap; width: 1%; }
        .section { font-size: 0.9em; font-weight: bold; text-transform: uppercase; margin: 7px 0 3px; color: #000; }
        .item-sub { font-size: 0.9em; color: #000; margin: 0 0 4px; }
        .total td { font-weight: bold; font-size: 1.05em; }
        .ops { margin-top: 7px; font-size: 0.9em; color: #000; }
        .legal { margin: 9px 0 5px; font-size: 0.9em; }
        .sigs { width: 100%; margin-top: 10px; border-collapse: collapse; }
        .sigs td { width: 50%; text-align: center; font-size: 0.85em; padding-top: 24px; vertical-align: top; font-weight: normal; }
        .sig-line { border-top: 1px solid #000; width: 90%; margin: 0 auto 3px; }
        .footer { margin-top: 7px; font-weight: bold; font-size: 0.85em; }
    </style>
</head>
<body>
    @php
        $logo = ($encabezado['logos'][0] ?? null);
        $marca = $logo['alt']
            ?? ($encabezado['mostrar_bellaroma'] ?? false ? 'Bellaroma' : (($encabezado['mostrar_aromas'] ?? false) ? 'Aromas' : 'GELIA'));
        $items = collect($comprobante->creditos_detalle ?? []);
        $generadores = $items->pluck('generado_por')->filter()->unique()->implode(', ');
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

    <div class="meta"><strong>Folio:</strong> {{ $comprobante->folio }}
        · {{ optional($comprobante->aplicado_at ?? $comprobante->created_at)->format('d/m/Y H:i') }}</div>
    @if($comprobante->sucursal || $comprobante->caja || $comprobante->referencia_venta)
        <div class="meta">
            @if($comprobante->sucursal)<strong>Suc:</strong> {{ $comprobante->sucursal }}@endif
            @if($comprobante->caja)@if($comprobante->sucursal) · @endif<strong>Caja:</strong> {{ $comprobante->caja }}@endif
            @if($comprobante->referencia_venta)@if($comprobante->sucursal || $comprobante->caja) · @endif<strong>Venta:</strong> {{ $comprobante->referencia_venta }}@endif
        </div>
    @endif
    <div class="meta"><strong>Cliente:</strong> {{ $comprobante->cliente?->nombre }}
        (#{{ $comprobante->cliente?->numero_cliente }})</div>

    <div class="rule"></div>

    <table class="row"><tr>
        <td>Saldo anterior</td>
        <td class="r">${{ number_format((float) $comprobante->saldo_anterior, 2) }}</td>
    </tr></table>

    <div class="section">Saldos utilizados ({{ $items->count() }})</div>
    @foreach($items as $item)
        <table class="row"><tr>
            <td>{{ $item['folio'] ?? '' }}@if(!empty($item['canal_origen'])) · {{ $item['canal_origen'] }}@endif</td>
            <td class="r">${{ number_format((float) ($item['monto'] ?? 0), 2) }}</td>
        </tr></table>
        @if(!empty($item['documento_origen']))
            <div class="item-sub">Doc: {{ $item['documento_origen'] }}</div>
        @endif
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

    <div class="ops">
        @if($generadores)
            SAF: {{ $generadores }} ·
        @endif
        Caja: {{ $comprobante->generadoPor?->name }}
    </div>

    <p class="legal">Firma de autorización.</p>

    <table class="sigs"><tr>
        <td><div class="sig-line"></div>Cliente</td>
        <td><div class="sig-line"></div>Cajera</td>
    </tr></table>

    <div class="center footer">No es un comprobante fiscal.</div>
</body>
</html>
