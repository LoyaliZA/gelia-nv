@extends('layouts.app')

@section('title', 'Bellaroma Drive | Gelia Hub')

@section('content')

<div id="modal-config" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-dark-800 border border-dark-700 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden transition-all">
        <div class="p-6 border-b border-dark-700 flex justify-between items-center bg-dark-900/50">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-bella-main" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Ajustes de Automatización
            </h2>
            <button onclick="cerrarModalConfig()" class="text-gray-400 hover:text-white transition">&times;</button>
        </div>

        <div id="step-auth" class="p-8 text-center">
            <svg class="w-12 h-12 text-dark-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <h3 class="text-lg font-bold text-white mb-2">Autenticación Requerida</h3>
            <p class="text-sm text-dark-muted mb-6">Ingresa tu PIN de administrador para continuar. <br>(Por defecto: <strong class="text-gray-300">1234</strong>)</p>
            <input type="password" id="input-pin-auth" class="w-full bg-dark-900 border border-dark-700 rounded-xl px-4 py-3 text-center text-2xl tracking-widest text-white focus:border-bella-main outline-none mb-4" placeholder="••••" maxlength="4">
            <button onclick="verificarPinConfig()" class="w-full py-3 bg-bella-main hover:bg-pink-600 text-white font-bold rounded-xl shadow-lg transition">Verificar Identidad</button>
        </div>

        <form id="step-config" class="hidden p-6 space-y-5" onsubmit="guardarConfiguracion(event)">
            <input type="hidden" id="config-pin-actual" name="pin_actual">

            <div>
                <label class="block text-sm font-bold text-gray-300 mb-1">Hora de Notificación (Push)</label>
                <input type="time" name="hora_notificacion" id="config-hora" class="w-full bg-dark-900 border border-dark-700 rounded-lg px-4 py-2 text-white focus:border-bella-main outline-none [color-scheme:dark]">
                <p class="text-[10px] text-dark-muted mt-1">Ej: 08:00 AM. El sistema lanzará una alerta local en tu navegador a esta hora.</p>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-300 mb-1">Correo de Destino</label>
                <input type="email" name="correo_destino" id="config-correo" placeholder="ejemplo@gelia.com" class="w-full bg-dark-900 border border-dark-700 rounded-lg px-4 py-2 text-white focus:border-bella-main outline-none">
                <p class="text-[10px] text-dark-muted mt-1">A esta dirección se enviará automáticamente la plantilla al generar.</p>
            </div>

            <div class="pt-4 border-t border-dark-700">
                <label class="block text-sm font-bold text-orange-400 mb-1">Cambiar PIN de Acceso</label>
                <input type="password" name="nuevo_pin" placeholder="Nuevo PIN (Opcional)" class="w-full bg-dark-900 border border-dark-700 rounded-lg px-4 py-2 text-white focus:border-orange-500 outline-none tracking-widest" maxlength="4">
            </div>

            <div class="pt-4 flex gap-3">
                <button type="button" onclick="cerrarModalConfig()" class="flex-1 py-3 bg-dark-700 hover:bg-dark-600 text-white font-bold rounded-xl transition">Cancelar</button>
                <button type="submit" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-lg transition">Guardar</button>
            </div>
        </form>
    </div>
</div>

<header class="flex items-center justify-between mb-8 border-b border-dark-700 pb-6">
    <div>
        <h1 class="text-4xl font-extrabold text-white tracking-tight">
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-bella-main to-pink-500">Bellaroma</span>
            <span class="text-lg text-dark-muted font-normal ml-2">Smart Drive</span>
        </h1>
        <p class="text-dark-muted mt-1 text-sm font-medium">Generador, almacenamiento en la nube y distribución de plantillas.</p>
    </div>

    <button onclick="abrirModalConfig()" class="px-4 py-2.5 bg-dark-800 hover:bg-dark-700 border border-dark-700 text-gray-300 hover:text-white rounded-xl transition shadow-lg flex items-center gap-2" title="Configuración de Automatización">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <span class="hidden sm:inline font-bold text-sm">Ajustes</span>
    </button>
</header>

