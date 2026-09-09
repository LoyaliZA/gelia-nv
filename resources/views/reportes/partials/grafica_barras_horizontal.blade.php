@php
    $anchoPistaPt = $anchoPista ?? 200;
@endphp
<div class="chart-wrap">
    @if(!empty($titulo))
        <div class="chart-title">{{ $titulo }}</div>
    @endif
    <table class="chart-bars-table" cellpadding="0" cellspacing="0">
        @foreach($grafica['labels'] as $i => $label)
            @php
                $valor = $grafica['values'][$i];
                $pct = $grafica['porcentajes'][$i] ?? (
                    $grafica['total'] > 0 ? round(($valor / $grafica['total']) * 100, 1) : 0
                );
                $anchoBarraPt = max(2, (int) round(($pct / 100) * $anchoPistaPt));
                $anchoRestoPt = max(0, $anchoPistaPt - $anchoBarraPt);
                $colorBarra = $grafica['colores'][$i] ?? ($color ?? '#4f46e5');
            @endphp
            <tr class="chart-bar-row">
                <td class="chart-bar-label">{{ $label }}</td>
                <td class="chart-bar-track">
                    <table class="chart-bar-inner" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="{{ $anchoBarraPt }}" bgcolor="{{ $colorBarra }}" style="background-color: {{ $colorBarra }}; height: 10px; line-height: 10px; font-size: 1px;">&nbsp;</td>
                            <td width="{{ $anchoRestoPt }}" style="height: 10px; line-height: 10px; font-size: 1px;">&nbsp;</td>
                        </tr>
                    </table>
                </td>
                <td class="chart-bar-value">${{ number_format($valor, 0) }}</td>
                <td class="chart-bar-pct">{{ $pct }}%</td>
            </tr>
        @endforeach
    </table>
    <div class="chart-caption">{{ $leyenda ?? 'Barras proporcionales al ingreso total del periodo' }}</div>
</div>
