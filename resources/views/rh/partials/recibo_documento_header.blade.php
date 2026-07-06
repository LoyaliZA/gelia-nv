<div class="page-header">
    <div class="header-rule"></div>
    <table style="width:100%; border-collapse:collapse; margin-bottom:8px;">
        <tr>
            <td style="width:48%; vertical-align:middle; border:none; padding:0;">
                @foreach($encabezado['logos'] as $logo)
                    @if(!empty($logo['base64']))
                        <img
                            src="data:image/png;base64,{{ $logo['base64'] }}"
                            class="logo-recibo"
                            alt="{{ $logo['alt'] }}"
                        />
                    @else
                        <span style="font-size:10px; font-weight:bold;">{{ $logo['alt'] }}</span>
                    @endif
                @endforeach
            </td>
            <td style="width:52%; vertical-align:middle; text-align:right; border:none; padding:0;">
                <div class="doc-label">{{ $docLabel ?? 'Recibo de pago' }}</div>
                <div class="doc-title">{{ $docTitle ?? 'Documento' }}</div>
                @if(!empty($docMeta))
                    <div class="doc-meta">{!! $docMeta !!}</div>
                @endif
            </td>
        </tr>
    </table>
</div>
