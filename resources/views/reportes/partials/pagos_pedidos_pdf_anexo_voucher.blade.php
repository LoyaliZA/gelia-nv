{{-- Tarjeta de voucher con encabezado inseparable. --}}
@php
    $layout = $voucher['layout'] ?? 'medio';
    $enc = $voucher['encabezado'] ?? [];
@endphp

<div class="anexo-voucher-card anexo-voucher-card--{{ $layout }}">
    <div class="anexo-voucher-header">
        <table class="anexo-voucher-meta">
            @foreach (array_chunk($enc, 2) as $fila)
                <tr>
                    @foreach ($fila as $campo)
                        <td>
                            <span class="anexo-meta-label">{{ $campo['label'] }}</span>
                            <span class="anexo-meta-valor">{{ $campo['valor'] }}</span>
                        </td>
                    @endforeach
                    @if (count($fila) === 1)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </table>
    </div>

    <div class="anexo-voucher-body">
        @if (! empty($voucher['imagen_path']))
            <img
                src="{{ $voucher['imagen_path'] }}"
                alt="Voucher {{ $enc[1]['valor'] ?? '' }}"
                class="anexo-voucher-img anexo-voucher-img--{{ $layout }}"
            >
        @elseif (! empty($voucher['es_pdf']))
            <p class="anexo-voucher-pdf">
                Comprobante PDF: <strong>{{ $voucher['nombre_archivo'] ?? 'archivo.pdf' }}</strong>
                — consultar en el sistema (no incrustado en este reporte).
            </p>
        @else
            <p class="anexo-voucher-pdf">Archivo no disponible para visualización en PDF.</p>
        @endif
    </div>
</div>
