import React, { useState, useEffect, useCallback } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import {
    Users, Upload, Search,
    FileSpreadsheet, TrendingUp,
    CheckCircle, Database, Edit3, ChevronDown, Sparkles,
    Plus, Shield, X, ChevronRight,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import GeliaLoader from '../../Components/GeliaLoader';

// --- IMPORTACIÓN DEL PARCIAL ---
import ModalFormCliente from './Partials/ModalFormCliente';
import ModalConfiguracionEspecial from './Partials/ModalConfiguracionEspecial';

import { geliaCardClass, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../utils/geliaTheme';

const ModalReporteImportacion = ({ reporte, onClose }) => {
    return (
        <div className={`${THEME_MODAL_OVERLAY} z-[100]`} onClick={onClose}>
            <div className={`${THEME_MODAL_SHELL} max-w-3xl modal-pop p-6 md:p-8 flex flex-col max-h-[90vh]`} onClick={e => e.stopPropagation()}>
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h3 className="text-xl font-black italic uppercase theme-text-main">
                            REPORTE DE <span style={{ color: 'var(--color-primario)' }}>ASCENSOS</span>
                        </h3>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mt-1">
                            {reporte.length} clientes promovidos a una lista superior_
                        </p>
                    </div>
                    <button onClick={onClose} className="p-2 rounded-full theme-element theme-text-muted hover:theme-text-main transition-colors outline-none">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="overflow-y-auto custom-scrollbar pr-2 flex-1 space-y-3">
                    {reporte.map((item, i) => (
                        <div key={i} className="theme-element border theme-border p-4 rounded-xl flex flex-col md:flex-row md:items-center justify-between gap-4 transition-all hover:shadow-md">
                            <div>
                                <p className="text-xs font-black uppercase theme-text-main leading-tight">{item.nombre}</p>
                                <p className="text-[10px] font-bold theme-text-muted mt-0.5">{item.numero_cliente}</p>
                            </div>
                            <div className="flex items-center gap-3 shrink-0">
                                <span className="text-[9px] font-black uppercase tracking-widest bg-slate-500/10 text-slate-500 px-3 py-1.5 rounded-lg border border-slate-500/20">{item.lista_anterior}</span>
                                <ChevronRight className="w-4 h-4 theme-text-muted" />
                                <span className="text-[9px] font-black uppercase tracking-widest bg-emerald-500/10 text-emerald-500 px-3 py-1.5 rounded-lg border border-emerald-500/20">{item.lista_nueva}</span>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="pt-6 mt-4 border-t theme-border flex justify-end">
                    <button onClick={onClose} className="px-8 py-3 text-white rounded-xl font-black uppercase tracking-widest text-[11px] shadow-lg transition-all hover:scale-105" style={{ backgroundColor: 'var(--color-primario)' }}>
                        Confirmar y Cerrar
                    </button>
                </div>
            </div>
        </div>
    );
};

export default function Clientes({ auth, clientes, vendedores = [], tipos_cliente = [], listas = [], filtros = {} }) {

    // --- SECCIÓN: ESTADOS GLOBALES ---
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [filtroListaId, setFiltroListaId] = useState(filtros.lista_id ? String(filtros.lista_id) : '');
    const [filtroTipo, setFiltroTipo] = useState(filtros.tipo || '');
    const [filtroEstado, setFiltroEstado] = useState(filtros.estado || '');
    const [filtroOrden, setFiltroOrden] = useState(filtros.orden || 'numero_asc');
    const [dragActive, setDragActive] = useState(false);
    const [cargandoLista, setCargandoLista] = useState(false);
    
    const [modalExitoAbierto, setModalExitoAbierto] = useState(false);

    // Control del Modal unificado
    const [modalConfig, setModalConfig] = useState({ abierto: false, modo: null, cliente: null });
    const [panelProteccionAbierto, setPanelProteccionAbierto] = useState(false);

    const formCarga = useForm({
        archivo: null,
    });

    useEffect(() => {
        setBusqueda(filtros.q || '');
        setFiltroListaId(filtros.lista_id ? String(filtros.lista_id) : '');
        setFiltroTipo(filtros.tipo || '');
        setFiltroEstado(filtros.estado || '');
        setFiltroOrden(filtros.orden || 'numero_asc');
    }, [filtros.q, filtros.lista_id, filtros.tipo, filtros.estado, filtros.orden]);

    const paramsFiltros = useCallback(() => ({
        q: busqueda.trim() || undefined,
        lista_id: filtroListaId || undefined,
        tipo: filtroTipo || undefined,
        estado: filtroEstado || undefined,
        orden: filtroOrden || 'numero_asc',
    }), [busqueda, filtroListaId, filtroTipo, filtroEstado, filtroOrden]);

    const recargarClientes = useCallback((extra = {}) => {
        router.get(route('admin.clientes'), { ...paramsFiltros(), ...extra }, {
            only: ['clientes', 'filtros'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            showProgress: false,
            onStart: () => setCargandoLista(true),
            onFinish: () => setCargandoLista(false),
        });
    }, [paramsFiltros]);

    useEffect(() => {
        const t = setTimeout(() => {
            const qActual = filtros.q || '';
            const listaActual = filtros.lista_id ? String(filtros.lista_id) : '';
            const tipoActual = filtros.tipo || '';
            const estadoActual = filtros.estado || '';
            const ordenActual = filtros.orden || 'numero_asc';
            if (
                busqueda !== qActual
                || filtroListaId !== listaActual
                || filtroTipo !== tipoActual
                || filtroEstado !== estadoActual
                || filtroOrden !== ordenActual
            ) {
                recargarClientes({ page: 1 });
            }
        }, 400);
        return () => clearTimeout(t);
    }, [busqueda, filtroListaId, filtroTipo, filtroEstado, filtroOrden, filtros, recargarClientes]);

    const irAPagina = (pagina) => {
        if (pagina < 1 || pagina > (clientes?.last_page || 1)) return;
        recargarClientes({ page: pagina });
    };

    // --- SECCIÓN: MANEJO DE ACCIONES ---
    const handleUpload = (e) => {
        e.preventDefault();
        if (formCarga.processing) return;
        formCarga.post(route('admin.clientes.importar'), {
            preserveScroll: true,
            onSuccess: () => {
                formCarga.reset();
                router.reload({ only: ['clientes'] });
                setModalExitoAbierto(true);
            },
        });
    };

    const abrirModal = (modo, cliente = null) => {
        setModalConfig({ abierto: true, modo, cliente });
    };

    const cerrarModal = () => {
        setModalConfig({ abierto: false, modo: null, cliente: null });
    };

    const descargarPlantilla = () => {
        const cabeceras = [
            "numero_cliente",
            "nombre",
            "direccion_fiscal",
            "colonia_fiscal",
            "municipio_fiscal",
            "codigo_postal",
            "estado_fiscal",
            "pais_fiscal",
            "direccion_contacto",
            "colonia_contacto",
            "municipio_contacto",
            "estado_contacto",
            "pais_contacto",
            "cp_contacto",
            "rfc",
            "telefono",
            "correo_electronico",
            "monto_credito_autorizado",
            "monto_venta_actual",
            "dias_cheque_postfechado",
            "dias_credito",
            "parte_relacional",
            "regimen_fiscal",
            "uso_factura",
            "codigo_lista",
            "variable_contable",
            "vendedor_id",
            "nombre_razon_social",
            "es_heredado"
        ];
        
        const csvContent = "data:text/csv;charset=utf-8,\uFEFF" + cabeceras.join(",");
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "plantilla_clientes.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    // Extraemos la sesión flash usando el hook de Inertia
    const { flash } = usePage().props;
    const [reporteModal, setReporteModal] = useState(null);

    // Efecto para detectar cuando llega un nuevo reporte desde el backend
    useEffect(() => {
        if (flash?.reporte_importacion && flash.reporte_importacion.length > 0) {
            setReporteModal(flash.reporte_importacion);
        }
    }, [flash]);

    // --- SECCIÓN: RENDERIZADORES DE UI ---
    const renderBadgeLista = (nombreLista) => {
        if (!nombreLista) return <span className="px-2 py-1 theme-element border theme-border text-zinc-500 text-[9px] font-black uppercase tracking-widest rounded-md">Sin Lista</span>;

        const nivel = nombreLista.toUpperCase().replace('MAYOREO ', '').trim();

        switch (nivel) {
            case 'BRONCE': return <span className="px-2 py-1 bg-[#cd7f32]/10 text-[#cd7f32] border border-[#cd7f32]/30 text-[9px] font-black uppercase tracking-widest rounded-md">Bronce</span>;
            case 'PLATA': return <span className="px-2 py-1 bg-slate-400/10 text-slate-500 border border-slate-400/30 text-[9px] font-black uppercase tracking-widest rounded-md">Plata</span>;
            case 'ORO': return <span className="px-2 py-1 bg-yellow-500/10 text-yellow-600 border border-yellow-500/30 text-[9px] font-black uppercase tracking-widest rounded-md">Oro</span>;
            case 'DIAMANTE':
                return (
                    <span className="relative flex items-center gap-1.5 px-2.5 py-1 bg-gradient-to-r from-cyan-500/10 to-blue-500/10 border border-cyan-400/40 text-[9px] font-black uppercase tracking-widest rounded-md overflow-hidden shadow-[0_0_8px_rgba(34,211,238,0.2)]">
                        <Sparkles className="w-3 h-3 text-cyan-600 dark:text-cyan-300" />
                        <span className="text-cyan-700 dark:text-cyan-300 drop-shadow-sm">Diamante</span>
                        <span className="absolute inset-0 w-[150%] -translate-x-full bg-gradient-to-r from-transparent via-cyan-100/60 dark:via-white/20 to-transparent skew-x-12 animate-[shimmer_3s_infinite_ease-in-out]"></span>
                    </span>
                );
            case 'PUBLICO GENERAL': return <span className="px-2 py-1 theme-element theme-border border text-zinc-500 text-[9px] font-black uppercase tracking-widest rounded-md">Público Gral.</span>;
            case 'COLABORADORES': return <span className="px-2 py-1 bg-purple-500/10 text-purple-600 border border-purple-500/30 text-[9px] font-black uppercase tracking-widest rounded-md">Colaborador</span>;
            default: return <span className="px-2 py-1 bg-blue-500/10 text-blue-500 border border-blue-500/30 text-[9px] font-black uppercase tracking-widest rounded-md">{nivel}</span>;
        }
    };

    const listaClientes = clientes?.data || [];
    const activeCardClass = geliaCardClass('relative z-10');

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de Clientes | GELIANV" />

            {/* Renderizado condicional del modal extraído */}
            {modalConfig.abierto && (
                <ModalFormCliente
                    onClose={cerrarModal}
                    modoModal={modalConfig.modo}
                    clienteActual={modalConfig.cliente}
                    tiposCliente={tipos_cliente}
                    vendedores={vendedores}
                    listas={listas}
                />
            )}

            {/* --- PANEL DE PROTECCION --- */}
            {panelProteccionAbierto && (
                <ModalConfiguracionEspecial 
                    onClose={() => setPanelProteccionAbierto(false)} 
                />
            )}

            {/* Renderizado del Modal de Reporte de Importación */}
            {reporteModal && (
                <ModalReporteImportacion 
                    reporte={reporteModal} 
                    onClose={() => setReporteModal(null)} 
                />
            )}

            <GeliaLoader
                isVisible={modalExitoAbierto}
                message="¡Carga Exitosa!"
                progress={null}
                onClose={() => setModalExitoAbierto(false)}
            />

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">

                {/* --- HEADER --- */}
                <header className={`${activeCardClass} p-8 md:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-6`} style={{ animationDelay: '0ms' }}>
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

                    {/* --- BOTONES DE ACCION --- */}
                    <div className="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                        <button
                            onClick={() => setPanelProteccionAbierto(true)}
                            className="py-4 px-6 theme-element border theme-border theme-text-main rounded-2xl font-black uppercase tracking-widest text-[11px] transition-all hover:shadow-md outline-none flex justify-center items-center gap-2 group"
                        >
                            <Shield className="w-5 h-5 group-hover:scale-110 transition-transform" style={{ color: 'var(--color-primario)' }} /> 
                            Protección_
                        </button>

                        <button
                            onClick={() => abrirModal('crear')}
                            className="py-4 px-8 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] transition-all hover:scale-105 shadow-xl outline-none flex justify-center items-center gap-2"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            <Plus className="w-5 h-5" /> Nuevo Cliente_
                        </button>
                    </div>
                </header>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch h-[calc(100vh-240px)] min-h-[600px]">

                    {/* --- PANEL LATERAL: CARGA MASIVA --- */}
                    <div className="lg:col-span-1 h-full">
                        <section className={`${activeCardClass} p-8 h-full overflow-y-auto custom-scrollbar`} style={{ animationDelay: '100ms' }}>
                            <div className="flex items-center justify-between gap-3 mb-6 shrink-0">
                                <div className="flex items-center gap-3">
                                    <Upload className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                                    <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                                        Carga Masiva_
                                    </h2>
                                </div>
                                <button 
                                    type="button"
                                    onClick={descargarPlantilla}
                                    className="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest theme-text-main theme-element border theme-border rounded-lg hover:shadow-md transition-all flex items-center gap-1.5"
                                >
                                    <FileSpreadsheet className="w-3 h-3" />
                                    Plantilla
                                </button>
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
                                    type="submit"
                                    disabled={formCarga.processing || !formCarga.data.archivo}
                                    className="w-full py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] transition-all hover:scale-105 shadow-xl disabled:opacity-50 disabled:scale-100 outline-none flex justify-center items-center gap-2"
                                    style={{ backgroundColor: 'var(--color-primario)' }}
                                >
                                    <Database className="w-4 h-4" /> {formCarga.processing ? 'Sincronizando...' : 'Actualizar BD_'}
                                </button>
                                {formCarga.processing && (
                                    <p className="text-[9px] font-black uppercase tracking-widest text-amber-600 text-center">
                                        No cierre esta pestaña hasta que termine la sincronización.
                                    </p>
                                )}
                            </form>

                            <div className="mt-8 pt-6 border-t theme-border space-y-4">
                                <div className="flex items-center gap-2 text-amber-500">
                                    <Database className="w-4 h-4" />
                                    <p className="text-[9px] font-black uppercase tracking-widest italic">Cabeceras Soportadas_</p>
                                </div>
                                <p className="text-[10px] theme-text-muted font-bold leading-relaxed">
                                    El sistema detecta automáticamente los campos. Puedes enviar un archivo solo con las columnas necesarias. <br /><br />
                                    <strong style={{ color: 'var(--color-primario)' }}>numero_cliente</strong> (Requerido)<br />
                                    <strong style={{ color: 'var(--color-primario)' }}>nombre</strong><br />
                                    <strong style={{ color: 'var(--color-primario)' }}>codigo_lista</strong> (Ej: PG, 1, 2, 3, 4, 7; celda vacía = inactivo; sin columna = solo actualiza monto)<br />
                                    <strong style={{ color: 'var(--color-primario)' }}>monto_venta_actual</strong><br />
                                    <strong style={{ color: 'var(--color-primario)' }}>vendedor_id</strong> (TAG de la Vendedora)<br />
                                    <strong style={{ color: 'var(--color-primario)' }}>es_heredado</strong> (SI o NO)<br />
                                    <strong style={{ color: 'var(--color-primario)' }}>limite_asignado</strong> o <strong style={{ color: 'var(--color-primario)' }}>monto_credito_autorizado</strong> (Sin símbolos, ej: 5000)<br />
                                    <strong style={{ color: 'var(--color-primario)' }}>dias_credito</strong> o <strong style={{ color: 'var(--color-primario)' }}>dias_de_credito</strong> (Número entero, ej: 15)<br /><br />
                                    <span className="text-[9px] font-black uppercase tracking-widest text-amber-600">
                                        Ejecute la carga antes de las 09:00 para evitar conflicto con el rechazo automático de pagos vencidos.
                                    </span><br /><br />
                                    <span className="text-[9px] font-black uppercase tracking-widest text-blue-500">Datos fiscales:</span><br />
                                    <strong style={{ color: 'var(--color-primario)' }}>rfc</strong>, <strong style={{ color: 'var(--color-primario)' }}>codigo_postal</strong>, <strong style={{ color: 'var(--color-primario)' }}>regimen_fiscal</strong>, <strong style={{ color: 'var(--color-primario)' }}>correo_electronico</strong>, <strong style={{ color: 'var(--color-primario)' }}>uso_factura</strong>, <strong style={{ color: 'var(--color-primario)' }}>nombre_razon_social</strong>, <strong style={{ color: 'var(--color-primario)' }}>direccion_fiscal</strong>, <strong style={{ color: 'var(--color-primario)' }}>colonia_fiscal</strong>, <strong style={{ color: 'var(--color-primario)' }}>municipio_fiscal</strong>, <strong style={{ color: 'var(--color-primario)' }}>estado_fiscal</strong>, <strong style={{ color: 'var(--color-primario)' }}>pais_fiscal</strong><br /><br />
                                    <span className="text-[9px] font-black uppercase tracking-widest text-orange-500">Datos de contacto y control:</span><br />
                                    <strong style={{ color: 'var(--color-primario)' }}>direccion_contacto</strong>, <strong style={{ color: 'var(--color-primario)' }}>colonia_contacto</strong>, <strong style={{ color: 'var(--color-primario)' }}>municipio_contacto</strong>, <strong style={{ color: 'var(--color-primario)' }}>estado_contacto</strong>, <strong style={{ color: 'var(--color-primario)' }}>pais_contacto</strong>, <strong style={{ color: 'var(--color-primario)' }}>cp_contacto</strong>, <strong style={{ color: 'var(--color-primario)' }}>telefono</strong><br />
                                    <strong style={{ color: 'var(--color-primario)' }}>dias_cheque_postfechado</strong>, <strong style={{ color: 'var(--color-primario)' }}>parte_relacional</strong>, <strong style={{ color: 'var(--color-primario)' }}>variable_contable</strong>
                                </p>
                            </div>
                        </section>
                    </div>

                    {/* --- PANEL PRINCIPAL: LISTADO --- */}
                    <div className="lg:col-span-2 h-full min-h-0">
                        <section className={`${activeCardClass} p-8 h-full flex flex-col`} style={{ animationDelay: '200ms' }}>
                            <div className="grid grid-cols-1 md:grid-cols-12 gap-4 shrink-0 mb-4">
                                <div className="md:col-span-12 relative">
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
                                        value={filtroListaId}
                                        onChange={e => setFiltroListaId(e.target.value)}
                                        className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                        onFocus={e => e.target.style.borderColor = 'var(--color-primario)'}
                                        onBlur={e => e.target.style.borderColor = ''}
                                    >
                                        <option value="">Todas las listas</option>
                                        {listas.map(lista => (
                                            <option key={lista.id} value={String(lista.id)}>{lista.nombre}</option>
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
                                        <option value="">TODOS</option>
                                        <option value="directos">DIRECTOS</option>
                                        <option value="heredados">HEREDADOS</option>
                                    </select>
                                    <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                        <ChevronDown className="w-4 h-4 theme-text-muted" />
                                    </div>
                                </div>

                                <div className="md:col-span-3 relative">
                                    <select
                                        value={filtroEstado}
                                        onChange={e => setFiltroEstado(e.target.value)}
                                        className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    >
                                        <option value="">ESTADO: TODOS</option>
                                        <option value="activos">ACTIVOS</option>
                                        <option value="inactivos">INACTIVOS</option>
                                    </select>
                                    <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                        <ChevronDown className="w-4 h-4 theme-text-muted" />
                                    </div>
                                </div>

                                <div className="md:col-span-3 relative">
                                    <select
                                        value={filtroOrden}
                                        onChange={e => {
                                            setFiltroOrden(e.target.value);
                                            recargarClientes({ page: 1, orden: e.target.value });
                                        }}
                                        className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    >
                                        <option value="numero_asc">NÚMERO (MENOR A MAYOR)</option>
                                        <option value="numero_desc">NÚMERO (MAYOR A MENOR)</option>
                                        <option value="monto_desc">MAYOR MONTO</option>
                                        <option value="monto_asc">MENOR MONTO</option>
                                    </select>
                                    <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                        <ChevronDown className="w-4 h-4 theme-text-muted" />
                                    </div>
                                </div>
                            </div>

                            {(clientes?.total ?? 0) > 0 && (
                                <div className="pt-2 pb-4 shrink-0">
                                    <GeliaPaginacion
                                        paginator={clientes}
                                        onIrAPagina={irAPagina}
                                        embedded
                                    />
                                </div>
                            )}

                            <div className={`space-y-4 flex-1 overflow-y-auto custom-scrollbar pr-3 pb-4 ${cargandoLista ? 'opacity-60 pointer-events-none' : ''}`}>
                                {listaClientes.length === 0 ? (
                                    <div className="text-center py-16 theme-element border-2 border-dashed theme-border rounded-[2rem]">
                                        <Users className="w-12 h-12 theme-text-muted mx-auto mb-4 opacity-50" />
                                        <h3 className="text-lg font-black italic uppercase theme-text-main">Sin resultados</h3>
                                        <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted mt-2">No se encontraron coincidencias.</p>
                                    </div>
                                ) : (
                                    listaClientes.map((cliente) => (
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
                                                        {(cliente.es_inactivo === true || cliente.es_inactivo === 1) && (
                                                            <span className="px-2 py-1 bg-amber-500/10 text-amber-600 border border-amber-500/30 text-[9px] font-black uppercase tracking-widest rounded-md">
                                                                Inactivo
                                                            </span>
                                                        )}
                                                        <span className="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                                            <TrendingUp className="w-3 h-3 text-emerald-500" />
                                                            {new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(cliente.monto_venta_actual)}
                                                        </span>
                                                    </div>
                                                    {cliente.rfc || cliente.correo_electronico || cliente.nombre_razon_social ? (
                                                        <p className="text-[9px] font-bold theme-text-muted mt-1.5 truncate max-w-[280px]">
                                                            {[cliente.rfc, cliente.codigo_postal, cliente.correo_electronico].filter(Boolean).join(' · ')}
                                                        </p>
                                                    ) : (
                                                        <span className="inline-block mt-1.5 text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded-md bg-slate-500/10 text-slate-500 border border-slate-500/20">
                                                            Sin datos fiscales
                                                        </span>
                                                    )}
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

                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}