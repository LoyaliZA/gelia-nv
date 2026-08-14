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
            '58mm' => 80,
            'carta' => 128,
            default => 108,
        };
        $esReimpresion = !empty($esReimpresion);
        $autoprint = $autoprint ?? true;
        $logo = ($encabezado['logos'][0] ?? null);
        $marca = $logo['alt']
            ?? ($encabezado['mostrar_bellaroma'] ?? false ? 'Bellaroma' : (($encabezado['mostrar_aromas'] ?? false) ? 'Aromas' : 'GELIA'));
        $items = collect($comprobante->creditos_detalle ?? []);
        $generadores = $items->pluck('generado_por')->filter()->unique()->implode(', ');
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
            font-size: 11px;
        }
        .toolbar button, .toolbar a {
            appearance: none;
            border: 1px solid #222;
            background: #111;
            color: #fff;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 11px;
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
            padding: 3mm 3mm 4mm;
            font-size: {{ $fontPx }}px;
            font-weight: 500;
            line-height: 1.35;
            box-shadow: 0 2px 12px rgba(0,0,0,.12);
        }
        .center { text-align: center; }
        .muted { color: #333; }
        .logo {
            display: block;
            margin: 0 auto 5px;
            max-width: 75%;
            max-height: {{ $logoMax }}px;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .marca {
            font-weight: 500;
            font-size: 1.15em;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin: 0;
        }
        .titulo {
            font-size: 0.9em;
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin: 3px 0 0;
        }
        .badge {
            font-weight: 500;
            letter-spacing: 0.14em;
            font-size: 0.82em;
            margin-bottom: 4px;
        }
        .rule {
            border: 0;
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .meta { margin: 3px 0; word-break: break-word; }
        .meta strong { font-weight: 500; }
        .row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
            margin: 3px 0;
        }
        .row .amt {
            font-variant-numeric: tabular-nums;
            text-align: right;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .row .lbl { min-width: 0; overflow-wrap: anywhere; }
        .section {
            font-size: 0.88em;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin: 8px 0 4px;
            color: #333;
        }
        .item-sub {
            font-size: 0.88em;
            color: #333;
            margin: 0 0 4px 0;
            padding-left: 2px;
        }
        .total { margin-top: 6px; font-size: 1.08em; }
        .ops { margin-top: 8px; font-size: 0.9em; color: #333; line-height: 1.35; }
        .legal {
            margin: 10px 0 6px;
            font-size: 0.9em;
            line-height: 1.35;
        }
        .sigs {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }
        .sig {
            flex: 1;
            text-align: center;
            font-size: 0.85em;
            margin-top: 26px;
        }
        .sig::before {
            content: "";
            display: block;
            border-top: 1px solid #000;
            width: 100%;
            margin: 0 auto 4px;
        }
        .footer { margin-top: 8px; font-size: 0.85em; }

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
                padding: 2.5mm 3mm 3.5mm !important;
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

            <hr class="rule">

            <div class="row"><span class="lbl">Saldo anterior</span><span class="amt">${{ number_format((float) $comprobante->saldo_anterior, 2) }}</span></div>

            <div class="section">Saldos utilizados ({{ $items->count() }})</div>
            @foreach($items as $item)
                <div class="row">
                    <span class="lbl">{{ $item['folio'] ?? '' }}@if(!empty($item['canal_origen'])) · {{ $item['canal_origen'] }}@endif</span>
                    <span class="amt">${{ number_format((float) ($item['monto'] ?? 0), 2) }}</span>
                </div>
                @if(!empty($item['documento_origen']))
                    <div class="item-sub">Doc: {{ $item['documento_origen'] }}</div>
                @endif
            @endforeach

            <hr class="rule">

            <div class="row total"><span class="lbl">TOTAL USADO</span><span class="amt">${{ number_format((float) $comprobante->monto_aplicado, 2) }}</span></div>
            <div class="row"><span class="lbl">Saldo restante</span><span class="amt">${{ number_format((float) $comprobante->saldo_restante, 2) }}</span></div>

            <div class="ops">
                @if($generadores)
                    SAF: {{ $generadores }} ·
                @endif
                Caja: {{ $comprobante->generadoPor?->name }}
            </div>

            <p class="legal">Firma de autorización.</p>

            <div class="sigs">
                <div class="sig">Cliente</div>
                <div class="sig">Cajera</div>
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
