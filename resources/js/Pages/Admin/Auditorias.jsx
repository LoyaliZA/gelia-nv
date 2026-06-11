import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    History, ChevronDown, Calendar,
    ArrowRight, User, Terminal, Globe, Tag,
    Settings, Code, FileJson
} from 'lucide-react';

// Se ajusta la ruta relativa basándonos en tu estructura
import AppLayout from '../../Layouts/AppLayout';
import { geliaCardClass } from '../../utils/geliaTheme';

export default function Auditorias({ auth, auditorias, auditoriasConfiguracion, listas, filtros, tabActivo = 'catalogos', isSuperAdmin = false, usuariosFiltro = [] }) {
    
    // Estados para mantener los selectores sincronizados con la URL
    const [filtroLista, setFiltroLista] = useState(filtros.lista_id || '');
    const [filtroOrigen, setFiltroOrigen] = useState(filtros.origen || '');
    const [jsonModal, setJsonModal] = useState(null);

    // Disparador de Inertia para recargar la data según los filtros y tabs
    const handleNavigation = (nuevosParametros) => {
        router.get(route('admin.auditorias_sistema.index'), { ...filtros, tab: tabActivo, ...nuevosParametros }, {
            preserveState: true,
            replace: true
        });
    };

    const handleTabChange = (tab) => {
        router.get(route('admin.auditorias_sistema.index'), { tab }, {
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
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    };

    // Clase base compartida que extrajimos de Clientes.jsx
    const activeCardClass = geliaCardClass('relative z-10');

    // Renderizado del paginador
    const Paginador = ({ data }) => {
        if (!data?.links || data.data.length === 0) return null;
        return (
            <div className="flex flex-col md:flex-row items-center justify-between pt-8 mt-4 border-t theme-border gap-4">
                <span className="text-[10px] font-black theme-text-muted uppercase tracking-widest">
                    Mostrando página {data.current_page} de {data.last_page}
                </span>

                <div className="flex items-center gap-1">
                    {data.links.map((link, i) => {
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
        );
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Auditorías del Sistema | GELIANV" />
            
            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">
                
                {/* --- ENCABEZADO --- */}
                <header className={`${activeCardClass} p-8 md:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-6`} style={{ animationDelay: '0ms' }}>
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

                {/* --- TABS --- */}
                {isSuperAdmin && (
                    <div className="flex space-x-2 bg-black/5 dark:bg-white/5 p-1.5 rounded-2xl w-full max-w-md mx-auto sm:mx-0">
                        <button
                            onClick={() => handleTabChange('catalogos')}
                            className={`flex-1 flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-xs font-black uppercase tracking-widest transition-all ${
                                tabActivo === 'catalogos'
                                    ? 'bg-white dark:bg-zinc-800 shadow-md theme-text-main'
                                    : 'theme-text-muted hover:bg-black/5 dark:hover:bg-white/5'
                            }`}
                        >
                            <Tag className="w-4 h-4" />
                            Catálogos
                        </button>
                        <button
                            onClick={() => handleTabChange('configuracion')}
                            className={`flex-1 flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-xs font-black uppercase tracking-widest transition-all ${
                                tabActivo === 'configuracion'
                                    ? 'bg-white dark:bg-zinc-800 shadow-md theme-text-main'
                                    : 'theme-text-muted hover:bg-black/5 dark:hover:bg-white/5'
                            }`}
                        >
                            <Settings className="w-4 h-4" />
                            Configuración
                        </button>
                    </div>
                )}
                
                {/* --- FILTROS Y LISTADO --- */}
                <section className={`${activeCardClass} p-8 space-y-8`} style={{ animationDelay: '100ms' }}>
                    
                    {tabActivo === 'catalogos' && (
                        <>
                            {/* Barra de Búsqueda / Filtros para Catálogos */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="relative">
                                    <select
                                        value={filtroLista}
                                        onChange={(e) => {
                                            setFiltroLista(e.target.value);
                                            handleNavigation({ lista_id: e.target.value });
                                        }}
                                        className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
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
                                            handleNavigation({ origen: e.target.value });
                                        }}
                                        className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
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
                            
                            {/* Contenedor de Registros Catálogos */}
                            <div className="space-y-4">
                                {auditorias?.data?.length === 0 ? (
                                    <div className="text-center py-16 theme-element border-2 border-dashed theme-border rounded-[2rem]">
                                        <History className="w-12 h-12 theme-text-muted mx-auto mb-4 opacity-50" />
                                        <h3 className="text-lg font-black italic uppercase theme-text-main">Sin registros</h3>
                                        <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted mt-2">No se han detectado modificaciones en los catálogos.</p>
                                    </div>
                                ) : (
                                    auditorias?.data?.map((log) => (
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
                                                <div className="flex items-center gap-3 theme-surface px-5 py-3 rounded-xl border theme-border w-full md:w-auto justify-center shadow-sm">
                                                    <span className="text-xs font-bold text-rose-500 line-through">
                                                        {formatCurrency(log.monto_anterior)}
                                                    </span>
                                                    <ArrowRight className="w-4 h-4 theme-text-muted" />
                                                    <span className="text-[15px] font-black text-emerald-500">
                                                        {formatCurrency(log.monto_nuevo)}
                                                    </span>
                                                </div>
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
                                <Paginador data={auditorias} />
                            </div>
                        </>
                    )}

                    {tabActivo === 'configuracion' && isSuperAdmin && (
                        <>
                            {/* Barra de Filtros para Configuración */}
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                                <div className="relative">
                                    <label className="text-[10px] font-black uppercase tracking-widest text-zinc-500 mb-1 block ml-1">Fecha Inicio</label>
                                    <input
                                        type="date"
                                        value={filtros.fecha_inicio || ''}
                                        onChange={(e) => handleNavigation({ fecha_inicio: e.target.value })}
                                        className="w-full px-5 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    />
                                </div>
                                <div className="relative">
                                    <label className="text-[10px] font-black uppercase tracking-widest text-zinc-500 mb-1 block ml-1">Fecha Fin</label>
                                    <input
                                        type="date"
                                        value={filtros.fecha_fin || ''}
                                        onChange={(e) => handleNavigation({ fecha_fin: e.target.value })}
                                        className="w-full px-5 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    />
                                </div>
                                <div className="relative">
                                    <label className="text-[10px] font-black uppercase tracking-widest text-zinc-500 mb-1 block ml-1">Efectuado Por</label>
                                    <select
                                        value={filtros.user_id || ''}
                                        onChange={(e) => handleNavigation({ user_id: e.target.value })}
                                        className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    >
                                        <option value="">TODOS</option>
                                        {usuariosFiltro?.map(u => (
                                            <option key={u.id} value={u.id}>{u.name} {u.apellido_paterno}</option>
                                        ))}
                                    </select>
                                    <div className="pointer-events-none absolute bottom-4 right-4 flex items-center">
                                        <ChevronDown className="w-4 h-4 theme-text-muted" />
                                    </div>
                                </div>
                                <div className="relative">
                                    <label className="text-[10px] font-black uppercase tracking-widest text-zinc-500 mb-1 block ml-1">Afectado</label>
                                    <select
                                        value={filtros.target_user_id || ''}
                                        onChange={(e) => handleNavigation({ target_user_id: e.target.value })}
                                        className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    >
                                        <option value="">TODOS</option>
                                        {usuariosFiltro?.map(u => (
                                            <option key={u.id} value={u.id}>{u.name} {u.apellido_paterno}</option>
                                        ))}
                                    </select>
                                    <div className="pointer-events-none absolute bottom-4 right-4 flex items-center">
                                        <ChevronDown className="w-4 h-4 theme-text-muted" />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4">
                                {auditoriasConfiguracion?.data?.length === 0 ? (
                                    <div className="text-center py-16 theme-element border-2 border-dashed theme-border rounded-[2rem]">
                                        <Settings className="w-12 h-12 theme-text-muted mx-auto mb-4 opacity-50" />
                                        <h3 className="text-lg font-black italic uppercase theme-text-main">Sin registros de configuración</h3>
                                        <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted mt-2">Aún no hay cambios registrados en usuarios o permisos.</p>
                                    </div>
                                ) : (
                                    auditoriasConfiguracion?.data?.map((log) => (
                                        <div key={log.id} className="theme-element border theme-border p-5 rounded-2xl flex flex-col items-start gap-4 transition-all duration-150 hover:shadow-md hover:ring-2 hover:ring-[var(--color-primario)]/40 group">
                                            
                                            <div className="flex flex-col md:flex-row md:items-center justify-between w-full gap-4">
                                                <div className="flex items-center gap-4">
                                                    <div className="w-14 h-14 theme-surface border theme-border rounded-2xl flex items-center justify-center transition-transform group-hover:scale-110 shadow-sm shrink-0"
                                                        style={{ color: 'var(--color-primario)' }}>
                                                        <Settings className="w-6 h-6" />
                                                    </div>
                                                    
                                                    <div>
                                                        <h3 className="text-[15px] font-black theme-text-main leading-tight uppercase">
                                                            {log.modulo} / {log.accion}
                                                        </h3>
                                                        <div className="flex flex-wrap items-center gap-3 mt-2">
                                                            <span className="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                                                <Calendar className="w-3 h-3" />
                                                                {formatDate(log.created_at)}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="flex flex-col items-start md:items-end gap-2 border-t md:border-t-0 md:border-l theme-border pt-4 md:pt-0 md:pl-6">
                                                    <div className="flex flex-col items-start md:items-end">
                                                        <p className="text-[8px] font-black theme-text-muted uppercase tracking-widest">Ejecutado por_</p>
                                                        <div className="flex items-center gap-1.5 mt-0.5">
                                                            <User className="w-3 h-3 theme-text-main" />
                                                            <p className="text-[10px] font-black theme-text-main uppercase italic truncate max-w-[200px]">
                                                                {log.usuario?.name || 'SISTEMA'} {log.usuario?.apellido_paterno || ''}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    {log.usuario_afectado && (
                                                        <div className="flex flex-col items-start md:items-end mt-2">
                                                            <p className="text-[8px] font-black text-rose-500 uppercase tracking-widest">Afectado_</p>
                                                            <div className="flex items-center gap-1.5 mt-0.5">
                                                                <User className="w-3 h-3 theme-text-main" />
                                                                <p className="text-[10px] font-black theme-text-main uppercase italic truncate max-w-[200px]">
                                                                    {log.usuario_afectado.name} {log.usuario_afectado.apellido_paterno}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Detalles en texto o botón para JSON */}
                                            {log.detalles && (
                                                <div className="w-full mt-2 flex flex-col gap-2">
                                                    <div className="p-4 bg-black/5 dark:bg-white/5 rounded-xl border border-black/10 dark:border-white/10 flex justify-between items-center">
                                                        <div className="text-xs theme-text-main opacity-80 max-w-2xl truncate">
                                                            {log.detalles.descripcion || 'Se registraron cambios en la configuración. Revise el snapshot para más detalles.'}
                                                        </div>
                                                        <button
                                                            onClick={() => setJsonModal(log)}
                                                            className="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-emerald-500 hover:text-emerald-400 bg-emerald-500/10 px-3 py-1.5 rounded-lg border border-emerald-500/20"
                                                        >
                                                            <FileJson className="w-4 h-4" />
                                                            Ver Snapshot
                                                        </button>
                                                    </div>
                                                    
                                                    {/* Áreas y Departamentos */}
                                                    {(log.detalles.areas || log.detalles.departamentos || (log.detalles.area_principal && typeof log.detalles.area_principal === 'object')) && (
                                                        <div className="bg-black/5 dark:bg-white/5 p-4 rounded-xl border border-black/10 dark:border-white/10">
                                                            <h4 className="text-[10px] font-black uppercase tracking-widest text-zinc-500 mb-2">Áreas y Departamentos</h4>
                                                            
                                                            {typeof log.detalles.area_principal === 'object' && log.detalles.area_principal?.nueva && (
                                                                <div className="flex gap-2 text-xs mb-1">
                                                                    <span className="font-bold theme-text-main">Área Principal:</span>
                                                                    <span className="text-emerald-500">{log.detalles.area_principal.nueva}</span>
                                                                    {log.detalles.area_principal.anterior && (
                                                                        <span className="text-rose-500 line-through text-[10px] opacity-70">({log.detalles.area_principal.anterior})</span>
                                                                    )}
                                                                </div>
                                                            )}

                                                            {log.detalles.areas?.asignadas?.length > 0 && (
                                                                <div className="text-xs mb-1"><span className="font-bold text-emerald-500">+ Áreas asignadas:</span> {log.detalles.areas.asignadas.join(', ')}</div>
                                                            )}
                                                            {log.detalles.areas?.retiradas?.length > 0 && (
                                                                <div className="text-xs mb-1"><span className="font-bold text-rose-500">- Áreas retiradas:</span> {log.detalles.areas.retiradas.join(', ')}</div>
                                                            )}
                                                            {log.detalles.departamentos?.asignados?.length > 0 && (
                                                                <div className="text-xs mb-1"><span className="font-bold text-emerald-500">+ Departamentos asignados:</span> {log.detalles.departamentos.asignados.join(', ')}</div>
                                                            )}
                                                            {log.detalles.departamentos?.retirados?.length > 0 && (
                                                                <div className="text-xs mb-1"><span className="font-bold text-rose-500">- Departamentos retirados:</span> {log.detalles.departamentos.retirados.join(', ')}</div>
                                                            )}
                                                        </div>
                                                    )}

                                                    {/* Permisos (Soporte nuevo formato y formato legacy) */}
                                                    {(log.detalles.permisos || log.detalles.permisos_nuevos) && (
                                                        <div className="bg-black/5 dark:bg-white/5 p-4 rounded-xl border border-black/10 dark:border-white/10">
                                                            <h4 className="text-[10px] font-black uppercase tracking-widest text-zinc-500 mb-2">Permisos</h4>
                                                            
                                                            {log.detalles.permisos ? (
                                                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                                    <div>
                                                                        <h5 className="text-[9px] font-bold uppercase tracking-widest text-emerald-500 mb-1">Asignados ({log.detalles.permisos.asignados?.length || 0})</h5>
                                                                        <ul className="text-[10px] opacity-80 list-disc list-inside theme-text-main max-h-32 overflow-y-auto">
                                                                            {log.detalles.permisos.asignados?.map(p => <li key={p}>{p}</li>)}
                                                                        </ul>
                                                                    </div>
                                                                    <div>
                                                                        <h5 className="text-[9px] font-bold uppercase tracking-widest text-rose-500 mb-1">Retirados ({log.detalles.permisos.retirados?.length || 0})</h5>
                                                                        <ul className="text-[10px] opacity-80 list-disc list-inside theme-text-main max-h-32 overflow-y-auto">
                                                                            {log.detalles.permisos.retirados?.map(p => <li key={p}>{p}</li>)}
                                                                        </ul>
                                                                    </div>
                                                                    <div>
                                                                        <h5 className="text-[9px] font-bold uppercase tracking-widest text-blue-500 mb-1">Actuales ({log.detalles.permisos.actuales?.length || 0})</h5>
                                                                        <ul className="text-[10px] opacity-80 list-disc list-inside theme-text-main max-h-32 overflow-y-auto">
                                                                            {log.detalles.permisos.actuales?.map(p => <li key={p}>{p}</li>)}
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            ) : (
                                                                <div>
                                                                    <h5 className="text-[9px] font-bold uppercase tracking-widest text-blue-500 mb-1">Actualizados ({log.detalles.permisos_nuevos?.length || 0})</h5>
                                                                    <ul className="text-[10px] opacity-80 list-disc list-inside theme-text-main max-h-32 overflow-y-auto">
                                                                        {log.detalles.permisos_nuevos?.map(p => <li key={p}>{p}</li>)}
                                                                    </ul>
                                                                </div>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    ))
                                )}
                                <Paginador data={auditoriasConfiguracion} />
                            </div>
                        </>
                    )}
                </section>
            </div>

            {/* Modal para ver JSON Snapshot */}
            {jsonModal && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                    <div className="bg-white dark:bg-zinc-900 w-full max-w-3xl rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 overflow-hidden flex flex-col max-h-[90vh]">
                        <div className="p-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-zinc-50 dark:bg-zinc-900/50">
                            <h3 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2">
                                <Code className="w-5 h-5 text-emerald-500" />
                                Snapshot de Configuración
                            </h3>
                            <button onClick={() => setJsonModal(null)} className="text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 p-1">
                                &times;
                            </button>
                        </div>
                        <div className="p-6 overflow-y-auto font-mono text-xs text-zinc-800 dark:text-emerald-400 bg-zinc-50 dark:bg-black/50 whitespace-pre-wrap">
                            {JSON.stringify(jsonModal.detalles, null, 2)}
                        </div>
                        <div className="p-4 border-t border-zinc-200 dark:border-zinc-800 text-right">
                            <button onClick={() => setJsonModal(null)} className="px-4 py-2 bg-zinc-200 dark:bg-zinc-800 rounded-xl text-xs font-bold uppercase tracking-widest">
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}