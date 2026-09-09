@php
    $dona = \App\Support\ContabilidadReporteAssets::generarDonaPlataformasPng($grafica);
@endphp
@if(!empty($dona))
    <div class="chart-wrap">
        @if(!empty($titulo))
            <div class="chart-title">{{ $titulo }}</div>
        @endif
        <table class="chart-dona-layout" cellpadding="0" cellspacing="0">
            <tr>
                <td class="chart-dona-image">
                    <img
                        src="data:image/png;base64,{{ $dona['base64'] }}"
                        alt="{{ $titulo ?? 'Distribución por plataforma' }}"
                        width="160"
                        height="160"
                    />
                </td>
                <td class="chart-dona-legend">
                    <table class="chart-dona-legend-table" cellpadding="0" cellspacing="0">
                        @foreach($dona['segmentos'] as $segmento)
                            <tr>
                                <td class="chart-dona-swatch" bgcolor="{{ $segmento['color'] }}" style="background-color: {{ $segmento['color'] }};">&nbsp;</td>
                                <td class="chart-dona-label">{{ $segmento['label'] }}</td>
                                <td class="chart-dona-value">${{ number_format($segmento['valor'], 0) }}</td>
                                <td class="chart-dona-pct">{{ $segmento['pct'] }}%</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>
        <div class="chart-caption">{{ $leyenda ?? 'Participación de cada plataforma sobre el ingreso total del periodo' }}</div>
    </div>
@endif
