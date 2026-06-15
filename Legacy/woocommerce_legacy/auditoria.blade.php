@extends('layouts.app')

@section('title', 'Auditoría de Precios | Gelia Hub')

@section('content')
<header class="flex items-center justify-between mb-8 border-b border-dark-700 pb-6">
    <div>
        <a href="{{ route('woocommerce.index') }}" class="text-sm text-purple-400 hover:text-purple-300 flex items-center gap-1 mb-2 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Volver a WooCommerce
        </a>
        <h1 class="text-3xl font-extrabold text-white tracking-tight">
            Centro de <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-indigo-500">Auditoría</span>
        </h1>
        <p class="text-dark-muted mt-1 text-sm font-medium">Historial detallado y descarga de reportes de sincronización.</p>
    </div>
</header>
@if($errors->any())
    <div class="mb-6 p-4 bg-red-500/10 border border-red-500/50 rounded-xl text-red-400 font-bold flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        {{ $errors->first() }}
    </div>
@endif

<div class="bg-dark-800 border border-dark-700 rounded-2xl p-6 shadow-xl mb-8">
    <form action="{{ route('woocommerce.auditoria') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Buscar ID / Estado</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Ej. 104 o completado..." class="w-full bg-dark-900 border border-dark-600 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-blue-500 transition-all text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Fecha Inicio</label>
            <input type="date" name="fecha_inicio" value="{{ request('fecha_inicio') }}" class="w-full bg-dark-900 border border-dark-600 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-blue-500 transition-all text-sm" style="color-scheme: dark;">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Fecha Fin (Opcional)</label>
            <input type="date" name="fecha_fin" value="{{ request('fecha_fin') }}" class="w-full bg-dark-900 border border-dark-600 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-blue-500 transition-all text-sm" style="color-scheme: dark;">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="w-full py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-500 transition-all flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                Filtrar
            </button>
            <a href="{{ route('woocommerce.auditoria') }}" class="py-2.5 px-4 bg-dark-700 text-gray-300 font-bold rounded-xl border border-dark-600 hover:bg-dark-600 transition-all">
                Limpiar
            </a>
        </div>
    </form>
</div>

<div class="bg-dark-800 border border-dark-700 rounded-2xl shadow-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="text-xs text-gray-400 bg-dark-900/50 uppercase font-bold">
                <tr>
                    <th class="px-6 py-4">ID de Lote</th>
                    <th class="px-6 py-4">Fecha y Hora</th>
                    <th class="px-6 py-4">Productos Procesados</th>
                    <th class="px-6 py-4">Estado</th>
                    <th class="px-6 py-4 text-center">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-700 bg-dark-800">
                @forelse($logs as $log)
                <tr class="hover:bg-dark-700/30 transition-colors">
                    <td class="px-6 py-4 font-bold text-gray-300">#{{ $log->id }}</td>
                    <td class="px-6 py-4 text-white font-medium">
                        {{ $log->created_at->format('d/m/Y') }} <span class="text-dark-muted ml-2">{{ $log->created_at->format('H:i A') }}</span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 bg-dark-900 border border-dark-600 rounded-lg text-xs font-mono text-purple-400">
                            {{ $log->procesados }} / {{ $log->total_productos }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        @if($log->estado === 'completado')
                            <span class="px-2 py-1 text-xs font-bold bg-green-500/10 text-green-400 border border-green-500/20 rounded-md">COMPLETADO</span>
                        @elseif($log->estado === 'error')
                            <span class="px-2 py-1 text-xs font-bold bg-red-500/10 text-red-400 border border-red-500/20 rounded-md">ERROR</span>
                        @else
                            <span class="px-2 py-1 text-xs font-bold bg-yellow-500/10 text-yellow-400 border border-yellow-500/20 rounded-md">PROCESANDO</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 flex justify-center">
                        <a href="{{ route('woocommerce.auditoria.descargar', $log->id) }}" class="flex items-center gap-2 px-4 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white font-bold hover:bg-blue-600 hover:border-blue-500 transition-all text-xs">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            Descargar Detalles
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="p-10 text-center text-dark-muted font-medium italic">No se encontraron registros de auditoría.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($logs->hasPages())
    <div class="p-4 bg-dark-900/30 border-t border-dark-700">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection