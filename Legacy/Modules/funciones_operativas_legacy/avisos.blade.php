@extends('layouts.aromas')

@section('title', 'Aviso Mercancía | Gelia Hub')

@section('aromas-content')
<header class="flex items-center justify-between mb-8 pb-6 border-b border-dark-700">
    <div>
        <h1 class="text-3xl font-extrabold text-white tracking-tight">
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-blue-500">Cruce de Aviso de Mercancía</span>
        </h1>
        <p class="text-dark-muted mt-1 text-sm font-medium">Validación automática de SKUs entre Orden de Compra y Aviso de Drive.</p>
    </div>
</header>

<div id="alertas"></div>

<form id="form-avisos" enctype="multipart/form-data">
    @csrf
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <x-upload-area 
            id="orden_compra" 
            name="orden_compra" 
            title="1. Orden de Compra (Wizerp) *" 
            colorTheme="blue" 
            instructions="<span class='text-blue-400 font-bold'>Formato:</span> Excel o CSV original exportado desde Wizerp." 
        />

        <x-upload-area 
            id="aviso_mercancia" 
            name="aviso_mercancia" 
            title="2. Aviso de Mercancía (Drive) *" 
            colorTheme="cyan" 
            instructions="<span class='text-cyan-400 font-bold'>Formato:</span> Pestaña 'aviso mercancía' descargada en Excel o CSV." 
        />
    </div>

    <button type="button" onclick="procesarAviso()" class="w-full bg-gradient-to-r from-cyan-700 to-blue-800 hover:from-cyan-600 hover:to-blue-700 text-white font-bold py-4 rounded-xl border border-dark-700 shadow-lg transition">
        Cruzar e Identificar Mercancía
    </button>
</form>

{{-- NUEVO: Contenedor dinámico para la tabla de resultados --}}
<div id="resultados-container" class="mt-12 hidden transition-all duration-500">
    {{-- El contenido se inyecta vía JS --}}
</div>
@endsection

@push('scripts')
<script>
    window.GeliaConfigAvisos = {
        routes: {
            procesar: "{{ route('aromas.avisos.procesar') }}"
        }
    };
</script>
{{-- CORRECCIÓN: Integración de scripts globales para habilitar la UI de los Dropzones --}}
@vite(['resources/js/app.js', 'resources/js/aromas.js', 'resources/js/aromas/avisos.js'])
@endpush