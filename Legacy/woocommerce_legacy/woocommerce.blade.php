@extends('layouts.app')

@section('title', 'WooCommerce Sync | Gelia Hub')

@section('content')
<header class="flex items-center justify-between mb-8 border-b border-dark-700 pb-6">
    <div>
        <h1 class="text-4xl font-extrabold text-white tracking-tight">
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-500 to-indigo-500">WooCommerce</span>
            <span class="text-lg text-dark-muted font-normal ml-2">Sync Hub</span>
        </h1>
        <p class="text-dark-muted mt-1 text-sm font-medium">Actualización masiva de precios e inventario para la tienda en línea.</p>
    </div>
    <div class="flex items-center gap-4">
        <a href="{{ route('woocommerce.auditoria') }}" class="flex items-center gap-2 px-5 py-2.5 bg-dark-800 border border-dark-600 rounded-xl text-white font-bold hover:border-blue-500 hover:text-blue-400 transition-all shadow-sm">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Auditoría
        </a>
        <a href="{{ route('woocommerce.alertas') }}" class="flex items-center gap-2 px-5 py-2.5 bg-red-900/20 border border-red-600 rounded-xl text-red-500 font-bold hover:bg-red-600 hover:text-white transition-all shadow-sm">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            Alertas
        </a>

        <button onclick="abrirModalPin()" class="flex items-center gap-2 px-5 py-2.5 bg-dark-800 border border-dark-600 rounded-xl text-white font-bold hover:border-purple-500 transition-all shadow-sm hover:shadow-purple-500/20">
            <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Ajustes
        </button>
    </div>
