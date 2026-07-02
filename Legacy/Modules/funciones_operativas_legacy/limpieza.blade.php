@extends('layouts.aromas')

@section('title', 'Limpieza de Facturados | Gelia Hub')

@section('aromas-content')
<header class="flex items-center justify-between mb-8 pb-6 border-b border-dark-700">
    <div>
        <h1 class="text-3xl font-extrabold text-white tracking-tight">
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-400">Limpieza de Archivos</span>
        </h1>
        <p class="text-dark-muted mt-1 text-sm font-medium">Herramienta para remover apóstrofes y formatear tipos de datos exportados del ERP.</p>
    </div>
</header>

<div id="alertas"></div>

<form id="form-limpieza" enctype="multipart/form-data">
    @csrf
    
    <div class="bg-dark-900 border border-dark-700 rounded-xl p-6 mb-8">
        <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <span class="bg-blue-500 w-2 h-6 rounded"></span> Sube el archivo a limpiar
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-upload-area
                id="archivo_sucio"
                name="archivo_sucio"
                title="Archivo Facturado CSV/Excel *"
                colorTheme="blue"
                instructions="<span class='text-blue-400 font-bold'>Formato:</span> CSV o Excel.<br><span class='text-gray-400'>Se removerán todos los apóstrofes y se ajustará el formato numérico automáticamente.</span>" />
        </div>
    </div>

    <div class="pt-4 flex justify-end">
        <button type="submit" id="btn-procesar-limpieza" class="px-8 py-3 bg-gradient-to-r from-blue-700 to-blue-600 hover:from-blue-600 hover:to-blue-500 text-white font-bold rounded-xl border border-blue-500 shadow-lg shadow-blue-500/20 transition flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
            Procesar y Descargar Archivo Limpio
        </button>
    </div>
</form>
@endsection

@push('scripts')
@vite(['resources/js/app.js', 'resources/js/aromas/limpieza.js'])
<script>
    window.GeliaLimpieza = {
        routes: {
            procesar: "{{ route('aromas.limpieza.procesar') }}"
        }
    };
</script>
@endpush