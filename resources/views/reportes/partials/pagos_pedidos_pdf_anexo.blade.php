{{-- Anexo A — vouchers y remisiones referenciadas. --}}
@php
    $anexo = $anexo ?? [];
@endphp

@if (! ($anexo['vacio'] ?? true))
    <div class="anexo-section">
        <h2 class="anexo-titulo">{{ $anexo['titulo'] ?? 'Anexo A — Vouchers y comprobantes' }}</h2>

        @if (! empty($anexo['remisiones']))
            <div class="anexo-remisiones">
                <p class="anexo-subtitulo">Remisiones referenciadas</p>
                <p class="anexo-nota">
                    Las remisiones en PDF no se incrustan en este reporte; consulte el archivo en el sistema con los datos siguientes.
                </p>
                <table class="data anexo-remisiones-table">
                    <thead>
                        <tr>
                            <th>Folio del pedido</th>
                            <th>Folio de remisión</th>
                            <th>Nombre del archivo</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($anexo['remisiones'] as $rem)
                            <tr>
                                <td>{{ $rem['folio_pedido'] }}</td>
                                <td>{{ $rem['folio_remision'] }}</td>
                                <td>{{ $rem['nombre'] }}</td>
                                <td>{{ $rem['fecha'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @foreach ($anexo['remisiones_embebidas'] ?? [] as $rem)
            <div class="anexo-pagina-completa anexo-remision-embebida">
                <p class="anexo-subtitulo">Remisión completa — pedido {{ $rem['folio_pedido'] }} · {{ $rem['folio_remision'] }}</p>
                <p class="anexo-nota">{{ $rem['nombre'] }}</p>
                <embed
                    type="application/pdf"
                    src="{{ $rem['pdf_path'] }}"
                    class="anexo-remision-pdf"
                />
            </div>
        @endforeach

        @foreach ($anexo['paginas'] ?? [] as $pagina)
            @if (($pagina['tipo'] ?? '') === 'completo')
                @foreach ($pagina['vouchers'] ?? [] as $voucher)
                    <div class="anexo-pagina-completa">
                        @include('reportes.partials.pagos_pedidos_pdf_anexo_voucher', ['voucher' => $voucher])
                    </div>
                @endforeach
            @else
                <table class="anexo-grid">
                    <tr>
                        @foreach ($pagina['vouchers'] ?? [] as $voucher)
                            <td class="anexo-grid-cell">
                                @include('reportes.partials.pagos_pedidos_pdf_anexo_voucher', ['voucher' => $voucher])
                            </td>
                        @endforeach
                        @if (count($pagina['vouchers'] ?? []) === 1)
                            <td class="anexo-grid-cell anexo-grid-cell--empty"></td>
                        @endif
                    </tr>
                </table>
            @endif
        @endforeach
    </div>
@endif
