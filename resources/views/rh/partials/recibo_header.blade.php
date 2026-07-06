@php
    $docLabel = $docLabel ?? 'Documento RH';
    $docTitulo = $docTitulo ?? 'Recibo';
    $docSubtitulo = $docSubtitulo ?? null;
    $docFolio = $docFolio ?? null;
    $docFecha = $docFecha ?? now()->format('d/m/Y');
@endphp

<div class="header-wrapper">
    <div class="header-accent-rule"></div>
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:50%; vertical-align:middle;">
                <table style="border-collapse:collapse;">
                    <tr>
                        @foreach($encabezado['logos'] as $index => $logo)
                            @if($index > 0)
                                <td style="vertical-align:middle; padding:0 16px;">
                                    <div style="width:1px; height:80px; background:#8B5CF6; opacity:0.5;"></div>
                                </td>
                            @endif
                            <td style="vertical-align:middle;">
                                @if(!empty($logo['base64']))
                                    <img
                                        src="data:image/png;base64,{{ $logo['base64'] }}"
                                        style="height:88px; max-width:210px; display:block;"
                                        alt="{{ $logo['alt'] }}"
                                    />
                                @else
                                    <span style="font-family:'Montserrat', 'DejaVu Sans', Arial, sans-serif; font-size:13px; font-weight:800; color:#0F172A; letter-spacing:2px;">{{ $logo['alt'] }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </table>
            </td>
            <td style="width:50%; vertical-align:middle; text-align:right;">
                <div class="doc-title-label">{{ $docLabel }}</div>
                <div class="doc-title-main">{{ $docTitulo }}</div>
                <div class="doc-title-meta">
                    @if(!empty($docSubtitulo))
                        Folio <strong>{{ $docSubtitulo }}</strong>
                    @elseif(!empty($docFolio))
                        Folio <strong>{{ $docFolio }}</strong>
                    @endif
                    &nbsp;&middot;&nbsp;
                    Fecha <strong>{{ $docFecha }}</strong>
                </div>
            </td>
        </tr>
    </table>
    <div class="section-divider" style="margin-top:14px; margin-bottom:0;"></div>
</div>
