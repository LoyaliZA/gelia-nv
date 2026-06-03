<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; table-layout: fixed;">
    <tr>
        <td height="{{ $rowFolioH }}mm" valign="top" style="font-size: {{ $fsFolio }}mm; font-weight: bold; line-height: {{ round($fsFolio * 1.15, 1) }}mm; word-break: break-all; overflow: hidden;">
            {{ $item['folio'] }}
        </td>
    </tr>
    <tr>
        <td height="{{ $rowNombreH }}mm" valign="top" style="font-size: {{ $fsNombre }}mm; line-height: {{ round($fsNombre * 1.15, 1) }}mm; overflow: hidden;">
            {{ $item['nombre'] }}
        </td>
    </tr>
    <tr>
        <td height="{{ $rowTipoH }}mm" valign="top" style="font-size: {{ $fsTipo }}mm; line-height: {{ round($fsTipo * 1.15, 1) }}mm; color: #525252; overflow: hidden;">
            {{ $item['tipo'] }}
        </td>
    </tr>
    <tr>
        <td height="{{ $rowSpacerH }}mm" valign="top" style="font-size: 1px; line-height: 1px;">&nbsp;</td>
    </tr>
    <tr>
        <td height="{{ $rowLogosH }}mm" align="center" valign="middle" style="overflow: hidden;">
            <table cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 auto;">
                <tr>
                    <td valign="middle" style="padding: 0;">
                        <img
                            src="data:image/png;base64,{{ $logo_aromas_base64 }}"
                            alt="Aromas"
                            style="height: {{ $logoAltoPx }}px; width: {{ $aromasAnchoPx }}px; display: block;"
                        />
                    </td>
                    <td valign="middle" style="padding: 0 {{ $sepPadPx }}px;">
                        <div style="width: 1px; height: {{ $sepAltoPx }}px; background-color: #cbd5e1;"></div>
                    </td>
                    <td valign="middle" style="padding: 0;">
                        <img
                            src="data:image/png;base64,{{ $logo_bellaroma_base64 }}"
                            alt="Bellaroma"
                            style="height: {{ $logoAltoPx }}px; width: {{ $bellaromaAnchoPx }}px; display: block;"
                        />
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td height="{{ $rowLeyendaH }}mm" valign="bottom" style="font-size: {{ $fsLeyenda }}mm; line-height: {{ round($fsLeyenda * 1.15, 1) }}mm; color: #737373; overflow: hidden;">
            Escanea para consultar
        </td>
    </tr>
</table>
