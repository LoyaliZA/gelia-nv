@extends('layouts.aromas')

@section('title', 'Filtrado de Asistencia | Gelia Hub')

@section('aromas-content')
<header class="flex items-center justify-between mb-8 pb-6 border-b border-dark-700">
    <div>
        <h1 class="text-3xl font-extrabold text-white tracking-tight">
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-indigo-500">Filtrado de Asistencia</span>
        </h1>
        <p class="text-dark-muted mt-1 text-sm font-medium">Procesamiento y limpieza de reportes de la máquina checadora.</p>
    </div>
</header>

@if($errors->any())
<div class="mb-6 p-4 bg-red-900/50 border border-red-500 rounded-lg text-red-200">
    <ul class="list-disc pl-5">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route('aromas.asistencia.procesar') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <h2 class="text-xl font-bold text-white mb-4 flex items-center">
        <span class="bg-indigo-500 w-2 h-6 rounded mr-2"></span> Carga de Reporte Original
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
        <x-upload-area
            id="archivo_asistencia"
            name="archivo_asistencia"
            title="1. Archivo Excel de Asistencia *"
            colorTheme="blue"
            instructions="<span class='text-blue-400 font-bold'>Formato:</span> .xls o .xlsx<br><span class='text-blue-400 font-bold'>Nota:</span> Asegúrate de descargar el reporte original directamente de la máquina checadora sin modificarlo." />
            
        <div class="bg-dark-800 border border-dark-700 rounded-xl p-6 flex flex-col justify-center">
            <h3 class="text-lg font-bold text-white mb-2">¿Qué hará el sistema?</h3>
            <ul class="text-sm text-gray-300 space-y-3">
                <li class="flex items-start gap-2">
                    <span class="text-indigo-400 font-bold">✓</span>
                    Convertirá la matriz de días en una lista fila por fila.
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-indigo-400 font-bold">✓</span>
                    Separará las horas agrupadas en "Hora de entrada" y "Hora de salida".
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-indigo-400 font-bold">✓</span>
                    Detectará automáticamente el mes y año para nombrar el archivo.
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-orange-400 font-bold">⚠</span>
                    Si olvidaron checar salida, se marcará como "Sin registro".
                </li>
            </ul>
        </div>
    </div>

    <div class="border-t border-dark-700 pt-8 flex justify-end">
        <button type="submit" class="bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-bold py-4 px-10 rounded-xl border border-indigo-500 shadow-lg transition flex items-center gap-2">
            Procesar y Descargar Limpio
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
        </button>
    </div>
</form>
@endsection
@push('scripts')
    @vite(['resources/js/aromas/asistencia.js'])
@endpush