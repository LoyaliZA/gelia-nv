@extends('layouts.aromas') @section('aromas-content')

@section('title', 'Transacciones Bancarias | Gelia Hub')

@section('aromas-content')
<header class="flex items-center justify-between mb-6 border-b border-dark-700 pb-6">
    <div>
        <h1 class="text-4xl font-extrabold text-white tracking-tight">
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-400 to-orange-600">Transacciones Bancarias</span>
        </h1>
        <p class="text-dark-muted mt-1 text-sm font-medium">Limpieza y formateo de estados de cuenta bancarios.</p>
    </div>
    <a href="{{ route('gelia.index') }}" class="bg-dark-800 hover:bg-dark-700 border border-dark-700 text-dark-muted hover:text-white px-4 py-2 rounded-lg text-sm font-bold transition shadow-lg flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Volver a Aromas
    </a>
</header>

<div class="mb-6 bg-dark-800 border border-dark-700 border-l-4 border-l-orange-500 rounded-lg p-5 shadow-sm">
    <h3 class="text-sm font-bold text-orange-500 mb-2 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
        Instrucciones del Módulo
    </h3>
    <ol class="list-decimal list-inside text-xs text-dark-muted space-y-1">
        <li>Sube el archivo de transacciones descargado directamente del portal bancario.</li>
        <li>El sistema eliminará columnas innecesarias y limpiará caracteres especiales (saltos de línea, dobles espacios).</li>
        <li>Haz clic en <strong class="text-gray-300">Limpiar Transacciones</strong> para descargar el archivo procesado y formateado.</li>
    </ol>
</div>

<div id="alertas"></div>

<form id="form-principal" enctype="multipart/form-data">
    @csrf

    <div class="bg-dark-800 border border-dark-700 p-6 rounded-xl max-w-2xl mx-auto shadow-lg">
        <div class="flex flex-col gap-6">
            
            <x-upload-area 
                id="transacciones" 
                name="archivo_transacciones" 
                title="Subir Transacciones" 
                colorTheme="orange"
                accept=".xlsx,.xls,.csv"
            />
            
            <button type="button" onclick="procesarSolicitudTransacciones()" class="w-full py-4 bg-orange-600/20 border border-orange-600/50 text-orange-400 hover:bg-orange-600 hover:text-white rounded-lg font-bold transition flex justify-center items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                </svg>
                Limpiar Transacciones
            </button>
            
        </div>
    </div>
</form>
@endsection

@push('scripts')
@vite(['resources/js/app.js', 'resources/js/aromas/transacciones.js'])
<script>
    window.GeliaConfig = {
        routes: {
            generar: "{{ route('aromas.transacciones.procesar') }}"
        }
    };
</script>
@endpush