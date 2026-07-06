<div class="header-wrapper">
    <div class="header-accent-bar"></div>
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:62%; vertical-align:middle;">
                <table style="border-collapse:collapse;">
                    <tr>
                        @foreach($encabezado['logos'] as $index => $logo)
                            @if($index > 0)
                                <td style="vertical-align:middle; padding:0 14px;">
                                    <div style="width:1px; height:55px; background:#cbd5e1;"></div>
                                </td>
                            @endif
                            <td style="vertical-align:middle;">
                                @if(!empty($logo['base64']))
                                    <img
                                        src="data:image/png;base64,{{ $logo['base64'] }}"
                                        style="height:60px; max-width:130px; display:block;"
                                        alt="{{ $logo['alt'] }}"
                                    />
                                @else
                                    <span style="font-size:14px; font-weight:900; color:#1e3a8a;">{{ $logo['alt'] }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </table>
            </td>
            <td style="width:38%; vertical-align:middle; text-align:right;">
                <div style="font-size:7px; font-weight:bold; letter-spacing:3px; text-transform:uppercase; color:#94a3b8;">{{ $docLabel ?? 'Documento RH' }}</div>
                <div style="font-size:15px; font-weight:900; color:#1e3a8a; text-transform:uppercase;">{{ $docTitulo ?? 'Recibo de Incidencia' }}</div>
                @if(!empty($docSubtitulo))
                    <div style="font-size:8px; font-weight:bold; color:#2563eb; text-transform:uppercase; letter-spacing:2px; margin-top:3px;">{{ $docSubtitulo }}</div>
                @endif
            </td>
        </tr>
    </table>
    <div class="header-bottom-line"></div>
</div>
