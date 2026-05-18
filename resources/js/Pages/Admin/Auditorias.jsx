import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    History, ChevronDown, Calendar,
    ArrowRight, User, Terminal, Globe, Tag
} from 'lucide-react';

// Se ajusta la ruta relativa basándonos en tu estructura
import AppLayout from '../../Layouts/AppLayout';

const ESTILOS_ADICIONALES = `
    @keyframes slideUpFade {
        0% { opacity: 0; transform: translateY(20px); }
        100% { opacity: 1; transform: translateY(0); }
    }
    .animate-page-reveal {
        opacity: 0;
        animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
`;

export default function Auditorias({ auth, auditorias, listas, filtros }) {
    
    // Estados para mantener los selectores sincronizados con la URL
    const [filtroLista, setFiltroLista] = useState(filtros.lista_id || '');
    const [filtroOrigen, setFiltroOrigen] = useState(filtros.origen || '');

    // Disparador de Inertia para recargar la data según los filtros
    const handleFiltroChange = (campo, valor) => {
        const nuevosFiltros = { ...filtros, [campo]: valor };
        if (!valor) delete nuevosFiltros[campo];
        
        // Se asume que en web.php nombraste la ruta como 'admin.auditorias_sistema.index'
        router.get(route('admin.auditorias_sistema.index'), nuevosFiltros, {
            preserveState: true,
            replace: true
        });
    };

    const formatCurrency = (amount) => {
        if (amount === null || amount === undefined) return '$0.00';
        return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);
    };

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleString('es-MX', {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    };

    // Clase base compartida que extrajimos de Clientes.jsx
    const baseCardClass = "animate-page-reveal theme-surface border theme-border rounded-[2.5rem] shadow-[0_12px_40px_rgba(0,0,0,0.08)] dark:shadow-[0_12px_40px_rgba(0,0,0,0.5)] transition-all duration-300 relative z-10";

    return (
        <AppLayout auth={auth}>
            <Head title="Auditorías del Sistema | GELIANV" />
            <style>{ESTILOS_ADICIONALES}</style>
            
            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">
                
                {/* --- ENCABEZADO --- */}
                <header className={`${baseCardClass} p-8 md:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-6`} style={{ animationDelay: '0ms' }}>
                    <div className="flex flex-col gap-4">
                        <div className="flex items-center justify-start mb-2">
                            <div className="w-8 h-1.5 rounded-full mr-3" style={{ backgroundColor: 'var(--color-primario)' }}></div>
                            <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted drop-shadow-sm">
                                PANEL DE CONTROL WIZERP_
                            </span>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0 p-0">
                            BITÁCORA DE <span style={{ color: 'var(--color-primario)' }}>AUDITORÍA</span>
                        </h1>
                    </div>
                    
                    <div className="flex items-center gap-3 bg-rose-500/10 text-rose-500 px-5 py-3 rounded-2xl border border-rose-500/20 shadow-sm">
                         <History className="w-5 h-5" />
                         <span className="text-[11px] font-black uppercase tracking-widest">Registros Inmutables</span>
                    </div>
                </header>
                
                {/* --- FILTROS Y LISTADO --- */}
                <section className={`${baseCardClass} p-8 space-y-8`} style={{ animationDelay: '100ms' }}>
                    
                    {/* Barra de Búsqueda / Filtros */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="relative">
                            <select
                                value={filtroLista}
                                onChange={(e) => {
                                    setFiltroLista(e.target.value);
                                    handleFiltroChange('lista_id', e.target.value);
                                }}
                                className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                onFocus={e => e.target.style.borderColor = 'var(--color-primario)'}
                                onBlur={e => e.target.style.borderColor = ''}
                            >
                                <option value="">TODOS LOS CATÁLOGOS</option>
                                {listas.map(lista => (
                                    <option key={lista.id} value={lista.id}>{lista.nombre}</option>
                                ))}
                            </select>
                            <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                <ChevronDown className="w-4 h-4 theme-text-muted" />
                            </div>
                        </div>

                        <div className="relative">
                            <select
                                value={filtroOrigen}
                                onChange={(e) => {
                                    setFiltroOrigen(e.target.value);
                                    handleFiltroChange('origen', e.target.value);
                                }}
                                className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                onFocus={e => e.target.style.borderColor = 'var(--color-primario)'}
                                onBlur={e => e.target.style.borderColor = ''}
                            >
                                <option value="">TODOS LOS ORÍGENES</option>
                                <option value="INTERFAZ WEB">INTERFAZ WEB</option>
                                <option value="CONSOLA / SCRIPT BASH">CONSOLA / SCRIPT BASH</option>
                            </select>
                            <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                <ChevronDown className="w-4 h-4 theme-text-muted" />
                            </div>
                        </div>
                    </div>
                    
                    {/* Contenedor de Registros */}
                    <div className="space-y-4">
                        {auditorias.data.length === 0 ? (
                            <div className="text-center py-16 theme-element border-2 border-dashed theme-border rounded-[2rem]">
                                <History className="w-12 h-12 theme-text-muted mx-auto mb-4 opacity-50" />
                                <h3 className="text-lg font-black italic uppercase theme-text-main">Sin registros</h3>
                                <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted mt-2">No se han detectado modificaciones en los catálogos.</p>
                            </div>
                        ) : (
                            auditorias.data.map((log) => (
                                <div key={log.id} className="theme-element border theme-border p-5 rounded-2xl flex flex-col xl:flex-row items-center justify-between gap-6 transition-all duration-150 hover:shadow-md hover:ring-2 hover:ring-[var(--color-primario)]/40 group">
                                    
                                    {/* IDENTIFICADOR Y FECHA */}
                                    <div className="flex items-center gap-4 w-full xl:w-auto">
                                        <div className="w-14 h-14 theme-surface border theme-border rounded-2xl flex items-center justify-center transition-transform group-hover:scale-110 shadow-sm shrink-0"
                                            style={{ color: 'var(--color-primario)' }}>
                                            <Tag className="w-6 h-6" />
                                        </div>
                                        
                                        <div>
                                            <h3 className="text-[15px] font-black theme-text-main leading-tight uppercase truncate max-w-[300px]">
                                                {log.lista?.nombre || 'CATÁLOGO ELIMINADO'}
                                            </h3>
                                            <div className="flex flex-wrap items-center gap-3 mt-2">
                                                <span className="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                                    <Calendar className="w-3 h-3" />
                                                    {formatDate(log.created_at)}
                                                </span>
                                                <span className={`flex items-center gap-1 text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-md border ${
                                                    log.origen_cambio === 'INTERFAZ WEB' 
                                                        ? 'bg-blue-500/10 text-blue-500 border-blue-500/20' 
                                                        : 'bg-amber-500/10 text-amber-500 border-amber-500/20'
                                                }`}>
                                                    {log.origen_cambio === 'INTERFAZ WEB' ? <Globe className="w-3 h-3"/> : <Terminal className="w-3 h-3"/>}
                                                    {log.origen_cambio}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* CAMBIO DE MONTOS Y RESPONSABLE */}
                                    <div className="flex flex-col md:flex-row items-center justify-between xl:justify-end gap-6 w-full xl:w-auto mt-2 xl:mt-0">
                                        
                                        {/* Delta Monetario */}
                                        <div className="flex items-center gap-3 theme-surface px-5 py-3 rounded-xl border theme-border w-full md:w-auto justify-center shadow-sm">
                                            <span className="text-xs font-bold text-rose-500 line-through">
                                                {formatCurrency(log.monto_anterior)}
                                            </span>
                                            <ArrowRight className="w-4 h-4 theme-text-muted" />
                                            <span className="text-[15px] font-black text-emerald-500">
                                                {formatCurrency(log.monto_nuevo)}
                                            </span>
                                        </div>
                                        
                                        {/* Usuario */}
                                        <div className="text-center md:text-right border-t md:border-t-0 md:border-l theme-border pt-4 md:pt-0 md:pl-6 w-full md:w-auto">
                                            <p className="text-[8px] font-black theme-text-muted uppercase tracking-widest">Responsable_</p>
                                            <div className="flex items-center justify-center md:justify-end gap-1.5 mt-0.5">
                                                <User className="w-3 h-3 theme-text-main" />
                                                <p className="text-[10px] font-black theme-text-main uppercase italic truncate max-w-[150px]">
                                                    {log.usuario?.name || 'SISTEMA (AUTOMATIZADO)'}
                                                </p>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            ))
                        )}
                        
                        {/* Paginador nativo de Laravel/Inertia adaptado a tu diseño */}
                        {auditorias.links && auditorias.data.length > 0 && (
                            <div className="flex flex-col md:flex-row items-center justify-between pt-8 mt-4 border-t theme-border gap-4">
                                <span className="text-[10px] font-black theme-text-muted uppercase tracking-widest">
                                    Mostrando página {auditorias.current_page} de {auditorias.last_page}
                                </span>

                                <div className="flex items-center gap-1">
                                    {auditorias.links.map((link, i) => {
                                        // Reemplazar flechas de texto con iconos
                                        let label = link.label;
                                        if (label.includes('&laquo;')) label = '<';
                                        if (label.includes('&raquo;')) label = '>';

                                        return (
                                            <button
                                                key={i}
                                                onClick={() => {
                                                    if (link.url) router.get(link.url, {}, { preserveState: true });
                                                }}
                                                disabled={!link.url}
                                                className={`w-10 h-10 flex items-center justify-center rounded-xl text-xs font-black transition-all outline-none shadow-sm ${
                                                    link.active 
                                                        ? 'text-white' 
                                                        : 'theme-surface border theme-border theme-text-muted hover:scale-105 disabled:opacity-50 disabled:hover:scale-100'
                                                }`}
                                                style={{ backgroundColor: link.active ? 'var(--color-primario)' : '' }}
                                            >
                                                {label}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}