import React, { useState, useEffect, useCallback } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import {
    Users, Upload, Search,
    FileSpreadsheet, TrendingUp,
    CheckCircle, Database, Edit3, ChevronDown,
    Plus, Shield, X, ChevronRight, History, MapPin,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import GeliaLoader from '../../Components/GeliaLoader';

// --- IMPORTACIÓN DEL PARCIAL ---
import ModalFormCliente from './Partials/ModalFormCliente';
import useClienteDialog from './Partials/useClienteDialog';
import ModalConfiguracionEspecial from './Partials/ModalConfiguracionEspecial';
import TabAuditoriaClientes from './Partials/TabAuditoriaClientes';

import BadgeListaDescuento from '../../Components/BadgeListaDescuento';
import { geliaCardClass, GELIA_PAGE_SHELL, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../utils/geliaTheme';

const formatoMoneda = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

const ModalReporteImportacion = ({ reporte, onClose }) => {
    const dialogRef = useClienteDialog(onClose);
    return (
        <div className={`${THEME_MODAL_OVERLAY} z-[100]`} onClick={onClose}>
            <div ref={dialogRef} role="dialog" aria-modal="true" aria-labelledby="clientes-reporte-title" tabIndex={-1} className={`${THEME_MODAL_SHELL} clientes-report max-w-3xl modal-pop p-4 sm:p-6 md:p-8 flex flex-col max-h-[90dvh]`} onClick={e => e.stopPropagation()}>
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h2 id="clientes-reporte-title" className="text-xl font-black italic uppercase theme-text-main">
                            REPORTE DE <span style={{ color: 'var(--color-primario)' }}>ASCENSOS</span>
                        </h2>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mt-1">
                            {reporte.length} clientes promovidos a una lista superior_
                        </p>
                    </div>
                    <button onClick={onClose} aria-label="Cerrar reporte de importación" className="p-2 rounded-full theme-element theme-text-muted hover:theme-text-main transition-colors outline-none">
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

export default function Clientes({ auth, clientes, vendedores = [], tipos_cliente = [], listas = [], filtros = {}, puedeDescargarImportaciones = false }) {

    const tabActivo = filtros.tab === 'auditoria' ? 'auditoria' : 'clientes';

    // --- SECCIÓN: ESTADOS GLOBALES ---
    const [busquedaInput, setBusquedaInput] = useState(filtros.q || '');
    const [busquedaAplicada, setBusquedaAplicada] = useState(filtros.q || '');
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

    const paramsFiltros = useCallback((qOverride) => ({
        q: (qOverride !== undefined ? qOverride : busquedaAplicada).trim() || undefined,
        lista_id: filtroListaId || undefined,
        tipo: filtroTipo || undefined,
        estado: filtroEstado || undefined,
        orden: filtroOrden || 'numero_asc',
        tab: tabActivo === 'auditoria' ? 'auditoria' : undefined,
    }), [busquedaAplicada, filtroListaId, filtroTipo, filtroEstado, filtroOrden, tabActivo]);

    const recargarClientes = useCallback((extra = {}) => {
        const qFromExtra = Object.prototype.hasOwnProperty.call(extra, 'q') ? extra.q : undefined;
        const merged = { ...paramsFiltros(qFromExtra), ...extra };
        if (Object.prototype.hasOwnProperty.call(extra, 'q')) {
            merged.q = extra.q?.trim() || undefined;
        }
        router.get(route('admin.clientes'), merged, {
            only: ['clientes', 'filtros'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            showProgress: false,
            onStart: () => setCargandoLista(true),
            onFinish: () => setCargandoLista(false),
        });
    }, [paramsFiltros]);

    const aplicarFiltroDropdown = (campo, valor) => {
        const params = {
            page: 1,
            q: busquedaAplicada.trim() || undefined,
            lista_id: filtroListaId || undefined,
            tipo: filtroTipo || undefined,
            estado: filtroEstado || undefined,
            orden: filtroOrden || 'numero_asc',
            tab: tabActivo === 'auditoria' ? 'auditoria' : undefined,
            [campo]: valor || undefined,
        };
        router.get(route('admin.clientes'), params, {
            only: ['clientes', 'filtros'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            showProgress: false,
            onStart: () => setCargandoLista(true),
            onFinish: () => setCargandoLista(false),
        });
    };

    const ejecutarBusqueda = () => {
        const q = busquedaInput.trim();
        setBusquedaAplicada(q);
        recargarClientes({ page: 1, q });
    };

    const cambiarTab = (tab) => {
        router.get(route('admin.clientes'), {
            tab: tab === 'auditoria' ? 'auditoria' : undefined,
            q: busquedaAplicada.trim() || undefined,
            lista_id: filtroListaId || undefined,
            tipo: filtroTipo || undefined,
            estado: filtroEstado || undefined,
            orden: filtroOrden || 'numero_asc',
        }, {
            preserveState: true,
            preserveScroll: false,
            replace: true,
        });
    };

    useEffect(() => {
        setBusquedaInput(filtros.q || '');
        setBusquedaAplicada(filtros.q || '');
        setFiltroListaId(filtros.lista_id ? String(filtros.lista_id) : '');
        setFiltroTipo(filtros.tipo || '');
        setFiltroEstado(filtros.estado || '');
        setFiltroOrden(filtros.orden || 'numero_asc');
    }, [filtros.q, filtros.lista_id, filtros.tipo, filtros.estado, filtros.orden]);

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

            <div className={`${GELIA_PAGE_SHELL} clientes-page max-w-[1400px] w-full mx-auto space-y-5 md:space-y-6 relative`}>

                {/* --- HEADER --- */}
                <header className={`${activeCardClass} clientes-header p-5 sm:p-8 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-5`} style={{ animationDelay: '0ms' }}>
                    <div className="min-w-0 flex flex-col gap-3">
                        <div className="flex items-center justify-start mb-2">
                            <div className="w-8 h-1.5 rounded-full mr-3" style={{ backgroundColor: 'var(--color-primario)' }}></div>
                            <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted drop-shadow-sm">
                                Base de datos Wizerp
                            </span>
                        </div>
                        <h1 className="text-3xl sm:text-4xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0 p-0">
                            SISTEMA DE <span style={{ color: 'var(--color-primario)' }}>CLIENTES</span>
                        </h1>
                    </div>

                    {/* --- BOTONES DE ACCION --- */}
                    <div className="clientes-header-actions flex flex-wrap gap-3 w-full xl:w-auto">
                        <button
                            onClick={() => setPanelProteccionAbierto(true)}
                            className="py-4 px-6 theme-element border theme-border theme-text-main rounded-2xl font-black uppercase tracking-widest text-[11px] transition-all hover:shadow-md outline-none flex justify-center items-center gap-2 group"
                        >
                            <Shield className="w-5 h-5 group-hover:scale-110 transition-transform" style={{ color: 'var(--color-primario)' }} /> 
                            Protección
                        </button>

                        <button
                            onClick={() => abrirModal('crear')}
                            className="py-4 px-8 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] transition-all hover:scale-105 shadow-xl outline-none flex justify-center items-center gap-2"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            <Plus className="w-5 h-5" /> Nuevo cliente
                        </button>
                    </div>
                </header>

                {/* Pestañas Clientes / Auditoría */}
                <div className="clientes-tabs flex gap-2">
                    <button
                        type="button"
                        onClick={() => cambiarTab('clientes')}
                        aria-pressed={tabActivo === 'clientes'}
                        className={`px-6 py-3 rounded-xl font-black uppercase tracking-widest text-[10px] transition-all border ${tabActivo === 'clientes' ? 'text-white border-transparent shadow-lg' : 'theme-element theme-border theme-text-muted hover:theme-text-main'}`}
                        style={tabActivo === 'clientes' ? { backgroundColor: 'var(--color-primario)' } : {}}
                    >
                        <Users className="w-4 h-4 inline mr-2 -mt-0.5" />
                        Clientes
                    </button>
                    <button
                        type="button"
                        onClick={() => cambiarTab('auditoria')}
                        aria-pressed={tabActivo === 'auditoria'}
                        className={`px-6 py-3 rounded-xl font-black uppercase tracking-widest text-[10px] transition-all border ${tabActivo === 'auditoria' ? 'text-white border-transparent shadow-lg' : 'theme-element theme-border theme-text-muted hover:theme-text-main'}`}
                        style={tabActivo === 'auditoria' ? { backgroundColor: 'var(--color-primario)' } : {}}
                    >
                        <History className="w-4 h-4 inline mr-2 -mt-0.5" />
                        Auditoría
                    </button>
                </div>

                {tabActivo === 'auditoria' ? (
                    <TabAuditoriaClientes puedeDescargarImportaciones={puedeDescargarImportaciones} />
                ) : (
                <div className="clientes-workspace grid grid-cols-1 xl:grid-cols-[minmax(0,320px)_minmax(0,1fr)] gap-5 items-start">

                    {/* --- PANEL LATERAL: CARGA MASIVA --- */}
                    <div className="min-w-0 order-2 xl:order-1">
                        <section className={`${activeCardClass} clientes-import p-5 sm:p-6`} style={{ animationDelay: '100ms' }}>
                            <details>
                                <summary className="clientes-import-toggle flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-3">
                                        <Upload className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                                            Carga masiva
                                        </h2>
                                    </div>
                                    <ChevronDown className="clientes-import-chevron w-5 h-5 shrink-0 theme-text-muted" aria-hidden="true" />
                                </summary>
                                <div className="mt-5 flex justify-end">
                                    <button type="button" onClick={descargarPlantilla} className="inline-flex items-center gap-2 min-h-11 px-3 text-xs font-bold theme-text-main theme-element border theme-border rounded-xl hover:shadow-md transition-shadow">
                                        <FileSpreadsheet className="w-4 h-4" aria-hidden="true" /> Descargar plantilla
                                    </button>
                                </div>

                                <form onSubmit={handleUpload} className="space-y-4 mt-4">
                                    <label
                                        className="border-[3px] border-dashed theme-border rounded-2xl p-5 flex flex-col items-center justify-center text-center space-y-4 transition-all cursor-pointer group w-full block theme-element hover:shadow-md"
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
                                            <p className="text-xs font-black theme-text-main uppercase">Selecciona o arrastra un archivo</p>
                                            <p className="text-[10px] theme-text-muted italic mt-1 uppercase font-bold">Formatos: .csv</p>
                                        </div>

                                        <span className="text-[9px] font-black uppercase tracking-widest underline" style={{ color: 'var(--color-primario)' }}>
                                            O examinar archivos
                                        </span>

                                        <input
                                            type="file"
                                            className="sr-only"
                                            name="archivo"
                                            aria-label="Seleccionar archivo de clientes"
                                            accept=".csv,.txt"
                                            onChange={e => formCarga.setData('archivo', e.target.files[0])}
                                        />
                                    </label>

                                    {formCarga.errors.archivo && (
                                        <p className="text-red-500 text-[10px] font-bold mt-2 uppercase tracking-widest text-center">{formCarga.errors.archivo}</p>
                                    )}

                                    {formCarga.data.archivo && (
                                        <div className="min-w-0 flex items-center gap-3 p-4 theme-surface border border-emerald-500/30 rounded-2xl shadow-sm">
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
                                        <Database className="w-4 h-4" /> {formCarga.processing ? 'Sincronizando…' : 'Actualizar base de clientes'}
                                    </button>
                                    {formCarga.processing && (
                                        <p className="text-[9px] font-black uppercase tracking-widest text-amber-600 text-center">
                                            No cierre esta pestaña hasta que termine la sincronización.
                                        </p>
                                    )}
                                </form>

                                <div className="clientes-import-help mt-6 pt-5 border-t theme-border space-y-4">
                                    <div className="flex items-center gap-2 text-amber-500">
                                        <Database className="w-4 h-4" />
                                        <p className="text-[9px] font-black uppercase tracking-widest italic">Columnas admitidas</p>
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
                            </details>
                        </section>
                    </div>

                    {/* --- PANEL PRINCIPAL: LISTADO --- */}
                    <div className="min-w-0 order-1 xl:order-2">
                        <section className={`${activeCardClass} clientes-list-panel p-4 sm:p-6 flex flex-col`} style={{ animationDelay: '200ms' }}>
                            <div className="flex flex-wrap items-baseline justify-between gap-2 mb-5">
                                <h2 className="text-xl font-black theme-text-main m-0">Directorio de clientes</h2>
                                <span className="text-sm theme-text-muted tabular-nums">{(clientes?.total ?? 0).toLocaleString('es-MX')} clientes</span>
                            </div>
                            <div className="clientes-filters grid gap-3 shrink-0 mb-4">
                                <div className="clientes-search relative">
                                    <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                    <input
                                        type="text"
                                        id="clientes-busqueda"
                                        name="q"
                                        aria-label="Buscar clientes por número o nombre"
                                        autoComplete="off"
                                        placeholder="Número o nombre…"
                                        value={busquedaInput}
                                        onChange={e => setBusquedaInput(e.target.value)}
                                        onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); ejecutarBusqueda(); } }}
                                        className="w-full px-12 py-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md theme-placeholder"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                        onFocus={e => e.target.style.borderColor = 'var(--color-primario)'}
                                        onBlur={e => e.target.style.borderColor = ''}
                                    />
                                </div>
                                <div className="clientes-search-button">
                                    <button
                                        type="button"
                                        onClick={ejecutarBusqueda}
                                        className="w-full h-full py-4 text-white rounded-xl font-black uppercase tracking-widest text-[11px] shadow-lg transition-all hover:scale-[1.02] outline-none flex justify-center items-center gap-2"
                                        style={{ backgroundColor: 'var(--color-primario)' }}
                                    >
                                        <Search className="w-4 h-4" />
                                        Buscar
                                    </button>
                                </div>

                                <div className="min-w-0">
                                    <label htmlFor="clientes-filtro-lista" className="block text-xs font-semibold theme-text-muted mb-2">Lista</label>
                                    <div className="relative">
                                        <select id="clientes-filtro-lista" name="lista_id"
                                            aria-label="Lista de descuento"
                                            value={filtroListaId}
                                            onChange={e => {
                                                setFiltroListaId(e.target.value);
                                                aplicarFiltroDropdown('lista_id', e.target.value);
                                            }}
                                            className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                            style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                            onFocus={e => e.target.style.borderColor = 'var(--color-primario)'}
                                            onBlur={e => e.target.style.borderColor = ''}
                                        >
                                            <option value="">Todas</option>
                                            {listas.map(lista => (
                                                <option key={lista.id} value={String(lista.id)}>{lista.nombre}</option>
                                            ))}
                                        </select>
                                        <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                            <ChevronDown className="w-4 h-4 theme-text-muted" />
                                        </div>
                                    </div>
                                </div>

                                <div className="min-w-0">
                                    <label htmlFor="clientes-filtro-tipo" className="block text-xs font-semibold theme-text-muted mb-2">Asignación</label>
                                    <div className="relative">
                                        <select id="clientes-filtro-tipo" name="tipo"
                                            aria-label="Tipo de asignación"
                                            value={filtroTipo}
                                            onChange={e => {
                                                setFiltroTipo(e.target.value);
                                                aplicarFiltroDropdown('tipo', e.target.value);
                                            }}
                                            className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                            style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                            onFocus={e => e.target.style.borderColor = 'var(--color-primario)'}
                                            onBlur={e => e.target.style.borderColor = ''}
                                        >
                                            <option value="">Todos</option>
                                            <option value="directos">Directos</option>
                                            <option value="heredados">Heredados</option>
                                        </select>
                                        <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                            <ChevronDown className="w-4 h-4 theme-text-muted" />
                                        </div>
                                    </div>
                                </div>

                                <div className="min-w-0">
                                    <label htmlFor="clientes-filtro-estado" className="block text-xs font-semibold theme-text-muted mb-2">Estado</label>
                                    <div className="relative">
                                        <select id="clientes-filtro-estado" name="estado"
                                            aria-label="Estado del cliente"
                                            value={filtroEstado}
                                            onChange={e => {
                                                setFiltroEstado(e.target.value);
                                                aplicarFiltroDropdown('estado', e.target.value);
                                            }}
                                            className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                            style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                        >
                                            <option value="">Todos</option>
                                            <option value="activos">Activos</option>
                                            <option value="inactivos">Inactivos</option>
                                        </select>
                                        <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                            <ChevronDown className="w-4 h-4 theme-text-muted" />
                                        </div>
                                    </div>
                                </div>

                                <div className="min-w-0">
                                    <label htmlFor="clientes-filtro-orden" className="block text-xs font-semibold theme-text-muted mb-2">Orden</label>
                                    <div className="relative">
                                        <select id="clientes-filtro-orden" name="orden"
                                            aria-label="Orden de clientes"
                                            value={filtroOrden}
                                            onChange={e => {
                                                setFiltroOrden(e.target.value);
                                                aplicarFiltroDropdown('orden', e.target.value);
                                            }}
                                            className="w-full pl-5 pr-10 py-4 theme-element border theme-border rounded-xl theme-text-main text-xs font-bold uppercase tracking-widest outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md appearance-none cursor-pointer"
                                            style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                        >
                                            <option value="numero_asc">Número menor</option>
                                            <option value="numero_desc">Número mayor</option>
                                            <option value="monto_desc">Mayor monto</option>
                                            <option value="monto_asc">Menor monto</option>
                                        </select>
                                        <div className="pointer-events-none absolute inset-y-0 right-4 flex items-center">
                                            <ChevronDown className="w-4 h-4 theme-text-muted" />
                                        </div>
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

                            <div aria-busy={cargandoLista} className={`clientes-records space-y-3 ${cargandoLista ? 'opacity-60 pointer-events-none' : ''}`}>
                                <p role="status" className="sr-only">{cargandoLista ? 'Cargando clientes…' : `${listaClientes.length} clientes en esta página`}</p>
                                {listaClientes.length === 0 ? (
                                    <div className="text-center py-16 theme-element border-2 border-dashed theme-border rounded-[2rem]">
                                        <Users className="w-12 h-12 theme-text-muted mx-auto mb-4 opacity-50" />
                                        <h3 className="text-lg font-black italic uppercase theme-text-main">Sin resultados</h3>
                                        <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted mt-2">Prueba con otro nombre, número o filtro.</p>
                                    </div>
                                ) : (
                                    listaClientes.map((cliente) => (
                                        <article key={cliente.id} className="clientes-record theme-element border theme-border p-4 rounded-2xl group">

                                            <div className="clientes-record-identity flex items-start gap-3 min-w-0">
                                                <div className="clientes-record-number theme-surface border theme-border rounded-xl px-2 py-3 font-black theme-text-main text-xs text-center tabular-nums">
                                                    {cliente.numero_cliente}
                                                </div>

                                                <div className="min-w-0 flex-1">
                                                    <h3 className="clientes-record-name text-[15px] font-black theme-text-main leading-snug m-0">
                                                        {cliente.nombre}
                                                    </h3>
                                                    <div className="flex flex-wrap items-center gap-2 mt-2">
                                                        <BadgeListaDescuento nombre={cliente.lista_descuento?.nombre} />
                                                        {(cliente.es_inactivo === true || cliente.es_inactivo === 1) && (
                                                            <span className="px-2 py-1 bg-amber-500/10 text-amber-600 border border-amber-500/30 text-[9px] font-black uppercase tracking-widest rounded-md">
                                                                Inactivo
                                                            </span>
                                                        )}
                                                        <span className="flex items-center gap-1 text-xs font-semibold theme-text-muted tabular-nums">
                                                            <TrendingUp className="w-3 h-3 text-emerald-500" />
                                                            {formatoMoneda.format(cliente.monto_venta_actual ?? 0)}
                                                        </span>
                                                    </div>
                                                    {cliente.rfc || cliente.correo_electronico || cliente.nombre_razon_social ? (
                                                        <p className="clientes-record-fiscal text-xs theme-text-muted mt-2">
                                                            {[cliente.rfc, cliente.codigo_postal, cliente.correo_electronico].filter(Boolean).join(' · ')}
                                                        </p>
                                                    ) : (
                                                        <span className="inline-block mt-1.5 text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded-md bg-slate-500/10 text-slate-500 border border-slate-500/20">
                                                            Sin datos fiscales
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="clientes-record-actions flex flex-wrap items-center gap-2 pt-3 border-t theme-border">
                                                {cliente.es_heredado ? (
                                                    <div className="clientes-record-assignment min-w-0 flex-1 pr-2">
                                                        <p className="text-[8px] font-black text-amber-500 uppercase tracking-widest">Protegido</p>
                                                        <p className="text-[10px] font-black text-amber-600 dark:text-amber-400 uppercase italic">Heredado</p>
                                                    </div>
                                                ) : (
                                                    <div className="clientes-record-assignment min-w-0 flex-1 pr-2">
                                                        <p className="text-[8px] font-black theme-text-muted uppercase tracking-widest">Asignación</p>
                                                        <p className="text-xs font-bold theme-text-main break-words">
                                                            {cliente.vendedor ? cliente.vendedor.name : 'Sin Asignar'}
                                                        </p>
                                                    </div>
                                                )}

                                                <a
                                                    href={route('admin.clientes.direcciones.index', cliente.id)}
                                                    className="relative inline-flex items-center justify-center gap-2 min-h-11 px-3 theme-surface border theme-border rounded-xl transition-shadow hover:shadow-md text-xs font-bold"
                                                    aria-label={`Ver direcciones de ${cliente.nombre}`}
                                                    style={{ color: 'var(--color-primario)' }}
                                                    title={
                                                        cliente.direcciones_sin_verificar_count > 0
                                                            ? `${cliente.direcciones_sin_verificar_count} sin verificar`
                                                            : `${cliente.direcciones_activas_count || 0} direcciones`
                                                    }
                                                >
                                                    <MapPin className="w-4 h-4" aria-hidden="true" /> Direcciones
                                                    {(cliente.direcciones_activas_count > 0 || cliente.direcciones_sin_verificar_count > 0) && (
                                                        <span
                                                            className={`absolute -top-1 -right-1 min-w-[16px] h-4 px-1 rounded-full text-[8px] font-black flex items-center justify-center text-white ${
                                                                cliente.direcciones_sin_verificar_count > 0 ? 'bg-amber-500' : ''
                                                            }`}
                                                            style={cliente.direcciones_sin_verificar_count > 0 ? undefined : { backgroundColor: 'var(--color-primario)' }}
                                                        >
                                                            {cliente.direcciones_sin_verificar_count > 0
                                                                ? '!'
                                                                : cliente.direcciones_activas_count}
                                                        </span>
                                                    )}
                                                    {cliente.direcciones_activas_count === 0 && (
                                                        <span className="sr-only">Sin direcciones verificadas</span>
                                                    )}
                                                </a>
                                                <button
                                                    onClick={() => abrirModal('editar', cliente)}
                                                    className="inline-flex items-center justify-center gap-2 min-h-11 px-3 theme-surface border theme-border rounded-xl transition-shadow hover:shadow-md text-xs font-bold"
                                                    aria-label={`Editar a ${cliente.nombre}`}
                                                    style={{ color: 'var(--color-primario)' }}
                                                    title="Editar cliente"
                                                >
                                                    <Edit3 className="w-4 h-4" aria-hidden="true" /> Editar
                                                </button>
                                            </div>
                                        </article>
                                    ))
                                )}

                            </div>
                        </section>
                    </div>
                </div>
                )}
            </div>
        </AppLayout>
    );
}