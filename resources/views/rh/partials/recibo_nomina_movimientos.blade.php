@php
    $cols = $movimientosColumnas ?? [];
    $mostrarConcepto = empty($compacto) ? true : false;
    $limiteConcepto = !empty($modoUltraCompacto) ? 36 : 52;
@endphp

@if(count($cols) > 1)
    <table class="cols-detalle" style="width:100%; border-collapse:collapse;">
        <tr>
            @foreach($cols as $columna)
                <td style="vertical-align:top; width:{{ round(100 / count($cols), 2) }}%; padding:0 3px;">
                    <table class="data">
                        <thead>
                            <tr>
                                <th class="tipo">Tipo</th>
                                <th class="fecha">Fecha</th>
                                @if($mostrarConcepto)
                                    <th>Concepto</th>
                                @else
                                    <th class="ref">Ref.</th>
                                @endif
                                <th style="text-align:right;">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($columna as $mov)
                                <tr>
                                    <td class="tipo">{{ $mov['tipo'] }}</td>
                                    <td class="fecha">{{ $mov['fecha'] }}</td>
                                    @if($mostrarConcepto)
                                        <td>{{ \Illuminate\Support\Str::limit($mov['concepto'], $limiteConcepto) }}</td>
                                    @else
                                        <td class="ref">{{ $mov['ref'] }}</td>
                                    @endif
                                    <td class="monto">${{ number_format($mov['monto'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $mostrarConcepto ? 4 : 4 }}">&nbsp;</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
            @endforeach
        </tr>
    </table>
@else
    <table class="data">
        <thead>
            <tr>
                <th class="tipo">Tipo</th>
                <th class="fecha">Fecha</th>
                <th class="ref">Ref.</th>
                <th>Concepto</th>
                <th style="text-align:right;">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($movimientos as $mov)
                <tr>
                    <td class="tipo">{{ $mov['tipo'] }}</td>
                    <td class="fecha">{{ $mov['fecha'] }}</td>
                    <td class="ref">{{ $mov['ref'] }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($mov['concepto'], $limiteConcepto) }}</td>
                    <td class="monto">${{ number_format($mov['monto'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