</header>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
    <div class="lg:col-span-5 space-y-6">
        <div class="flex gap-2 bg-dark-800 p-1.5 rounded-xl border border-dark-700 shadow-sm">
            <button onclick="cambiarPestanaWoo('diario')" id="tab-diario" class="flex-1 py-2 text-sm font-bold rounded-lg bg-purple-600 text-white transition-all">Sincronización Precios</button>
            <button onclick="cambiarPestanaWoo('sync')" id="tab-sync" class="flex-1 py-2 text-sm font-bold rounded-lg text-gray-400 hover:text-white transition-all">Configurar Catálogo</button>
        </div>

        <div id="form-diario" class="bg-dark-800 border border-dark-700 rounded-2xl p-6 shadow-xl">
            <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                <span class="w-2 h-5 bg-purple-500 rounded"></span> Procesar Inventario
            </h2>
            <div class="mb-8">
                <x-upload-area id="listado-aromas" name="listado_aromas" title="Listado Aromas (Excel) *" colorTheme="purple" accept=".xlsx" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <button onclick="ejecutarProceso('local')" class="flex items-center justify-center gap-2 py-3 bg-dark-700 text-white font-bold rounded-xl border border-dark-600 hover:bg-dark-600 transition-all">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Generar CSV
                </button>
                <button onclick="ejecutarProceso('previsualizar')" class="flex items-center justify-center gap-2 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl shadow-lg hover:scale-[1.02] transition-all">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    Analizar Cambios
                </button>
            </div>
        </div>

        <div id="form-sync" class="hidden bg-dark-800 border border-dark-700 rounded-2xl p-6 shadow-xl">
            <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                <span class="w-2 h-5 bg-blue-500 rounded"></span> Sincronizar Base de Datos
            </h2>

            <!-- INSTRUCCIONES DE EXPORTACIÓN WOOCOMMERCE -->
            <div class="mb-6 p-4 bg-blue-900/20 border border-blue-500/30 rounded-xl">
                <h3 class="text-sm font-bold text-blue-400 mb-2">Instrucciones de Exportación</h3>
                <ul class="text-xs text-gray-300 list-disc list-inside space-y-1">
                    <li>Para sincronizar el catálogo local, exporta desde WooCommerce los siguientes campos: <strong>ID, SKU, Nombre, Tipo, Superior</strong>.</li>
                    <li>Para actualizar precios masivamente sin usar la API, usa el archivo generado por Gelia que contiene: <strong>SKU, Precio normal, Precio rebajado</strong>.</li>
                </ul>
            </div>

            <!-- SECCIÓN 1: ACTUALIZACIÓN LOCAL DE PRECIOS -->
            <div class="mt-8 pt-6 border-t border-dark-700 mb-8">
                <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                    <span class="w-2 h-5 bg-green-500 rounded"></span> Actualizar Precios en Local (Gelia Export)
                </h3>
                <form id="form-precios-locales" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <x-upload-area id="archivo-precios-locales" name="archivo_precios_locales" title="CSV Export de Precios (Gelia)" colorTheme="green" accept=".csv" />
                    </div>
                    <button type="button" onclick="sincronizarPreciosLocales()" class="w-full py-3 bg-dark-700 text-green-400 font-bold rounded-xl border border-green-500/30 hover:bg-green-600 hover:text-white transition-all shadow-sm">
                        Actualizar Precios Internos
                    </button>
                </form>
            </div>

            <!-- SECCIÓN 2: ACTUALIZAR ESTRUCTURA DESDE WOOCOMMERCE -->
            <div class="mt-8 pt-6 border-t border-dark-700 mb-8">
                <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                    <span class="w-2 h-5 bg-blue-400 rounded"></span> Sincronizar Estructura (WooCommerce Export)
                </h3>
                <div class="mb-4">
                    <x-upload-area id="woocommerce-csv" name="woocommerce_csv" title="CSV Export de Woo" colorTheme="blue" accept=".csv" />
                </div>
                <button type="button" onclick="sincronizarCatalogo()" class="w-full py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-500 transition-all shadow-sm">
                    Actualizar Productos Locales
                </button>
            </div>

            <!-- SECCIÓN 3: EXTRACCIÓN API EN VIVO -->
            <div class="mt-8 pt-6 border-t border-dark-700">
                <h3 class="text-sm font-bold text-white mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Sincronización Secundaria (API)
                </h3>
                <p class="text-xs text-dark-muted mb-4">Descarga los precios en vivo desde WooCommerce para mostrarlos en la tabla local.</p>
                <button type="button" onclick="descargarPreciosWoo()" class="w-full py-3 bg-dark-700 text-purple-400 font-bold rounded-xl border border-purple-500/30 hover:bg-purple-600 hover:text-white transition-all flex justify-center items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    Extraer Precios Actuales
                </button>
            </div>
        </div>
    </div>

    <div class="lg:col-span-7 space-y-6">
        @php
        // Consultamos rápidamente si hay un proceso zombie activo
        $procesoAtascado = \App\Models\WoocommerceSyncLog::where('estado', 'en_proceso')->first();
        @endphp

        <div id="status-api-container" class="{{ $procesoAtascado ? '' : 'hidden' }} bg-dark-800 border border-green-500/30 rounded-2xl p-6 shadow-2xl relative overflow-hidden mb-6">
            <div class="relative z-10">

                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-bold text-green-400 uppercase tracking-widest">Sincronización en curso</h3>

                    @if($procesoAtascado)
                    <div class="flex gap-2">
                        <form action="{{ route('woocommerce.sync.reanudar', $procesoAtascado->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="bg-blue-900/40 text-blue-400 border border-blue-500 hover:bg-blue-600 hover:text-white text-xs font-bold py-1.5 px-3 rounded-lg transition shadow-sm">
                                Reanudar Proceso
                            </button>
                        </form>

                        <form action="{{ route('woocommerce.sync.cancelar', $procesoAtascado->id) }}" method="POST" onsubmit="return confirm('¿Estás seguro de cancelar definitivamente este proceso?');">
                            @csrf
                            <button type="submit" class="bg-red-900/40 text-red-500 border border-red-500 hover:bg-red-600 hover:text-white text-xs font-bold py-1.5 px-3 rounded-lg transition shadow-sm">
                                Detener Proceso
                            </button>
                        </form>
                    </div>
                    @endif
                </div>

                <div class="w-full bg-dark-900 rounded-full h-3 mb-4 border border-dark-700">
                    <div id="barra-progreso" class="bg-gradient-to-r from-green-600 to-emerald-500 h-full transition-all duration-700 rounded-full"
                        style="width: {{ $procesoAtascado ? ($procesoAtascado->procesados / max($procesoAtascado->total_productos, 1)) * 100 : 0 }}%">
                    </div>
                </div>
                <div class="flex justify-between text-xs font-mono text-gray-400">
                    <span id="conteo-productos">
                        {{ $procesoAtascado ? $procesoAtascado->procesados . ' / ' . $procesoAtascado->total_productos : '0 / 0' }}
                    </span>
                    <span id="porcentaje-texto">
                        {{ $procesoAtascado ? round(($procesoAtascado->procesados / max($procesoAtascado->total_productos, 1)) * 100) : 0 }}%
                    </span>
                </div>
            </div>
        </div>

        <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <svg class="w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Historial de Archivos
        </h2>
        <div class="bg-dark-800 border border-dark-700 rounded-2xl overflow-hidden shadow-xl divide-y divide-dark-700">
            @forelse($templatesHoy as $template)
            <div class="p-4 flex items-center justify-between hover:bg-dark-700/50 transition-colors">
                <div class="text-sm">
                    <p class="font-bold text-white">{{ $template->nombre_archivo }}</p>
                    <p class="text-xs text-dark-muted">{{ $template->created_at->format('h:i A') }} • {{ $template->tamano_kb }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('woocommerce.descargar', $template->id) }}" class="p-2 bg-dark-600 rounded-lg text-white hover:bg-purple-600 transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </a>
                    <button onclick="eliminarTemplateWoo({{ $template->id }})" class="p-2 bg-dark-600 rounded-lg text-white hover:bg-red-600 transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
            @empty
            <div class="p-10 text-center text-dark-muted font-medium italic">No se han generado reportes el día de hoy.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="bg-dark-800 border border-dark-700 rounded-2xl shadow-xl overflow-hidden mt-8">
    <div class="p-6 border-b border-dark-700 flex flex-col md:flex-row justify-between items-center gap-4">
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <svg class="w-6 h-6 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 4-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
            </svg>
            Catálogo Sincronizado ({{ $productos->total() }})
        </h2>

        <form action="{{ route('woocommerce.index') }}" method="GET" class="relative w-full md:w-96">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por SKU o Nombre..." class="w-full bg-dark-900 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-purple-500 transition-all text-sm">
            <button type="submit" class="absolute right-3 top-2.5 text-gray-500 hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            @if($procesoActivo)
            <input type="hidden" id="active-log-id" value="{{ $procesoActivo->id }}">
            @endif

            <thead class="text-xs text-gray-400 bg-dark-900/50 uppercase font-bold">
                <tr>
                    <th class="px-6 py-4">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'sku', 'order' => $order === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 hover:text-white transition">
                            SKU / ID
                            @if($sort === 'sku')
                            <svg class="w-4 h-4 {{ $order === 'desc' ? 'rotate-180' : '' }} transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                            </svg>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-4">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'nombre', 'order' => $order === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center gap-1 hover:text-white transition">
                            Nombre del Producto
                            @if($sort === 'nombre')
                            <svg class="w-4 h-4 {{ $order === 'desc' ? 'rotate-180' : '' }} transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                            </svg>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-4 text-center">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'precio_normal', 'order' => $order === 'asc' ? 'desc' : 'asc']) }}" class="flex items-center justify-center gap-1 hover:text-white transition">
                            Referencia de Precios
                            @if($sort === 'precio_normal')
                            <svg class="w-4 h-4 {{ $order === 'desc' ? 'rotate-180' : '' }} transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                            </svg>
                            @endif
                        </a>
                    </th>
                    <th class="px-6 py-4 text-center text-xs text-gray-400 uppercase font-bold">
                        Acciones
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-700 bg-dark-800">
                @foreach($productos as $producto)
                <tr class="hover:bg-dark-700/30 transition-colors">
                    <td class="px-6 py-4">
                        <p class="font-bold text-gray-300">{{ $producto->sku }}</p>
                        <p class="text-[10px] text-purple-400">#{{ $producto->id }}</p>
                    </td>
                    <td class="px-6 py-4 text-white font-medium">{{ $producto->nombre }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-center gap-4">
                            <div class="text-center">
                                <p class="text-[10px] text-dark-muted uppercase">Normal</p>
                                <p class="font-bold text-gray-400">
                                    {{ $producto->precio_normal ? '$'.number_format($producto->precio_normal, 2) : '---' }}
                                </p>
                            </div>
                            <div class="text-center">
                                <p class="text-[10px] text-green-500 uppercase">Oferta</p>
                                <p class="font-bold text-green-400">
                                    {{ $producto->precio_rebajado ? '$'.number_format($producto->precio_rebajado, 2) : '---' }}
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <!-- Botón: Consultar API -->
                            <button type="button" onclick="consultarPrecioIndividualWoo({{ $producto->id }})" class="p-1.5 bg-blue-900/30 text-blue-400 hover:bg-blue-600 hover:text-white rounded-lg border border-blue-500/30 transition-all" title="Consultar precio en WooCommerce">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </button>

                            <!-- Botón: Editar y Subir -->
                            <button type="button" onclick="abrirModalEdicion({{ $producto->id }}, '{{ $producto->sku }}', {{ $producto->precio_normal ?? 0 }}, {{ $producto->precio_rebajado ?? 0 }})" class="p-1.5 bg-purple-900/30 text-purple-400 hover:bg-purple-600 hover:text-white rounded-lg border border-purple-500/30 transition-all" title="Editar y subir precio">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="p-4 bg-dark-900/30 border-t border-dark-700">
        {{ $productos->links() }}
    </div>
</div>

<div id="modal-previsualizacion" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center backdrop-blur-sm">
    <div class="bg-dark-800 border border-dark-700 rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col m-4">

        <div class="p-6 border-b border-dark-700 flex justify-between items-center bg-dark-900/50 rounded-t-2xl">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <svg class="w-6 h-6 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Confirmación de Carga
                </h2>
                <p class="text-sm text-dark-muted mt-1" id="texto-resumen-cambios">Se detectaron N productos con cambios en su precio.</p>
            </div>
            <button onclick="cerrarModalPrevisualizacion()" class="text-gray-500 hover:text-white transition-colors">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="p-0 overflow-y-auto flex-1">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-400 bg-dark-900 sticky top-0 uppercase font-bold shadow-md">
                    <tr>
                        <th class="px-6 py-4">SKU</th>
                        <th class="px-6 py-4">Producto</th>
                        <th class="px-6 py-4 text-center">Precio Normal</th>
                        <th class="px-6 py-4 text-center">Precio Oferta</th>
                    </tr>
                </thead>
                <tbody id="tabla-previsualizacion" class="divide-y divide-dark-700 bg-dark-800">
                </tbody>
            </table>
        </div>

        <div class="p-6 border-t border-dark-700 bg-dark-900/50 rounded-b-2xl flex justify-end gap-3">
            <button onclick="cerrarModalPrevisualizacion()" class="px-6 py-2.5 bg-dark-700 text-gray-300 font-bold rounded-xl hover:bg-dark-600 transition-all">
                Cancelar
            </button>
            <button onclick="ejecutarProceso('nube')" class="px-6 py-2.5 bg-purple-600 text-white font-bold rounded-xl hover:bg-purple-500 flex items-center gap-2 transition-all shadow-lg shadow-purple-500/20">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                Confirmar y Sincronizar API
            </button>
        </div>
    </div>
</div>

@include('components.woocommerce-modals')

@push('scripts')
@vite(['resources/js/woocommerce.js'])
@endpush
@endsection