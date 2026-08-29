{{-- Ficha visual por pedido + exhibiciones + vouchers. --}}
@php
    $ficha = $pedido['ficha'] ?? [];
    $campos = $ficha['campos'] ?? [];
    $exhib = $pedido['exhibiciones'] ?? [];
    $filas = $exhib['filas'] ?? [];
    $contexto = $exhib['contexto'] ?? [];
@endphp

<div class="pedido-ficha">
    <table class="pedido-meta-grid">
        @foreach (array_chunk($campos, 2) as $fila)
            <tr>
                @foreach ($fila as $campo)
                    <td class="pedido-meta-cell">
                        <span class="pedido-meta-label">{{ $campo['label'] }}</span>
                        <span class="pedido-meta-valor">{{ $campo['valor'] }}</span>
                    </td>
                @endforeach
                @if (count($fila) === 1)
                    <td class="pedido-meta-cell"></td>
                @endif
            </tr>
        @endforeach
    </table>

    <div class="pedido-badges">
        <span class="pedido-badge pedido-badge--cierre">{{ $ficha['estado_cierre'] ?? '—' }} · v{{ $ficha['version'] ?? 1 }}</span>
        <span class="pedido-badge pedido-badge--cobertura">{{ $ficha['estado_cobertura'] ?? '—' }}</span>
    </div>

    <table class="pedido-bloques">
        <tr>
            <td class="pedido-bloque">
                <p class="pedido-bloque-titulo">Composición del pedido</p>
                @foreach ($ficha['composicion'] ?? [] as $filaFin)
                    <div class="pedido-fin-fila {{ ! empty($filaFin['destacado']) ? 'pedido-fin-fila--destacado' : '' }}">
                        <span class="pedido-fin-label">{{ $filaFin['label'] }}</span>
                        <span class="pedido-fin-valor">{{ $filaFin['valor'] }}</span>
                    </div>
                @endforeach
            </td>
            <td class="pedido-bloque">
                <p class="pedido-bloque-titulo">Cobertura</p>
                @foreach ($ficha['cobertura'] ?? [] as $filaFin)
                    <div class="pedido-fin-fila {{ ! empty($filaFin['destacado']) ? 'pedido-fin-fila--destacado' : '' }}">
                        <span class="pedido-fin-label">
                            {{ $filaFin['label'] }}
                            @if (! empty($filaFin['alcance']))
                                <span class="pedido-fin-alcance">({{ $filaFin['alcance'] }})</span>
                            @endif
                        </span>
                        <span class="pedido-fin-valor pedido-fin-valor--{{ $filaFin['tono'] ?? 'neutro' }}">{{ $filaFin['valor'] }}</span>
                    </div>
                @endforeach
            </td>
        </tr>
    </table>
</div>

<div class="exhibiciones-block">
    <p class="exhibiciones-titulo">Exhibiciones de pago</p>

    @if (! empty($contexto['hay_filtro']))
        <p class="exhibiciones-contexto">
            Mostrando {{ number_format((int) ($contexto['mostradas'] ?? 0)) }}
            de {{ number_format((int) ($contexto['totales'] ?? 0)) }} exhibiciones.
            Pagos coincidentes: <strong>{{ $contexto['pagos_coincidentes'] ?? '—' }}</strong>
            · Pagos válidos del pedido: <strong>{{ $contexto['pagos_pedido_completo'] ?? '—' }}</strong>
        </p>
        @if (! empty($contexto['nota']))
            <p class="exhibiciones-nota">{{ $contexto['nota'] }}</p>
        @endif
    @endif

    @if (empty($filas))
        <p class="muted">Sin exhibiciones coincidentes con los filtros aplicados.</p>
    @else
        <table class="data data-exhibiciones">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Monto</th>
                    <th>Forma</th>
                    <th>Banco</th>
                    <th>Referencia</th>
                    <th>F. reportada</th>
                    <th>F. pago</th>
                    <th>Estado</th>
                    <th>Revisor</th>
                    <th>Cobertura</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($filas as $ex)
                <tr>
                    <td class="mono">{{ $ex['numero'] }}</td>
                    <td class="right">{{ $ex['monto'] }}</td>
                    <td>{{ $ex['forma'] }}</td>
                    <td>{{ $ex['banco'] }}</td>
                    <td>{{ $ex['referencia'] }}</td>
                    <td>{{ $ex['fecha_reportada'] }}</td>
                    <td>{{ $ex['fecha_pago'] }}</td>
                    <td>{{ $ex['estado'] }}</td>
                    <td>{{ $ex['revisor'] }}</td>
                    <td class="cobertura-{{ $ex['cobertura_tono'] ?? 'neutro' }}">{{ $ex['cobertura'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
