<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Etiquetas de activos</title>
    @php
        $esVertical = $orientacion_etiqueta === 'vertical';

        if ($esVertical) {
            $etAncho = $celda_ancho_mm;
            $etAlto = $celda_alto_mm;
            $qrColW = $etAncho;
            $txtColW = $etAncho;
            $qrRowH = $qr_row_vertical_mm ?? round($etAlto * 0.40, 1);
            $bodyRowH = round($etAlto - $qrRowH, 1);
            $fsFolio = round($etAncho * 0.078, 1);
            $fsNombre = round($etAncho * 0.068, 1);
            $fsTipo = round($etAncho * 0.052, 1);
            $fsLeyenda = round($etAncho * 0.042, 1);
            $logoAlto = max(3.2, round($etAncho * 0.17, 1));
            $logoMaxW = round($etAncho * 0.32, 1);
            $innerPad = 1.2;
            $contentH = $bodyRowH - (2 * $innerPad);
        } else {
            $etAncho = $ancho_mm;
            $etAlto = $alto_mm;
            $qrColW = round($etAncho * 0.44, 1);
            $txtColW = round($etAncho - $qrColW, 1);
            $fsFolio = round($etAlto * 0.082, 1);
            $fsNombre = round($etAlto * 0.068, 1);
            $fsTipo = round($etAlto * 0.052, 1);
            $fsLeyenda = round($etAlto * 0.042, 1);
            $logoAlto = max(3.2, round($etAlto * 0.26, 1));
            $logoMaxW = round($txtColW * 0.38, 1);
            $innerPad = 1.2;
            $contentH = $etAlto - (2 * $innerPad);
        }

        $rowFolioH = round($fsFolio * 2.2, 1);
        $rowNombreH = round($fsNombre * 1.6, 1);
        $rowTipoH = round($fsTipo * 1.5, 1);
        $rowLogosH = round($logoAlto + 1.2, 1);
        $rowLeyendaH = round($fsLeyenda * 1.6, 1);
        $rowSpacerH = max(1, round($contentH - $rowFolioH - $rowNombreH - $rowTipoH - $rowLogosH - $rowLeyendaH, 1));
        $qrSizeH = round($esVertical ? min($qrColW * 0.72, $qrRowH - 2) : min($qrColW * 0.82, $etAlto * 0.84), 1);

        $pxPerMm = 3.7795275591;
        $logoAltoPx = max(22, (int) round($logoAlto * $pxPerMm));
        $aromasAnchoPx = max(1, (int) round($logoAltoPx * ($logo_aromas_w / max($logo_aromas_h, 1))));
        $bellaromaAnchoPx = max(1, (int) round($logoAltoPx * ($logo_bellaroma_w / max($logo_bellaroma_h, 1))));
        $sepAltoPx = max(14, (int) round($logoAltoPx * 0.82));
        $sepPadPx = max(6, (int) round($txtColW * $pxPerMm * 0.04));
    @endphp
    <style>
        @page {
            size: {{ $page_ancho_mm }}mm {{ $page_alto_mm }}mm;
            margin: {{ $margen_mm }}mm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #000;
        }

        .page {
            box-sizing: border-box;
            width: 100%;
            padding: 0;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .page-break-after {
            page-break-after: always;
        }

        .page-last {
            page-break-after: avoid;
        }

        .grid {
            display: block;
            width: {{ ($columnas * $celda_ancho_mm) + (max($columnas - 1, 0) * $gap_mm) }}mm;
            font-size: 0;
            line-height: 0;
        }

        .celda {
            display: inline-block;
            vertical-align: top;
            width: {{ $celda_ancho_mm }}mm;
            height: {{ $celda_alto_mm }}mm;
            margin-right: {{ $gap_mm }}mm;
            margin-bottom: {{ $gap_mm }}mm;
            overflow: hidden;
            font-size: 9pt;
            line-height: 1.2;
        }

        .grid .celda:nth-child({{ $columnas }}n) {
            margin-right: 0;
        }

        .grid .celda:nth-last-child(-n+{{ $columnas }}) {
            margin-bottom: 0;
        }

        .celda-vacia .tbl-etiqueta {
            border-color: transparent;
        }
    </style>
</head>
<body>
@foreach($paginas as $pagina)
    @php
        $celdas = $pagina;
        $resto = count($celdas) % $columnas;
        if ($resto !== 0) {
            $celdas = array_pad($celdas, count($celdas) + ($columnas - $resto), null);
        }
    @endphp
    <div class="page{{ $loop->last ? ' page-last' : ' page-break-after' }}">
        <div class="grid">
            @foreach($celdas as $item)
                <div class="celda {{ $item ? '' : 'celda-vacia' }}">
                    @if($item)
                        @if($esVertical)
                            <table class="tbl-etiqueta" width="{{ $etAncho }}mm" height="{{ $etAlto }}mm" cellpadding="0" cellspacing="0" style="border-collapse: collapse; border: 0.35mm solid #d4d4d4; table-layout: fixed;">
                                <tr>
                                    <td width="{{ $qrColW }}mm" height="{{ $qrRowH }}mm" align="center" valign="middle" style="padding: 1mm;">
                                        <img src="data:image/png;base64,{{ $item['qr_base64'] }}" alt="QR" style="width: {{ $qrSizeH }}mm; height: {{ $qrSizeH }}mm;">
                                    </td>
                                </tr>
                                <tr>
                                    <td width="{{ $txtColW }}mm" height="{{ $bodyRowH }}mm" valign="top" style="padding: {{ $innerPad }}mm;">
                                        @include('activos.partials.etiqueta_contenido_filas')
                                    </td>
                                </tr>
                            </table>
                        @else
                            <table class="tbl-etiqueta" width="{{ $etAncho }}mm" height="{{ $etAlto }}mm" cellpadding="0" cellspacing="0" style="border-collapse: collapse; border: 0.35mm solid #d4d4d4; table-layout: fixed;">
                                <tr>
                                    <td width="{{ $qrColW }}mm" height="{{ $etAlto }}mm" align="center" valign="middle" style="padding: 1mm;">
                                        <img src="data:image/png;base64,{{ $item['qr_base64'] }}" alt="QR" style="width: {{ $qrSizeH }}mm; height: {{ $qrSizeH }}mm;">
                                    </td>
                                    <td width="{{ $txtColW }}mm" height="{{ $etAlto }}mm" valign="top" style="padding: {{ $innerPad }}mm {{ $innerPad }}mm {{ $innerPad }}mm 0.5mm;">
                                        @include('activos.partials.etiqueta_contenido_filas')
                                    </td>
                                </tr>
                            </table>
                        @endif
                    @else
                        <table width="{{ $celda_ancho_mm }}mm" height="{{ $celda_alto_mm }}mm" cellpadding="0" cellspacing="0"><tr><td></td></tr></table>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endforeach
</body>
</html>
