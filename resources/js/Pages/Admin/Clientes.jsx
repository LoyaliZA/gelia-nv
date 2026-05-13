import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { 
    Users, Upload, Search, 
    FileSpreadsheet, TrendingUp, 
    CheckCircle, Database, Edit3, ChevronDown, Sparkles,
    ChevronLeft, ChevronRight, Plus
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

// --- IMPORTACIÓN DEL PARCIAL ---
import ModalFormCliente from './Partials/ModalFormCliente';

// --- ESTILOS COMPARTIDOS ---
const ESTILOS_ADICIONALES = `
    @keyframes slideUpFade {
        0% { opacity: 0; transform: translateY(20px); }
        100% { opacity: 1; transform: translateY(0); }
    }
    .animate-page-reveal {
        opacity: 0;
        animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @keyframes shimmer {
        0% { transform: translateX(-200%); }
        50%, 100% { transform: translateX(200%); }
    }
`;

export default function Clientes({ auth, clientes = [], vendedores = [], tipos_cliente = [] }) {
    
    // --- SECCIÓN: ESTADOS GLOBALES ---
    const [busqueda, setBusqueda] = useState('');
    const [filtroLista, setFiltroLista] = useState('Todas');
    const [filtroTipo, setFiltroTipo] = useState('Todos');
    const [dragActive, setDragActive] = useState(false);
    
    const [paginaActual, setPaginaActual] = useState(1);
    const itemsPorPagina = 10;
    
    // Control del Modal unificado
    const [modalConfig, setModalConfig] = useState({ abierto: false, modo: null, cliente: null });

    const formCarga = useForm({
        archivo: null,
    });

    useEffect(() => {
        setPaginaActual(1);
    }, [busqueda, filtroLista, filtroTipo]);

    // --- SECCIÓN: MANEJO DE ACCIONES ---
    const handleUpload = (e) => {
        e.preventDefault();
        formCarga.post(route('admin.clientes.importar'), {
            onSuccess: () => {
                formCarga.reset();
                alert('Base de datos sincronizada correctamente.');
            }
        });
    };

    const abrirModal = (modo, cliente = null) => {
        setModalConfig({ abierto: true, modo, cliente });
    };

    const cerrarModal = () => {
        setModalConfig({ abierto: false, modo: null, cliente: null });
    };

    // --- SECCIÓN: RENDERIZADORES DE UI ---
    const renderBadgeLista = (nombreLista) => {
        if (!nombreLista) return <span className="px-2 py-1 theme-element border theme-border text-zinc-500 text-[9px] font-black uppercase tracking-widest rounded-md">Sin Lista</span>;
        
        const nivel = nombreLista.toUpperCase().replace('MAYOREO ', '').trim();

        switch (nivel) {
            case 'BRONCE': return <span className="px-2 py-1 bg-[#cd7f32]/10 text-[#cd7f32] border border-[#cd7f32]/30 text-[9px] font-black uppercase tracking-widest rounded-md">Bronce</span>;
            case 'PLATA':  return <span className="px-2 py-1 bg-slate-400/10 text-slate-500 border border-slate-400/30 text-[9px] font-black uppercase tracking-widest rounded-md">Plata</span>;
            case 'ORO':    return <span className="px-2 py-1 bg-yellow-500/10 text-yellow-600 border border-yellow-500/30 text-[9px] font-black uppercase tracking-widest rounded-md">Oro</span>;
            case 'DIAMANTE':
                return (
                    <span className="relative flex items-center gap-1.5 px-2.5 py-1 bg-gradient-to-r from-cyan-500/10 to-blue-500/10 border border-cyan-400/40 text-[9px] font-black uppercase tracking-widest rounded-md overflow-hidden shadow-[0_0_8px_rgba(34,211,238,0.2)]">
                        <Sparkles className="w-3 h-3 text-cyan-600 dark:text-cyan-300" /> 
                        <span className="text-cyan-700 dark:text-cyan-300 drop-shadow-sm">Diamante</span>
                        <span className="absolute inset-0 w-[150%] -translate-x-full bg-gradient-to-r from-transparent via-cyan-100/60 dark:via-white/20 to-transparent skew-x-12 animate-[shimmer_3s_infinite_ease-in-out]"></span>
                    </span>
                );
            case 'PUBLICO GENERAL': return <span className="px-2 py-1 theme-element theme-border border text-zinc-500 text-[9px] font-black uppercase tracking-widest rounded-md">Público Gral.</span>;
            case 'COLABORADORES':   return <span className="px-2 py-1 bg-purple-500/10 text-purple-600 border border-purple-500/30 text-[9px] font-black uppercase tracking-widest rounded-md">Colaborador</span>;
            default:                return <span className="px-2 py-1 bg-blue-500/10 text-blue-500 border border-blue-500/30 text-[9px] font-black uppercase tracking-widest rounded-md">{nivel}</span>;
        }
    };

    // --- SECCIÓN: FILTRADO Y PAGINACIÓN ---
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

    const indiceUltimoItem = paginaActual * itemsPorPagina;
    const indicePrimerItem = indiceUltimoItem - itemsPorPagina;
    const clientesPaginados = clientesFiltrados.slice(indicePrimerItem, indiceUltimoItem);
    const totalPaginas = Math.ceil(clientesFiltrados.length / itemsPorPagina);

    const baseCardClass = "animate-page-reveal theme-surface border theme-border rounded-[2.5rem] shadow-[0_12px_40px_rgba(0,0,0,0.08)] dark:shadow-[0_12px_40px_rgba(0,0,0,0.5)] transition-all duration-300 relative z-10";

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de Clientes | GELIANV" />
            <style>{ESTILOS_ADICIONALES}</style>

            {/* Renderizado condicional del modal extraído */}
            {modalConfig.abierto && (
                <ModalFormCliente 
                    onClose={cerrarModal} 
                    modoModal={modalConfig.modo} 
                    clienteActual={modalConfig.cliente} 
                    tiposCliente={tipos_cliente} 
                    vendedores={vendedores} 
                />
            )}

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">
                
                {/* --- HEADER --- */}
                <header className={`${baseCardClass} p-8 md:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-6`} style={{ animationDelay: '0ms' }}>
                    <div className="flex flex-col gap-4">
                        <div className="flex items-center justify-start mb-2">
                            <div className="w-8 h-1.5 rounded-full mr-3" style={{ backgroundColor: 'var(--color-primario)' }}></div>
                            <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted drop-shadow-sm">
                                BASE DE DATOS WIZERP_
                            </span>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0 p-0">
                            SISTEMA DE <span style={{ color: 'var(--color-primario)' }}>CLIENTES</span>
                        </h1>
                    </div>
                    
                    <button 
                        onClick={() => abrirModal('crear')}
                        className="py-4 px-8 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] transition-all hover:scale-105 shadow-xl outline-none flex justify-center items-center gap-2"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Plus className="w-5 h-5" /> Nuevo Cliente_
                    </button>
                </header>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                    
                    {/* --- PANEL LATERAL: CARGA MASIVA --- */}
                    <div className="lg:col-span-1 space-y-8">
                        <section className={`${baseCardClass} p-8`} style={{ animationDelay: '100ms' }}>
                            <div className="flex items-center gap-3 mb-6">
                                <Upload className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                                <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                                    Carga Masiva_
                                </h2>
                            </div>
                            
                            <form onSubmit={handleUpload} className="space-y-6">
                                <label 
                                    className="border-[3px] border-dashed theme-border rounded-[2rem] p-10 flex flex-col items-center justify-center text-center space-y-4 transition-all cursor-pointer group w-full block theme-element hover:shadow-md"
                                    onDragOver={(e) => { e.preventDefault(); setDragActive(true); }}
                                    onDragLeave={() => setDragActive(false)}
                                    onDrop={(e) => {
                                        e.preventDefault();
                                        setDragActive(false);
                                        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                                            formCarga.setData('archivo', e.dataTransfer.files[0]);
                                        }
                                    }}
                                    style={{ borderColor: dragActive ? 'var(--color-primario)' : '' }}
                                >
                                    <div className="w-16 h-16 theme-surface border theme-border rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform shadow-sm">
                                        <FileSpreadsheet className="w-8 h-8" style={{ color: formCarga.data.archivo ? 'var(--color-primario)' : 'var(--theme-text-muted)' }} />
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
                                    <p className="text-red-500 text-[10px] font-bold mt-2 uppercase tracking-widest text-center">{formCarga.errors.archivo}</p>
                                )}

                                {formCarga.data.archivo && (
                                    <div className="flex items-center gap-3 p-4 theme-surface border border-emerald-500/30 rounded-2xl shadow-sm">
                                        <CheckCircle className="w-5 h-5 text-emerald-500 shrink-0" />
                                        <span className="text-[10px] font-bold theme-text-main truncate">{formCarga.data.archivo.name}</span>
                                    </div>
                                )}

                                <button 
                                    disabled={formCarga.processing || !formCarga.data.archivo}
                                    className="w-full py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] transition-all hover:scale-105 shadow-xl disabled:opacity-50 disabled:scale-100 outline-none flex justify-center items-center gap-2"
                                    style={{ backgroundColor: 'var(--color-primario)' }}
                                >
                                    <Database className="w-4 h-4" /> {formCarga.processing ? 'Sincronizando...' : 'Actualizar BD_'}
                                </button>
                            </form>

                            <div className="mt-8 pt-6 border-t theme-border space-y-4">
                                <div className="flex items-center gap-2 text-amber-500">
                                    <Database className="w-4 h-4" />
                                    <p className="text-[9px] font-black uppercase tracking-widest italic">Cabeceras Soportadas_</p>
                                </div>
                                <p className="text-[10px] theme-text-muted font-bold leading-relaxed">
                                    El sistema detecta automáticamente los campos. Puedes enviar un archivo solo con las columnas necesarias. <br/><br/>
                                    <strong style={{ color: 'var(--color-primario)' }}>numero_cliente</strong> (Requerido)<br/>
                                    <strong style={{ color: 'var(--color-primario)' }}>nombre</strong><br/>
                                    <strong style={{ color: 'var(--color-primario)' }}>codigo_lista</strong> (Ej: PG, 1, 2, 3, 4, 7)<br/>
                                    <strong style={{ color: 'var(--color-primario)' }}>monto_venta_actual</strong><br/>
                                    <strong style={{ color: 'var(--color-primario)' }}>vendedor_id</strong> (TAG de la Vendedora)<br/>
                                    <strong style={{ color: 'var(--color-primario)' }}>es_heredado</strong> (SI o NO)
                                </p>
                            </div>
                        </section>
                    </div>

                    {/* --- PANEL PRINCIPAL: LISTADO --- */}
                    <div className="lg:col-span-2 space-y-8">
                        <section className={`${baseCardClass} p-8 space-y-8`} style={{ animationDelay: '200ms' }}>
                            <div className="grid grid-cols-1 md:grid-cols-12 gap-4">
                                <div className="md:col-span-6 relative">
                                    <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                    <input 
                                        type="text" 
                                        placeholder="Buscar por número o nombre..." 
                                        value={busqueda}
                                        onChange={e => setBusqueda(e.target.value)}
                                        className="w-full px-12 py-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md theme-placeholder"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                        onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} 
                                        onBlur={e => e.target.style.borderColor = ''}
                                    />
                                </div>

                                <div className="md:col-span-3 relative">
                                    <select 
                                        value={filtroLista}
                                        onChange={e => setFiltroLista(e.target.value)}
                                        className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                        onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} 
                                        onBlur={e => e.target.style.borderColor = ''}
                                    >
                                        {listasUnicas.map(lista => (
                                            <option key={lista} value={lista}>{lista}</option>
                                        ))}
                                    </select>
                                    <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                        <ChevronDown className="w-4 h-4 theme-text-muted" />
                                    </div>
                                </div>

                                <div className="md:col-span-3 relative">
                                    <select 
                                        value={filtroTipo}
                                        onChange={e => setFiltroTipo(e.target.value)}
                                        className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                        onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} 
                                        onBlur={e => e.target.style.borderColor = ''}
                                    >
                                        <option value="Todos">TODOS</option>
                                        <option value="Directos">DIRECTOS</option>
                                        <option value="Heredados">HEREDADOS</option>
                                    </select>
                                    <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                        <ChevronDown className="w-4 h-4 theme-text-muted" />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4">
                                {clientesFiltrados.length === 0 ? (
                                    <div className="text-center py-16 theme-element border-2 border-dashed theme-border rounded-[2rem]">
                                        <Users className="w-12 h-12 theme-text-muted mx-auto mb-4 opacity-50" />
                                        <h3 className="text-lg font-black italic uppercase theme-text-main">Sin resultados</h3>
                                        <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted mt-2">No se encontraron coincidencias.</p>
                                    </div>
                                ) : (
                                    clientesPaginados.map((cliente) => (
                                        <div key={cliente.id} className="theme-element border theme-border p-5 rounded-2xl flex flex-col md:flex-row items-center justify-between gap-6 transition-all duration-150 hover:ring-2 hover:ring-[var(--color-primario)]/40 hover:shadow-md group">
                                            
                                            <div className="flex items-center gap-4 w-full md:w-auto">
                                                <div className="w-14 h-14 theme-surface border theme-border rounded-2xl flex items-center justify-center font-black italic theme-text-main text-[10px] transition-transform group-hover:scale-110 shadow-sm shrink-0">
                                                    {cliente.numero_cliente}
                                                </div>
                                                
                                                <div>
                                                    <h3 className="text-[15px] font-black theme-text-main leading-tight uppercase truncate max-w-[200px] md:max-w-[280px]">
                                                        {cliente.nombre}
                                                    </h3>
                                                    <div className="flex flex-wrap items-center gap-2 mt-2">
                                                        {renderBadgeLista(cliente.lista_descuento?.nombre)}
                                                        <span className="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                                            <TrendingUp className="w-3 h-3 text-emerald-500" /> 
                                                            {new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(cliente.monto_venta_actual)}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="flex items-center justify-between md:justify-end gap-4 w-full md:w-auto mt-2 md:mt-0">
                                                {cliente.es_heredado ? (
                                                    <div className="text-right border-r theme-border pr-4">
                                                        <p className="text-[8px] font-black text-amber-500 uppercase tracking-widest">Protegido_</p>
                                                        <p className="text-[10px] font-black text-amber-600 dark:text-amber-400 uppercase italic">Heredado</p>
                                                    </div>
                                                ) : (
                                                    <div className="text-right border-r theme-border pr-4">
                                                        <p className="text-[8px] font-black theme-text-muted uppercase tracking-widest">Asignación_</p>
                                                        <p className="text-[10px] font-black theme-text-main uppercase italic truncate max-w-[100px]">
                                                            {cliente.vendedor ? cliente.vendedor.name : 'Sin Asignar'}
                                                        </p>
                                                    </div>
                                                )}

                                                <button 
                                                    onClick={() => abrirModal('editar', cliente)}
                                                    className="p-3 theme-surface rounded-xl transition-all shadow-sm hover:shadow-md group-hover:scale-110 outline-none"
                                                    style={{ color: 'var(--color-primario)' }}
                                                    title="Editar cliente"
                                                >
                                                    <Edit3 className="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                    ))
                                )}

                                {totalPaginas > 1 && (
                                    <div className="flex flex-col md:flex-row items-center justify-between pt-8 mt-4 border-t theme-border gap-4">
                                        <span className="text-[10px] font-black theme-text-muted uppercase tracking-widest">
                                            Viendo {indicePrimerItem + 1} al {Math.min(indiceUltimoItem, clientesFiltrados.length)} de {clientesFiltrados.length}
                                        </span>
                                        
                                        <div className="flex items-center gap-2">
                                            <button 
                                                onClick={() => setPaginaActual(p => Math.max(1, p - 1))}
                                                disabled={paginaActual === 1}
                                                className="p-3 theme-element border theme-border rounded-xl disabled:opacity-50 transition-all hover:shadow-md outline-none"
                                                style={{ color: paginaActual === 1 ? 'var(--theme-text-muted)' : 'var(--color-primario)' }}
                                            >
                                                <ChevronLeft className="w-4 h-4" />
                                            </button>
                                            
                                            <div className="flex items-center gap-1">
                                                {Array.from({ length: totalPaginas }, (_, i) => i + 1)
                                                    .filter(num => num === 1 || num === totalPaginas || Math.abs(paginaActual - num) <= 1)
                                                    .map((num, i, arr) => (
                                                        <React.Fragment key={num}>
                                                            {i > 0 && arr[i - 1] !== num - 1 && (
                                                                <span className="text-xs theme-text-muted px-1 font-bold">...</span>
                                                            )}
                                                            <button 
                                                                onClick={() => setPaginaActual(num)}
                                                                className={`w-10 h-10 rounded-xl text-xs font-black transition-all outline-none shadow-sm ${
                                                                    paginaActual === num 
                                                                    ? 'text-white' 
                                                                    : 'theme-surface border theme-border theme-text-muted hover:scale-105'
                                                                }`}
                                                                style={{ backgroundColor: paginaActual === num ? 'var(--color-primario)' : '' }}
                                                            >
                                                                {num}
                                                            </button>
                                                        </React.Fragment>
                                                    ))
                                                }
                                            </div>

                                            <button 
                                                onClick={() => setPaginaActual(p => Math.min(totalPaginas, p + 1))}
                                                disabled={paginaActual === totalPaginas}
                                                className="p-3 theme-element border theme-border rounded-xl disabled:opacity-50 transition-all hover:shadow-md outline-none"
                                                style={{ color: paginaActual === totalPaginas ? 'var(--theme-text-muted)' : 'var(--color-primario)' }}
                                            >
                                                <ChevronRight className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}