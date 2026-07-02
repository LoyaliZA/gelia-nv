@extends('layouts.aromas') @section('aromas-content')

@section('title', 'Gastos Comprobables | Gelia Hub')

@section('aromas-content')
<header class="flex items-center justify-between mb-6 border-b border-dark-700 pb-6">
    <div>
        <h1 class="text-4xl font-extrabold text-white tracking-tight">
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-emerald-600">Gastos Comprobables</span>
        </h1>
        <p class="text-dark-muted mt-1 text-sm font-medium">Filtrado y extracción de reportes de remisiones y pedidos.</p>
    </div>
    <a href="{{ route('gelia.index') }}" class="bg-dark-800 hover:bg-dark-700 border border-dark-700 text-dark-muted hover:text-white px-4 py-2 rounded-lg text-sm font-bold transition shadow-lg flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Volver a Aromas
    </a>
</header>

<div class="mb-6 bg-dark-800 border border-dark-700 border-l-4 border-l-emerald-500 rounded-lg p-5 shadow-sm">
    <h3 class="text-sm font-bold text-emerald-500 mb-2 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
        Instrucciones del Módulo
    </h3>
    <ol class="list-decimal list-inside text-xs text-dark-muted space-y-1">
        <li>Sube el archivo Excel de gastos exportado del sistema.</li>
        <li>Selecciona el tipo de documento que deseas filtrar (Remisiones, Pedidos, o ambos).</li>
        <li>Haz clic en <strong class="text-gray-300">Generar Gastos</strong> para descargar el reporte limpio.</li>
    </ol>
</div>

<div id="alertas"></div>

<form id="form-principal" enctype="multipart/form-data">
    @csrf

    <div class="bg-dark-800 border border-dark-700 p-6 rounded-xl max-w-2xl mx-auto shadow-lg">
        <div class="flex flex-col gap-6">
            
            <x-upload-area 
                id="gastos" 
                name="archivo_gastos" 
                title="Subir Excel de Gastos" 
                colorTheme="green" 
                accept=".xlsx,.xls,.csv"
            />
            
            <div class="bg-dark-900 border border-dark-700 p-4 rounded-lg">
                <label class="block text-sm font-bold text-gray-300 mb-2">Filtrar por Tipo de Documento:</label>
                <select name="filtro_tipo" id="filtro-tipo" class="w-full bg-dark-800 border border-dark-600 rounded-lg p-3 text-white text-sm focus:border-emerald-500 outline-none transition cursor-pointer">
                    <option value="TODOS">Ambos (Pedidos y Remisiones)</option>
                    <option value="Remisión">Solo Remisiones</option>
                    <option value="Pedido">Solo Pedidos</option>
                </select>
            </div>

            <button type="button" onclick="procesarSolicitudGastos()" class="w-full py-4 bg-emerald-600/20 border border-emerald-600/50 text-emerald-400 hover:bg-emerald-600 hover:text-white rounded-lg font-bold transition flex justify-center items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V8z" clip-rule="evenodd" />
                </svg>
                Generar Gastos
            </button>
            
        </div>
    </div>
</form>
@endsection

@push('scripts')
@vite(['resources/js/app.js', 'resources/js/aromas/gastos.js'])
<script>
    window.GeliaConfig = {
        routes: {
            generar: "{{ route('aromas.gastos.procesar') }}"
        }
    };
</script>
@endpush