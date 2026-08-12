<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $comprobante->folio }}</title>
    @php
        $perfil = $perfil ?? '80mm';
        $anchoMm = match ($perfil) {
            '58mm' => 58,
            'carta' => 210,
            default => 80,
        };
        $fontPx = match ($perfil) {
            '58mm' => 13,
            'carta' => 16,
            default => 15,
        };
        $logoMax = match ($perfil) {
            '58mm' => 112,
            'carta' => 176,
            default => 144,
        };
        $esReimpresion = !empty($esReimpresion);
        $autoprint = $autoprint ?? true;
        $logo = ($encabezado['logos'][0] ?? null);
        $marca = $logo['alt']
            ?? ($encabezado['mostrar_bellaroma'] ?? false ? 'Bellaroma' : (($encabezado['mostrar_aromas'] ?? false) ? 'Aromas' : 'GELIA'));
    @endphp
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #e8e8e8;
            color: #000;
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            padding: 10px 12px;
            background: #fff;
            border-bottom: 1px solid #ccc;
            font-size: 12px;
        }
        .toolbar button, .toolbar a {
            appearance: none;
            border: 1px solid #222;
            background: #111;
            color: #fff;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
            text-decoration: none;
            cursor: pointer;
        }
        .toolbar .hint {
            color: #444;
            max-width: 420px;
            line-height: 1.35;
        }
        .ticket-wrap {
            display: flex;
            justify-content: center;
            padding: 16px 8px 32px;
        }
        .ticket {
            width: {{ $anchoMm }}mm;
            max-width: 100%;
            background: #fff;
            color: #000;
            padding: 3mm 3mm 5mm;
            font-size: {{ $fontPx }}px;
            font-weight: 600;
            line-height: 1.4;
            box-shadow: 0 2px 12px rgba(0,0,0,.12);
        }
        .center { text-align: center; }
        .muted { color: #222; font-weight: 600; }
        .logo {
            display: block;
            margin: 0 auto 8px;
            max-width: 98%;
            max-height: {{ $logoMax }}px;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .marca {
            font-weight: 900;
            font-size: 1.25em;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin: 2px 0 0;
        }
        .titulo {
            font-size: 0.95em;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin: 4px 0 0;
        }
        .badge {
            font-weight: 900;
            letter-spacing: 0.18em;
            font-size: 0.85em;
            margin-bottom: 6px;
        }
        .rule {
            border: 0;
            border-top: 1.5px dashed #000;
            margin: 10px 0;
        }
        .meta, .block { margin: 3px 0; word-break: break-word; font-weight: 700; }
        .meta strong, .block strong { font-weight: 900; }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 3px 0;
            font-weight: 700;
        }
        .row span:last-child { font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; font-weight: 800; }
        .section {
            font-size: 0.9em;
            font-weight: 900;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin: 10px 0 4px;
        }
        .item { margin: 6px 0 0; padding-left: 3px; border-left: 2.5px solid #000; font-weight: 700; }
        .item .sub { font-size: 0.92em; color: #222; font-weight: 600; }
        .total { font-weight: 900; font-size: 1.15em; margin-top: 6px; }
        .legal {
            margin-top: 12px;
            margin-bottom: 18px;
            font-size: 0.95em;
            font-weight: 600;
            line-height: 1.4;
        }
        .sigs { margin-top: 28px; }
        .sig {
            margin-top: 42px;
            text-align: center;
            font-size: 0.95em;
            font-weight: 700;
        }
        .sig::before {
            content: "";
            display: block;
            border-top: 1.5px solid #000;
            width: 78%;
            margin: 0 auto 4px;
        }
        .footer { margin-top: 12px; font-size: 0.95em; font-weight: 800; }

        @page {
            size: {{ $anchoMm }}mm auto;
            margin: 0;
        }

        @media print {
            html, body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .toolbar { display: none !important; }
            .ticket-wrap {
                padding: 0 !important;
                display: block !important;
            }
            .ticket {
                width: {{ $anchoMm }}mm !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 2mm 2.5mm 4mm !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir</button>
        <a href="{{ route('saldos_favor.caja.descargar', ['comprobante' => $comprobante, 'perfil' => $perfil]) }}">Descargar PDF</a>
        <a href="{{ route('saldos_favor.caja.imprimir', [
            'comprobante' => $comprobante,
            'perfil' => $perfil,
            'autoprint' => 0,
        ] + (request()->boolean('reimpresion') ? ['reimpresion' => 1] : [])) }}">Solo vista</a>
        <span class="hint">En el diálogo de impresión usa escala <strong>100%</strong> (sin “ajustar al área de impresión”). El alto se corta al contenido.</span>
    </div>

    <div class="ticket-wrap">
        <article class="ticket">
            @if($esReimpresion)
                <div class="center badge">REIMPRESIÓN</div>
            @endif

            <div class="center">
                @if(!empty($logo['base64']))
                    <img
                        class="logo"
                        src="data:image/png;base64,{{ $logo['base64'] }}"
                        alt="{{ $logo['alt'] ?? $marca }}"
                    />
                @else
                    <div class="marca">{{ $marca }}</div>
                @endif
                <div class="titulo">Aplicación de saldo a favor</div>
            </div>

            <hr class="rule">

            <div class="meta"><strong>Folio:</strong> {{ $comprobante->folio }}</div>
            <div class="meta"><strong>Fecha:</strong> {{ optional($comprobante->aplicado_at ?? $comprobante->created_at)->format('d/m/Y H:i') }}</div>
            @if($comprobante->sucursal)
                <div class="meta"><strong>Sucursal:</strong> {{ $comprobante->sucursal }}</div>
            @endif
            @if($comprobante->caja || $comprobante->referencia_venta)
                <div class="meta">
                    @if($comprobante->caja)<strong>Caja:</strong> {{ $comprobante->caja }}@endif
                    @if($comprobante->referencia_venta)
                        @if($comprobante->caja) · @endif
                        <strong>Venta:</strong> {{ $comprobante->referencia_venta }}
                    @endif
                </div>
            @endif

            <hr class="rule">

            <div class="block"><strong>Cliente:</strong> {{ $comprobante->cliente?->nombre }}</div>
            <div class="block"><strong>No. cliente:</strong> {{ $comprobante->cliente?->numero_cliente }}</div>

            <hr class="rule">

            <div class="row"><span>Saldo anterior</span><span>${{ number_format((float) $comprobante->saldo_anterior, 2) }}</span></div>

            <div class="section">Saldos utilizados</div>
            @foreach(($comprobante->creditos_detalle ?? []) as $item)
                <div class="item">
                    <div><strong>{{ $item['folio'] ?? '' }}</strong> · {{ $item['canal_origen'] ?? '' }}</div>
                    @if(!empty($item['documento_origen']))
                        <div class="sub">Origen: {{ $item['documento_origen'] }}</div>
                    @endif
                    <div class="row"><span>Aplicado</span><span>${{ number_format((float) ($item['monto'] ?? 0), 2) }}</span></div>
                </div>
            @endforeach

            <hr class="rule">

            <div class="row total"><span>TOTAL USADO</span><span>${{ number_format((float) $comprobante->monto_aplicado, 2) }}</span></div>
            <div class="row"><span>Saldo restante</span><span>${{ number_format((float) $comprobante->saldo_restante, 2) }}</span></div>

            <hr class="rule">

            @php
                $generadores = collect($comprobante->creditos_detalle ?? [])->pluck('generado_por')->filter()->unique()->implode(', ');
            @endphp
            @if($generadores)
                <div class="block muted">SAF registrado por: {{ $generadores }}</div>
            @endif
            <div class="block muted">Encargado de caja: {{ $comprobante->generadoPor?->name }}</div>

            <p class="legal">Firma de autorización.</p>

            <div class="sigs">
                <div class="sig">Firma del cliente</div>
                <div class="sig">Firma de la cajera</div>
            </div>

            <div class="center footer">No es un comprobante fiscal.</div>
        </article>
    </div>

    @if($autoprint)
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 250);
            });
        </script>
    @endif
</body>
</html>
