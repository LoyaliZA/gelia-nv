{{-- Encabezado compacto por día de validación. --}}
<div class="dia-block">
    <p class="dia-fecha">{{ $dia['fecha_label'] ?? '' }}</p>
    <p class="dia-meta {{ ! empty($dia['resumen']['incidencias']) ? 'dia-meta--alerta' : '' }}">
        {{ $dia['resumen']['meta_linea'] ?? '' }}
        @if (empty($dia['resumen']['pagos_es_pedido_completo']))
            <span class="dia-alcance"> · Pagos: exhibiciones filtradas</span>
        @endif
    </p>
</div>
