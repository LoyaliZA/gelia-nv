<style>
    @page {
        margin: 8mm 10mm;
    }

    * { box-sizing: border-box; }

    html, body {
        font-family: Helvetica, Arial, 'DejaVu Sans', sans-serif;
        color: #000;
        line-height: 1.4;
        margin: 0;
        padding: 0;
        background: #fff;
    }

    /* Bloque centrado con desglose vertical — sin forzar altura de página */
    .page-wrap {
        width: 100%;
        max-width: 7.4in;
        margin: 0 auto;
        padding: 4mm 2mm 6mm;
        page-break-inside: avoid;
    }

    body.orientacion-horizontal .page-wrap {
        max-width: 100%;
        padding: 3mm 0 5mm;
    }

    .page-header {
        margin-bottom: 10px;
    }

    .page-body {
        width: 100%;
    }

    .bloque-seccion {
        margin-bottom: 18px;
    }

    body.layout-expandido .bloque-seccion {
        margin-bottom: 22px;
    }

    .movimientos-container {
        margin-bottom: 4px;
    }

    .movimientos-container table.data {
        margin-bottom: 0;
    }

    .logo-recibo {
        display: block;
        height: 88px;
        max-width: 240px;
        filter: grayscale(100%);
    }

    body.orientacion-horizontal .logo-recibo {
        height: 80px;
        max-width: 220px;
    }

    body.layout-expandido.orientacion-vertical .logo-recibo {
        height: 92px;
        max-width: 260px;
    }

    body.orientacion-vertical {
        font-size: 9px;
    }

    body.orientacion-horizontal {
        font-size: 9px;
    }

    body.orientacion-horizontal .doc-title {
        font-size: 16px;
    }

    body.orientacion-horizontal .section-title {
        font-size: 8px;
        margin: 8px 0 5px;
    }

    body.orientacion-horizontal table.data th,
    body.orientacion-horizontal table.data td {
        padding: 4px 6px;
        font-size: 8px;
    }

    body.orientacion-horizontal table.data th {
        font-size: 7.5px;
    }

    body.orientacion-horizontal table.data.kv td,
    body.orientacion-horizontal table.inline-base td {
        font-size: 8px;
        padding: 4px 6px;
    }

    body.orientacion-horizontal .firma-linea {
        margin-top: 24px;
    }

    body.orientacion-horizontal table.totales tr.neto td {
        font-size: 11px;
        padding: 6px 8px;
    }

    .header-rule {
        width: 100%;
        height: 1.5px;
        background: #000;
        margin-bottom: 8px;
    }

    .doc-label {
        font-size: 7px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #444;
    }

    body.orientacion-horizontal .doc-label {
        font-size: 7.5px;
    }

    .doc-title {
        font-size: 14px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #000;
        line-height: 1.1;
        margin: 3px 0;
    }

    .doc-meta {
        font-size: 8px;
        color: #333;
        margin-top: 2px;
    }

    body.orientacion-horizontal .doc-meta {
        font-size: 8px;
    }

    .section-title {
        font-size: 8.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #000;
        border-bottom: 1px solid #000;
        padding-bottom: 4px;
        margin: 16px 0 8px;
    }

    .section-title.primero {
        margin-top: 0;
    }

    table.data {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
        page-break-inside: auto;
    }

    table.data th,
    table.data td {
        padding: 5px 6px;
        border: 1px solid #ccc;
        text-align: left;
        font-size: 8px;
        vertical-align: top;
    }

    table.data th {
        background: #f0f0f0;
        font-weight: bold;
        text-transform: uppercase;
        font-size: 7.5px;
        color: #333;
    }

    table.data td.monto {
        text-align: right;
        font-weight: bold;
        white-space: nowrap;
    }

    table.data td.ref,
    table.data td.fecha,
    table.data td.tipo {
        white-space: nowrap;
    }

    /* KV sin bordes (vertical, referencia intento) */
    table.kv {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
    }

    table.kv td {
        border: none;
        padding: 4px 0;
        font-size: 8px;
    }

    table.kv .label {
        font-weight: bold;
        width: 40%;
        color: #444;
        background: transparent;
    }

    table.kv .value {
        width: 60%;
    }

    /* KV con bordes (horizontal) */
    table.data.kv td {
        border: 1px solid #ddd;
        padding: 4px 6px;
    }

    table.data.kv .label {
        background: #fafafa;
        width: 38%;
    }

    table.data.kv .value {
        width: 62%;
    }

    table.inline-base {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
    }

    table.inline-base td {
        border: 1px solid #ddd;
        padding: 5px 6px;
        font-size: 7.5px;
        text-align: center;
    }

    table.inline-base .lbl {
        font-weight: bold;
        text-transform: uppercase;
        font-size: 6.5px;
        color: #555;
        display: block;
        margin-bottom: 3px;
    }

    table.totales {
        width: 100%;
        border-collapse: collapse;
        page-break-inside: avoid;
        margin: 0;
    }

    table.totales td {
        padding: 3px 5px;
        border: 1px solid #ccc;
        font-size: 8px;
    }

    table.totales .label {
        text-align: left;
        font-weight: bold;
        background: transparent;
        width: auto;
        border: none;
    }

    table.totales .monto {
        text-align: right;
        font-weight: bold;
        white-space: nowrap;
        border: none;
    }

    table.totales tr.neto td {
        background: #000;
        color: #fff;
        font-size: 10px;
        font-weight: bold;
        border-color: #000;
        padding: 8px 10px;
    }

    .cols-2, .cols-perc-ded, .cols-top-horizontal, .cols-detalle {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
        page-break-inside: avoid;
    }

    .cols-2 > tbody > tr > td,
    .cols-perc-ded > tbody > tr > td {
        width: 50%;
        vertical-align: top;
        border: none;
        padding: 0 8px 0 0;
    }

    .cols-2 > tbody > tr > td:last-child,
    .cols-perc-ded > tbody > tr > td:last-child {
        padding: 0 0 0 8px;
    }

    .cols-top-horizontal > tbody > tr > td {
        width: 25%;
        vertical-align: top;
        padding: 0 5px 0 0;
        border: none;
    }

    .cols-top-horizontal > tbody > tr > td:last-child {
        padding: 0;
    }

    body.layout-expandido.orientacion-vertical {
        font-size: 10px;
    }

    body.layout-expandido.orientacion-vertical .section-title {
        margin: 12px 0 7px;
        font-size: 9px;
    }

    body.layout-expandido.orientacion-vertical table.data th,
    body.layout-expandido.orientacion-vertical table.data td,
    body.layout-expandido.orientacion-vertical table.kv td {
        padding: 5px 7px;
        font-size: 9px;
    }

    body.layout-expandido.orientacion-vertical .firma-linea {
        margin-top: 32px;
    }

    body.layout-expandido.orientacion-horizontal .section-title {
        margin: 10px 0 7px;
    }

    body.layout-expandido.orientacion-horizontal table.data th,
    body.layout-expandido.orientacion-horizontal table.data td {
        padding: 5px 7px;
    }

    .page-foot {
        margin-top: 22px;
        padding-top: 14px;
        border-top: 1px solid #ccc;
        page-break-inside: avoid;
    }

    .empty-note {
        font-style: italic;
        color: #666;
        font-size: 8px;
        margin: 2px 0;
    }

    .footer-row {
        width: 100%;
        border-collapse: collapse;
    }

    .footer-row td {
        vertical-align: bottom;
        border: none;
        padding: 0;
    }

    .firma-block {
        text-align: center;
        width: 100%;
    }

    .firma-linea {
        border-top: 1px solid #000;
        margin-top: 8px;
        padding-top: 5px;
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        display: inline-block;
        width: 80%;
    }

    .firma-img-slot {
        min-height: 44px;
        margin-bottom: 4px;
        text-align: center;
    }

    .firma-img {
        max-height: 44px;
        max-width: 180px;
        display: block;
        margin: 0 auto 4px;
        filter: grayscale(100%);
    }

    .observaciones-box {
        border: 1px solid #ccc;
        padding: 8px 10px;
        font-size: 8px;
        line-height: 1.45;
        min-height: 36px;
        white-space: pre-wrap;
        background: #fafafa;
    }

    .observaciones-box.vacio {
        font-style: italic;
        color: #666;
    }

    body.orientacion-horizontal .firma-linea {
        font-size: 8px;
    }

    .footer {
        font-size: 6.5px;
        color: #666;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        padding-top: 8px;
    }

    body.ultra-compact { font-size: 7px; }

    body.ultra-compact table.data th,
    body.ultra-compact table.data td {
        padding: 2px 4px;
        font-size: 6.5px;
    }

    body.ultra-compact .section-title {
        margin: 8px 0 4px;
    }

    body.ultra-compact .bloque-seccion {
        margin-bottom: 10px;
    }
</style>
