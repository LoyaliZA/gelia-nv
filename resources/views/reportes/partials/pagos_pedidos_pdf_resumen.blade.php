{{-- Resumen del periodo: 6 KPIs + línea informativa. --}}
@php
    $r = $resumen_periodo ?? [];
    $indicadores = $r['indicadores'] ?? [];
    $informativos = $r['informativos'] ?? [];
@endphp

<div class="resumen-block">
    <p class="resumen-title">Resumen del periodo</p>

    <table class="kpi-grid">
        @foreach (array_chunk($indicadores, 3) as $fila)
            <tr>
                @foreach ($fila as $kpi)
                    <td class="kpi-cell">
                        <span class="kpi-label">{{ $kpi['label'] }}</span>
                        <span class="kpi-value">{{ $kpi['valor'] }}</span>
                        @if (! empty($kpi['alcance']))
                            <span class="kpi-alcance">{{ $kpi['alcance'] }}</span>
                        @endif
                    </td>
                @endforeach
                @for ($i = count($fila); $i < 3; $i++)
                    <td class="kpi-cell kpi-cell--empty"></td>
                @endfor
            </tr>
        @endforeach
    </table>

    <table class="info-grid">
        <tr>
            @foreach ($informativos as $item)
                <td class="info-cell">
                    <span class="info-label">{{ $item['label'] }}</span>
                    <span class="info-value">{{ $item['valor'] }}</span>
                </td>
            @endforeach
        </tr>
    </table>

    @if (! empty($r['nota']))
        <p class="resumen-nota">{{ $r['nota'] }}</p>
    @endif
</div>
