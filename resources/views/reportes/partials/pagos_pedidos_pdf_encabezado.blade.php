{{-- Encabezado administrativo (primera página). PDF en modo claro; franja rosa GELIA suave. --}}
@php
    $h = $encabezado ?? [];
    $colorPrimario = $h['color_primario'] ?? '#ec4899';
    $colorFranja = $h['color_franja'] ?? '#fdf2f8';
@endphp

<div class="admin-header" style="background-color: {{ $colorFranja }}; border-left: 4px solid {{ $colorPrimario }};">
    <table class="admin-header-table">
        <tr>
            <td class="admin-brand" style="width: 28%; vertical-align: top;">
                <table style="border-collapse: collapse;">
                    <tr>
                        <td style="vertical-align: middle; padding-right: 8px;">
                            @include('rh.partials.gelia_isotipo_svg', ['size' => 32, 'color' => $colorPrimario])
                        </td>
                        <td style="vertical-align: middle;">
                            <div class="brand-name" style="color: {{ $colorPrimario }};">GELIA</div>
                            <div class="brand-sub">Reportes · Administración</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 72%; vertical-align: top;">
                <div class="doc-kicker">Documento administrativo</div>
                <h1 class="doc-title">{{ $h['titulo'] ?? 'Reporte administrativo de pagos de pedidos' }}</h1>
            </td>
        </tr>
    </table>

    <table class="admin-meta-grid">
        <tr>
            <td class="meta-cell">
                <span class="meta-label">Folio del reporte</span>
                <span class="meta-value">{{ $h['folio'] ?? '—' }}</span>
            </td>
            <td class="meta-cell">
                <span class="meta-label">Generado</span>
                <span class="meta-value">{{ $h['generado_at'] ?? '—' }}</span>
            </td>
        </tr>
        <tr>
            <td class="meta-cell">
                <span class="meta-label">Generado por</span>
                <span class="meta-value">{{ $h['usuario'] ?? '—' }}</span>
            </td>
            <td class="meta-cell">
                <span class="meta-label">Periodo consultado</span>
                <span class="meta-value">{{ $h['periodo'] ?? '—' }}</span>
            </td>
        </tr>
        <tr>
            <td class="meta-cell">
                <span class="meta-label">Tipo de fecha</span>
                <span class="meta-value">{{ $h['tipo_fecha'] ?? '—' }}</span>
            </td>
            <td class="meta-cell">
                <span class="meta-label">Estado del reporte</span>
                <span class="meta-value meta-badge">{{ $h['estado_reporte'] ?? '—' }}</span>
            </td>
        </tr>
        <tr>
            <td class="meta-cell">
                <span class="meta-label">Pedidos incluidos</span>
                <span class="meta-value">{{ number_format((int) ($h['total_pedidos'] ?? 0)) }}</span>
            </td>
            <td class="meta-cell meta-pagination-cell">
                <span class="meta-label">Paginación</span>
                <span class="meta-value">&nbsp;</span>
            </td>
        </tr>
    </table>
</div>

<div class="admin-header-rule"></div>
