@extends('layouts.app')

@section('title', 'Alertas de Inventario | Gelia Hub')

@section('content')
<header class="mb-8 border-b border-dark-700 pb-6">
    <h1 class="text-4xl font-extrabold text-white tracking-tight text-red-500">Panel de Alertas Críticas</h1>
    <p class="text-dark-muted mt-1">Productos detectados sin precio asignado que requieren atención inmediata.</p>
</header>

<div class="bg-dark-800 rounded-xl border border-dark-700 overflow-hidden">
    <div class="p-6 border-b border-dark-700 flex justify-between items-center">
        <h3 class="text-lg font-bold text-white">Anomalías Detectadas ({{ $productosCriticos->count() }})</h3>
        
        @if($productosCriticos->count() > 0)
        <button onclick="ejecutarEmergencia()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition">
            Ocultar Todos (Pasar a Borrador)
        </button>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-dark-900 border-b border-dark-700">
                    <th class="px-6 py-4 text-xs font-bold text-dark-muted uppercase">SKU</th>
                    <th class="px-6 py-4 text-xs font-bold text-dark-muted uppercase">Producto</th>
                    <th class="px-6 py-4 text-xs font-bold text-dark-muted uppercase">Estado Actual</th>
                </tr>
            </thead>
            <tbody>
                @forelse($productosCriticos as $prod)
                <tr class="border-b border-dark-700 hover:bg-dark-700/50">
                    <td class="px-6 py-4 text-red-400 font-mono">{{ $prod->sku }}</td>
                    <td class="px-6 py-4 text-white">{{ $prod->nombre }}</td>
                    <td class="px-6 py-4 text-red-500 font-bold">Precio Inválido / Nulo</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="px-6 py-8 text-center text-dark-muted">Todo el inventario cuenta con precios válidos.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function ejecutarEmergencia() {
    if(!confirm('¿Estás seguro? Esto ocultará los productos de la tienda pública de Bellaroma.')) return;

    // Recopilar IDs
    const ids = @json($productosCriticos->pluck('id'));

    fetch('{{ route("woocommerce.emergencia") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ productos_ids: ids })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        window.location.reload();
    });
}
</script>
@endsection