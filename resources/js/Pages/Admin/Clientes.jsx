import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate } from 'animejs/animation'; // Importación modular corregida
import { 
    Users, Upload, Search, 
    FileSpreadsheet, TrendingUp, 
    CheckCircle, Database, Edit3, X, ChevronDown, Sparkles
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Clientes({ auth, clientes = [], vendedores = [] }) {
    // --- ESTADOS LOCALES ---
    const [busqueda, setBusqueda] = useState('');
    const [filtroLista, setFiltroLista] = useState('Todas');
    const [filtroTipo, setFiltroTipo] = useState('Todos');
    const [dragActive, setDragActive] = useState(false);
    
    // Estados para el Modal de Gestión Manual
    const [clienteEditando, setClienteEditando] = useState(null);

    // --- FORMULARIOS INERTIA ---
    const formCarga = useForm({
        archivo: null,
    });

    const formAsignacion = useForm({
        vendedor_id: '',
        es_heredado: false
    });

    // --- ANIMACIONES DE ENTRADA ---
    useEffect(() => {
        animate('.fade-up', {
            translateY: [15, 0],
            opacity: [0, 1],
            easing: 'easeOutExpo',
            duration: 600,
            delay: (el, i) => i * 100
        });
    }, []);

    // --- MANEJO DE ACCIONES ---
    const handleUpload = (e) => {
        e.preventDefault();
        formCarga.post(route('admin.clientes.importar'), {
            onSuccess: () => {
                formCarga.reset();
                alert('Base de datos sincronizada correctamente.');
            }
        });
    };

    const abrirModalAsignacion = (cliente) => {
        setClienteEditando(cliente);
        formAsignacion.setData({
            vendedor_id: cliente.vendedor_id || '',
            es_heredado: cliente.es_heredado === 1 || cliente.es_heredado === true
        });
    };

    const guardarAsignacion = (e) => {
        e.preventDefault();
        formAsignacion.put(route('admin.clientes.update', clienteEditando.id), {
            onSuccess: () => setClienteEditando(null)
        });
    };

    // --- FUNCIÓN RENDERIZADORA DE LISTAS (UI/UX) ---
    const renderBadgeLista = (nombreLista) => {
        if (!nombreLista) return <span className="px-2 py-1 bg-zinc-500/10 text-zinc-500 text-[9px] font-black uppercase tracking-widest rounded-md">Sin Lista</span>;
        
        // Limpiamos el texto: quitamos "MAYOREO " y normalizamos
        const nivel = nombreLista.toUpperCase().replace('MAYOREO ', '').trim();

        switch (nivel) {
            case 'BRONCE':
                return (
                    <span className="px-2 py-1 bg-[#cd7f32]/10 text-[#cd7f32] border border-[#cd7f32]/30 text-[9px] font-black uppercase tracking-widest rounded-md">
                        Bronce
                    </span>
                );
            case 'PLATA':
                return (
                    <span className="px-2 py-1 bg-slate-400/10 text-slate-500 border border-slate-400/30 text-[9px] font-black uppercase tracking-widest rounded-md">
                        Plata
                    </span>
                );
            case 'ORO':
                return (
                    <span className="px-2 py-1 bg-yellow-500/10 text-yellow-600 border border-yellow-500/30 text-[9px] font-black uppercase tracking-widest rounded-md">
                        Oro
                    </span>
                );
            case 'DIAMANTE':
                return (
                    <span className="relative flex items-center gap-1.5 px-2.5 py-1 bg-gradient-to-r from-cyan-500/10 to-blue-500/10 border border-cyan-400/40 text-[9px] font-black uppercase tracking-widest rounded-md overflow-hidden shadow-[0_0_8px_rgba(34,211,238,0.2)]">
                        <Sparkles className="w-3 h-3 text-cyan-600 dark:text-cyan-300" /> 
                        <span className="text-cyan-700 dark:text-cyan-300 drop-shadow-sm">Diamante</span>
                        
                        {/* Efecto de cristal (Sweep diagonal) */}
                        <span className="absolute inset-0 w-[150%] -translate-x-full bg-gradient-to-r from-transparent via-cyan-100/60 dark:via-white/20 to-transparent skew-x-12 animate-[shimmer_3s_infinite_ease-in-out]"></span>
                    </span>
                );
            case 'PUBLICO GENERAL':
                return (
                    <span className="px-2 py-1 bg-zinc-500/10 text-zinc-500 border border-zinc-500/30 text-[9px] font-black uppercase tracking-widest rounded-md">
                        Público Gral.
                    </span>
                );
            case 'COLABORADORES':
                return (
                    <span className="px-2 py-1 bg-purple-500/10 text-purple-600 border border-purple-500/30 text-[9px] font-black uppercase tracking-widest rounded-md">
                        Colaborador
                    </span>
                );
            default:
                return (
                    <span className="px-2 py-1 bg-blue-500/10 text-blue-500 border border-blue-500/30 text-[9px] font-black uppercase tracking-widest rounded-md">
                        {nivel}
                    </span>
                );
        }
    };

    // --- LÓGICA DE FILTRADO ---
    // Extraer listas únicas para poblar el select dinámicamente
    const listasUnicas = ['Todas', ...new Set(clientes.map(c => c.lista_descuento?.nombre || 'Sin Lista'))];

    const clientesFiltrados = clientes.filter(cliente => {
        const matchBusqueda = cliente.nombre.toLowerCase().includes(busqueda.toLowerCase()) || 
                              cliente.numero_cliente.toLowerCase().includes(busqueda.toLowerCase());
        
        const nombreLista = cliente.lista_descuento?.nombre || 'Sin Lista';
        const matchLista = filtroLista === 'Todas' || nombreLista === filtroLista;
        
        const matchTipo = filtroTipo === 'Todos' || 
                          (filtroTipo === 'Heredados' ? cliente.es_heredado : !cliente.es_heredado);

        return matchBusqueda && matchLista && matchTipo;
    });

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de Clientes | GELIANV" />

            {/* --- MODAL DE GESTIÓN MANUAL --- */}
            {clienteEditando && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-900/40 backdrop-blur-sm">
                    <div className="theme-surface border border-zinc-200 dark:border-zinc-800 rounded-[3rem] p-8 md:p-10 shadow-2xl max-w-md w-full relative z-10 fade-up">
                        
                        <button onClick={() => setClienteEditando(null)} className="absolute top-6 right-6 p-3 theme-element rounded-2xl hover:text-[var(--color-primario)] transition-colors">
                            <X className="w-5 h-5 theme-text-muted" />
                        </button>
                        
                        <form onSubmit={guardarAsignacion} className="space-y-6">
                            <div>
                                <h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter mb-1">
                                    Editar <span style={{ color: 'var(--color-primario)' }}>Cliente</span>
                                </h2>
                                <p className="text-xs font-bold theme-text-muted truncate">{clienteEditando.nombre}</p>
                            </div>
                            
                            <div className="space-y-3">
                                <label className="text-[10px] font-black uppercase theme-text-muted ml-2 tracking-widest italic">Vendedora Asignada_</label>
                                <select 
                                    value={formAsignacion.data.vendedor_id} 
                                    onChange={e => formAsignacion.setData('vendedor_id', e.target.value)}
                                    className="w-full p-4 theme-element border theme-border rounded-2xl theme-text-main font-bold outline-none transition-all text-sm cursor-pointer focus:border-[var(--color-primario)]"
                                >
                                    <option value="">-- Sin Asignar --</option>
                                    {vendedores?.map(vendedor => (
                                        <option key={vendedor.id} value={vendedor.id}>{vendedor.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div 
                                className="p-4 theme-element border theme-border rounded-2xl flex items-center justify-between cursor-pointer group transition-colors hover:border-[var(--color-primario)]" 
                                style={{ borderColor: formAsignacion.data.es_heredado ? 'var(--color-primario)' : '' }}
                                onClick={() => formAsignacion.setData('es_heredado', !formAsignacion.data.es_heredado)}
                            >
                                <div>
                                    <p className="text-xs font-black uppercase theme-text-main transition-colors group-hover:text-[var(--color-primario)]" style={{ color: formAsignacion.data.es_heredado ? 'var(--color-primario)' : '' }}>Cliente Heredado</p>
                                    <p className="text-[10px] font-bold theme-text-muted italic mt-1">Activa reglas de seguridad especiales</p>
                                </div>
                                <input 
                                    type="checkbox" 
                                    checked={formAsignacion.data.es_heredado}
                                    readOnly
                                    className="w-5 h-5 rounded border-zinc-300 cursor-pointer pointer-events-none"
                                    style={{ color: 'var(--color-primario)' }}
                                />
                            </div>

                            <div className="pt-4">
                                <button type="submit" disabled={formAsignacion.processing} className="w-full py-5 bg-zinc-900 dark:bg-white text-white dark:text-black rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all shadow-xl hover:scale-[1.02] disabled:opacity-50" style={{ backgroundColor: 'var(--color-primario)', color: 'white' }}>
                                    {formAsignacion.processing ? 'Guardando...' : 'Guardar Configuración_'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            <div className="max-w-7xl mx-auto p-6 md:p-12 space-y-12">
                {/* --- ENCABEZADO --- */}
                <header className="fade-up space-y-4">
                    <div className="flex items-center space-x-3">
                        <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                        <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Base de Datos Wizerp</p>
                    </div>
                    <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-tight transition-colors">
                        SISTEMA DE <span style={{ color: 'var(--color-primario)' }}>CLIENTES</span>
                    </h1>
                </header>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-12 items-start">
                    
                    {/* --- PANEL LATERAL: CARGA MASIVA CSV --- */}
                    <div className="fade-up lg:col-span-1 space-y-6">
                        <div 
                            className="theme-surface border-2 rounded-[2.5rem] p-8 shadow-sm transition-all relative overflow-hidden theme-border"
                            style={{ borderColor: dragActive ? 'var(--color-primario)' : '' }}
                        >
                            <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter flex items-center mb-6">
                                <Upload className="w-5 h-5 mr-3" style={{ color: 'var(--color-primario)' }} />
                                Carga Masiva
                            </h2>
                            
                            <form onSubmit={handleUpload} className="space-y-6">
                                {/* UX MEJORADA: Toda la caja es un Label clickeable */}
                                <label 
                                    className="border-2 border-dashed theme-border rounded-3xl p-10 flex flex-col items-center justify-center text-center space-y-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/10 transition-colors cursor-pointer group w-full block"
                                    onDragOver={(e) => { e.preventDefault(); setDragActive(true); }}
                                    onDragLeave={() => setDragActive(false)}
                                    onDrop={(e) => {
                                        e.preventDefault();
                                        setDragActive(false);
                                        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                                            formCarga.setData('archivo', e.dataTransfer.files[0]);
                                        }
                                    }}
                                >
                                    <div className="w-16 h-16 theme-element rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                                        <FileSpreadsheet className="w-8 h-8 theme-text-muted" style={{ color: formCarga.data.archivo ? 'var(--color-primario)' : '' }} />
                                    </div>
                                    <div>
                                        <p className="text-xs font-black theme-text-main uppercase">Suelte el archivo aquí_</p>
                                        <p className="text-[10px] theme-text-muted italic mt-1 uppercase font-bold">Formatos: .csv</p>
                                    </div>
                                    
                                    <span className="text-[9px] font-black uppercase tracking-widest underline" style={{ color: 'var(--color-primario)' }}>
                                        O examinar archivos
                                    </span>
                                    
                                    <input 
                                        type="file" 
                                        className="hidden" 
                                        accept=".csv,.txt"
                                        onChange={e => formCarga.setData('archivo', e.target.files[0])}
                                    />
                                </label>

                                {formCarga.errors.archivo && (
                                    <p className="text-red-500 text-xs font-bold mt-2">{formCarga.errors.archivo}</p>
                                )}

                                {formCarga.data.archivo && (
                                    <div className="flex items-center gap-3 p-4 theme-element rounded-2xl border-2 border-emerald-500/20">
                                        <CheckCircle className="w-4 h-4 text-emerald-500" />
                                        <span className="text-[10px] font-bold theme-text-main truncate">{formCarga.data.archivo.name}</span>
                                    </div>
                                )}

                                <button 
                                    disabled={formCarga.processing || !formCarga.data.archivo}
                                    className="w-full py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all disabled:opacity-30 hover:scale-[1.02]"
                                    style={{ backgroundColor: 'var(--color-primario)' }}
                                >
                                    {formCarga.processing ? 'Sincronizando...' : 'Actualizar Base de Datos'}
                                </button>
                            </form>

                            <div className="mt-8 p-6 theme-element rounded-3xl space-y-4">
                                <div className="flex items-center gap-2 text-amber-500">
                                    <Database className="w-4 h-4" />
                                    <p className="text-[9px] font-black uppercase tracking-widest italic">Cabeceras Soportadas_</p>
                                </div>
                                <p className="text-[10px] theme-text-muted font-bold leading-relaxed italic">
                                    El sistema detecta automáticamente los campos. Puedes enviar un archivo solo con las columnas necesarias. <br/><br/>
                                    <strong style={{ color: 'var(--color-primario)' }}>numero_cliente</strong> (Requerido)<br/>
                                    <strong style={{ color: 'var(--color-primario)' }}>nombre</strong><br/>
                                    <strong style={{ color: 'var(--color-primario)' }}>codigo_lista</strong> (Ej: PG, 1, 2, 3, 4, 7)<br/>
                                    <strong style={{ color: 'var(--color-primario)' }}>monto_venta_actual</strong><br/>
                                    <strong style={{ color: 'var(--color-primario)' }}>vendedor_id</strong> (TAG de la Vendedora)
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* --- PANEL PRINCIPAL: LISTADO DE CLIENTES --- */}
                    <div className="fade-up lg:col-span-2 space-y-6">
                        
                        {/* Buscador y Filtros */}
                        <div className="flex flex-col md:flex-row gap-4 items-center justify-between theme-surface border-2 theme-border p-4 rounded-[2rem] shadow-sm">
                            <div className="relative w-full md:flex-1">
                                <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                <input 
                                    type="text" 
                                    placeholder="Buscar por número o nombre..." 
                                    value={busqueda}
                                    onChange={e => setBusqueda(e.target.value)}
                                    className="w-full pl-12 pr-4 py-3 theme-element border theme-border rounded-xl theme-text-main font-bold outline-none focus:border-[var(--color-primario)] transition-all theme-placeholder text-xs"
                                />
                            </div>

                            <div className="flex gap-4 w-full md:w-auto">
                                <div className="relative flex-1 md:w-40">
                                    <select 
                                        value={filtroLista}
                                        onChange={e => setFiltroLista(e.target.value)}
                                        className="w-full pl-4 pr-10 py-3 theme-element border theme-border rounded-xl theme-text-main font-black uppercase tracking-widest text-[9px] appearance-none cursor-pointer outline-none focus:border-[var(--color-primario)] transition-all"
                                    >
                                        {listasUnicas.map(lista => (
                                            <option key={lista} value={lista}>{lista}</option>
                                        ))}
                                    </select>
                                    <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 theme-text-muted pointer-events-none" />
                                </div>
                                <div className="relative flex-1 md:w-40">
                                    <select 
                                        value={filtroTipo}
                                        onChange={e => setFiltroTipo(e.target.value)}
                                        className="w-full pl-4 pr-10 py-3 theme-element border theme-border rounded-xl theme-text-main font-black uppercase tracking-widest text-[9px] appearance-none cursor-pointer outline-none focus:border-[var(--color-primario)] transition-all"
                                    >
                                        <option value="Todos">TODOS LOS TIPOS</option>
                                        <option value="Directos">DIRECTOS</option>
                                        <option value="Heredados">HEREDADOS</option>
                                    </select>
                                    <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 theme-text-muted pointer-events-none" />
                                </div>
                            </div>
                        </div>

                        {/* Contenedor de la lista */}
                        <div className="space-y-4 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar">
                            {clientesFiltrados.length === 0 ? (
                                <div className="text-center py-12 theme-surface border-2 border-dashed theme-border rounded-[2rem]">
                                    <Users className="w-12 h-12 theme-text-muted mx-auto mb-4 opacity-50" />
                                    <h3 className="text-lg font-black italic uppercase theme-text-main">Sin resultados</h3>
                                    <p className="text-xs font-bold theme-text-muted mt-2">No se encontraron clientes que coincidan con los filtros.</p>
                                </div>
                            ) : (
                                clientesFiltrados.map((cliente) => (
                                    <div key={cliente.id} className="theme-surface border-2 theme-border p-6 rounded-[2rem] flex flex-col md:flex-row items-center justify-between gap-6 transition-all group hover:border-[var(--color-primario)]">
                                        
                                        <div className="flex items-center gap-6 w-full md:w-auto">
                                            {/* Badge Número de Cliente */}
                                            <div className="w-16 h-16 theme-element border-2 theme-border rounded-2xl flex items-center justify-center font-black italic theme-text-main text-xs transition-colors group-hover:border-[var(--color-primario)]">
                                                {cliente.numero_cliente}
                                            </div>
                                            
                                            {/* Información General */}
                                            <div>
                                                <h3 className="text-lg font-black italic theme-text-main uppercase tracking-tighter truncate max-w-[200px] md:max-w-[300px]">
                                                    {cliente.nombre}
                                                </h3>
                                                <div className="flex flex-wrap items-center gap-3 mt-1">
                                                    {renderBadgeLista(cliente.lista_descuento?.nombre)}
                                                    <span className="flex items-center gap-1 text-[10px] font-bold theme-text-muted">
                                                        <TrendingUp className="w-3 h-3 text-emerald-500" /> 
                                                        {new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(cliente.monto_venta_actual)}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Status y Botón de Gestión */}
                                        <div className="flex items-center gap-3 w-full md:w-auto justify-end">
                                            {cliente.es_heredado ? (
                                                <div className="p-3 theme-element rounded-2xl flex flex-col items-end border border-amber-500/20 bg-amber-500/5 min-w-[100px]">
                                                    <p className="text-[8px] font-black text-amber-600 uppercase tracking-widest">Protegido_</p>
                                                    <p className="text-[10px] font-black text-amber-500 uppercase italic">Heredado</p>
                                                </div>
                                            ) : (
                                                <div className="p-3 theme-element rounded-2xl flex flex-col items-end min-w-[100px]">
                                                    <p className="text-[8px] font-black theme-text-muted uppercase tracking-widest">Asignación_</p>
                                                    <p className="text-[10px] font-black theme-text-main uppercase italic truncate max-w-[100px]">
                                                        {cliente.vendedor ? cliente.vendedor.name : 'Sin Asignar'}
                                                    </p>
                                                </div>
                                            )}

                                            <button 
                                                onClick={() => abrirModalAsignacion(cliente)}
                                                className="p-4 theme-element border-2 theme-border rounded-2xl transition-all text-zinc-400 group/btn hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]"
                                                title="Editar cliente manualmente"
                                            >
                                                <Edit3 className="w-5 h-5" />
                                            </button>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* --- ESTILOS MAESTROS --- */}
            <style>{`
                .theme-surface { background-color: #ffffff; border-color: #f4f4f5; }
                .theme-element { background-color: #fafafa; border-color: #e4e4e7; }
                .theme-text-main { color: #18181b; }
                .theme-text-muted { color: #71717a; }
                .theme-border { border-color: #f4f4f5; }
                .theme-placeholder::placeholder { color: #a1a1aa; }
                
                .dark .theme-surface { background-color: #141414; border-color: #2A2A2A; }
                .dark .theme-element { background-color: #1A1A1A; border-color: #333333; }
                .dark .theme-text-main { color: #ffffff; }
                .dark .theme-text-muted { color: #a1a1aa; }
                .dark .theme-border { border-color: #2A2A2A; }
                .dark .theme-placeholder::placeholder { color: #52525b; }

                .custom-scrollbar::-webkit-scrollbar { width: 6px; }
                .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
                .custom-scrollbar::-webkit-scrollbar-thumb { background-color: var(--color-primario); border-radius: 10px; }

                @keyframes shimmer {
                    0% { transform: translateX(-200%); }
                    50%, 100% { transform: translateX(200%); }
                }
            `}</style>
        </AppLayout>
    );
}