<div id="modal-descarga" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <div class="bg-dark-800 border border-dark-700 w-full max-w-md rounded-2xl shadow-2xl p-8 text-center transform scale-95 transition-transform duration-300">
        <div class="w-16 h-16 bg-emerald-500/20 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-white mb-2">¡Plantilla Lista!</h2>
        <p class="text-dark-muted text-sm mb-6">El archivo ha sido procesado y guardado en tu Drive de forma segura.</p>

        <div class="flex flex-col gap-3">
            <a id="btn-descargar-modal" href="#" class="w-full py-3 bg-bella-main hover:bg-pink-600 text-white font-bold rounded-xl shadow-lg transition flex justify-center items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Descargar Ahora
            </a>
            <button onclick="cerrarModalDescarga()" class="w-full py-3 bg-dark-700 hover:bg-dark-600 text-white font-bold rounded-xl transition">
                Cerrar
            </button>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

    <div class="lg:col-span-5">
        <form id="form-bellaroma" enctype="multipart/form-data" class="bg-dark-800 border border-dark-700 rounded-2xl p-6 shadow-xl sticky top-24">
            @csrf
            <h2 class="text-xl font-bold text-white mb-6 flex items-center">
                <span class="bg-bella-main w-2 h-6 rounded mr-2"></span> Nueva Plantilla
            </h2>
            <div class="flex flex-col gap-6 mb-6">
                <x-upload-area
                    id="existencias"
                    name="existencias"
                    title="1. Existencias *"
                    colorTheme="bella"
                    accept=".xlsx,.csv" />
                <x-upload-area
                    id="precios"
                    name="precios"
                    title="2. Precios *"
                    colorTheme="green"
                    accept=".xlsx,.csv" />
            </div>
            <div class="col-span-1 md:col-span-2 bg-dark-900 border border-dark-700 rounded-xl p-4 mb-6 flex items-center justify-between shadow-inner">
                <div>
                    <h3 class="text-white font-bold text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 text-bella-main" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Preparar para mañana
                    </h3>
                    <p class="text-xs text-dark-muted mt-1">El archivo se generará con fecha del: <strong class="text-gray-300">{{ date('d/m/Y', strtotime('+1 day')) }}</strong></p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="para_manana" class="sr-only peer">
                    <div class="w-11 h-6 bg-dark-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-bella-main"></div>
                </label>
            </div>
            <button type="submit" class="w-full py-4 bg-gradient-to-r from-bella-main to-rose-700 hover:from-rose-600 hover:to-rose-800 text-white font-bold rounded-xl shadow-lg transition-all transform hover:scale-[1.02] flex items-center justify-center gap-2">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
                Procesar y Guardar
            </button>
        </form>
    </div>

    <div class="lg:col-span-7">
        <h2 class="text-xl font-bold text-white mb-6 flex items-center">
            <span class="bg-blue-500 w-2 h-6 rounded mr-2"></span> Almacenamiento
        </h2>

        <div class="bg-dark-800 border border-dark-700 rounded-2xl shadow-xl overflow-hidden">
            <div class="p-4 border-b border-dark-700 bg-dark-900/50 flex flex-col xl:flex-row gap-3">

                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-4">
                    <div class="relative w-full sm:w-1/2">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-dark-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" id="buscador-drive" class="bg-dark-900 text-white text-sm rounded-lg focus:ring-bella-main focus:border-bella-main block w-full pl-10 p-2.5 border border-dark-600 shadow-inner placeholder-dark-muted" placeholder="Buscar plantilla...">
                    </div>

                    @if($driveFolderId)
                    <a href="https://drive.google.com/drive/folders/{{ $driveFolderId }}" target="_blank" class="flex items-center gap-2 px-4 py-2.5 bg-[#1FA463]/10 hover:bg-[#1FA463]/20 border border-[#1FA463]/30 text-[#1FA463] text-sm font-bold rounded-lg transition-colors w-full sm:w-auto justify-center">
                        <span class="material-symbols-outlined text-[20px]">drive_export</span>
                        Abrir Drive General
                    </a>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <div class="relative">
                        <span class="absolute -top-2.5 left-2 bg-dark-900 px-1 text-[10px] font-bold text-dark-muted uppercase tracking-wider">Desde</span>
                        <input type="date" id="filtro-fecha-inicio" class="bg-dark-900 border border-dark-700 rounded-lg px-3 py-2 text-sm text-gray-300 focus:text-white focus:border-bella-main outline-none cursor-pointer [color-scheme:dark]">
                    </div>
                    <span class="text-dark-700 font-bold">-</span>
                    <div class="relative">
                        <span class="absolute -top-2.5 left-2 bg-dark-900 px-1 text-[10px] font-bold text-dark-muted uppercase tracking-wider">Hasta</span>
                        <input type="date" id="filtro-fecha-fin" class="bg-dark-900 border border-dark-700 rounded-lg px-3 py-2 text-sm text-gray-300 focus:text-white focus:border-bella-main outline-none cursor-pointer [color-scheme:dark]">
                    </div>
                </div>

            </div>

            <div class="divide-y divide-dark-700 max-h-[600px] overflow-y-auto custom-scroll relative" id="lista-drive">

                @if($templatesHoy->isNotEmpty())
                <div class="sticky top-0 bg-dark-800/95 backdrop-blur-md px-4 py-3 border-b border-dark-700 z-10 flex items-center gap-3 shadow-sm">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    <span class="text-sm font-bold text-white tracking-wide">Actividad Reciente (Hoy)</span>
                </div>

                @foreach($templatesHoy as $template)
                <div class="p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 hover:bg-dark-700/60 transition-colors fila-drive" data-date="{{ $template->created_at->format('Y-m-d') }}">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-dark-900 rounded-lg text-emerald-400 border border-dark-600 shadow-sm">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">{{ $template->nombre_archivo }}</h3>
                            <div class="flex items-center gap-3 mt-1 text-xs text-dark-muted">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ $template->created_at->format('h:i A') }}
                                </span>
                                <span>•</span>
                                <span>{{ $template->tamano_kb }}</span>
                            </div>
                            <div class="flex gap-2 mt-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $template->enviado_correo ? 'bg-blue-500/20 text-blue-400' : 'bg-dark-600 text-gray-400' }}">Mail</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $template->subido_drive ? 'bg-yellow-500/20 text-yellow-400' : 'bg-dark-600 text-gray-400' }}">G-Drive</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <a href="{{ route('bellaroma.descargar', $template->id) }}" class="flex-1 sm:flex-none px-4 py-2 bg-dark-600 hover:bg-emerald-600 text-white text-sm font-bold rounded-lg transition flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </a>
                        @if($template->drive_id)
                        <a href="https://drive.google.com/file/d/{{ $template->drive_id }}/view" target="_blank" title="Ver en Drive" class="flex-1 sm:flex-none px-4 py-2 bg-dark-600 hover:bg-[#eac326] text-white text-sm font-bold rounded-lg transition flex items-center justify-center">
                            <span class="material-symbols-outlined !text-[16px] leading-none block">drive_export</span>
                        </a>
                        @endif
                        <button onclick="eliminarPlantilla({{ $template->id }})" class="flex-1 sm:flex-none px-4 py-2 bg-dark-600 hover:bg-red-600 text-white text-sm font-bold rounded-lg transition flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
                @endforeach
                @endif

                @if($templatesHistorial->isNotEmpty())
                <div class="sticky top-0 bg-dark-800/95 backdrop-blur-md px-4 py-3 border-b border-t border-dark-700 z-10 flex items-center gap-3 shadow-sm mt-2">
                    <svg class="w-4 h-4 text-dark-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm font-bold text-gray-300 tracking-wide">Historial Guardado</span>
                </div>

                @foreach($templatesHistorial as $template)
                <div class="p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 hover:bg-dark-700/50 transition-colors fila-drive" data-date="{{ $template->created_at->format('Y-m-d') }}">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-dark-900 rounded-lg text-gray-500 border border-dark-600 shadow-sm">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">{{ $template->nombre_archivo }}</h3>
                            <div class="flex items-center gap-3 mt-1 text-xs text-dark-muted">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {{ $template->created_at->format('d/m/Y - h:i A') }}
                                </span>
                                <span>•</span>
                                <span>{{ $template->tamano_kb }}</span>
                            </div>
                            <div class="flex gap-2 mt-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $template->enviado_correo ? 'bg-blue-500/20 text-blue-400' : 'bg-dark-600 text-gray-400' }}">Mail</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $template->subido_drive ? 'bg-yellow-500/20 text-yellow-400' : 'bg-dark-600 text-gray-400' }}">G-Drive</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <a href="{{ route('bellaroma.descargar', $template->id) }}" class="flex-1 sm:flex-none px-4 py-2 bg-dark-600 hover:bg-emerald-600 text-white text-sm font-bold rounded-lg transition flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </a>
                        @if($template->drive_id)
                        <a href="https://drive.google.com/file/d/{{ $template->drive_id }}/view" target="_blank" title="Ver en Drive" class="flex-1 sm:flex-none px-4 py-2 bg-dark-600 hover:bg-[#1FA463] text-white text-sm font-bold rounded-lg transition flex items-center justify-center">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M7.71 3.5L1.15 15l3.43 6l6.55-11.32L7.71 3.5zM9.73 3.5l-3.43 6h13.11l3.43-6H9.73zM13.44 10.18L6.89 21.5h6.86l6.55-11.32h-6.86z"/>
                            </svg>
                        </a>
                        @endif
                        <button onclick="eliminarPlantilla({{ $template->id }})" class="flex-1 sm:flex-none px-4 py-2 bg-dark-600 hover:bg-red-600 text-white text-sm font-bold rounded-lg transition flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
                @endforeach
                @endif

                @if($templatesHoy->isEmpty() && $templatesHistorial->isEmpty())
                <div class="p-8 text-center" id="empty-drive">
                    <svg class="w-12 h-12 text-dark-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <p class="text-dark-muted font-bold">No hay plantillas en el Drive.</p>
                    <p class="text-xs text-dark-500 mt-1">Sube tus archivos para comenzar a generar.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.BellaromaConfig = {
        horaNotificacion: "{{ $horaNotificacion ?? '' }}",
        generadoHoy: {{ $generadoHoy ? 'true' : 'false' }}
    };
</script>
@vite(['resources/js/app.js', 'resources/js/bellaroma.js'])
@endpush