<table class="footer-row">
    <tr>
        @foreach($firmas as $firma)
            <td style="width:{{ $anchoColumna ?? round(100 / max(count($firmas), 1), 0) }}%;">
                <div class="firma-block">
                    <div class="firma-img-slot">
                        @if(!empty($firma['imagen_base64']))
                            <img
                                src="data:image/png;base64,{{ $firma['imagen_base64'] }}"
                                class="firma-img"
                                alt="{{ $firma['label'] ?? 'Firma' }}"
                            />
                        @endif
                    </div>
                    <div class="firma-linea">{{ $firma['label'] ?? 'Firma' }}</div>
                </div>
            </td>
        @endforeach
    </tr>
</table>
