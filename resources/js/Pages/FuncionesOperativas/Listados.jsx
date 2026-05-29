import React, { useState, useRef, useEffect } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { Head, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { geliaCardClass } from '../../utils/geliaTheme';
import GeliaLogo from '../../Components/GeliaLogo';
import ModalPlantilla from './Partials/ModalPlantilla';
import ModalAlerta from './Partials/ModalAlerta';
import {
    FileSpreadsheet, Settings2, Plus, Download, X,
    AlertTriangle, Check, Trash2, Edit3, Users, Share2,
    Upload, Archive, Box, Calculator, BarChart, PieChart,
    FileText, ClipboardList, CloudUpload, Zap, Layers,
    Tags, ShoppingCart, Truck, PackageOpen, ListTodo,
    Briefcase, Folders, Database, TrendingUp, Target, Clock
} from 'lucide-react';

// ----------------------------------------------------------------------
// CONSTANTES GLOBALES Y MAPEO DE DATOS
// ----------------------------------------------------------------------
const PIN_SISTEMA = "1998";

const CONFIGURACION_POR_DEFECTO = {
    pct_bronce: 12.39, pct_plata: 14.14, pct_oro: 15.89,
    pct_diamante: 17.65, pct_plataformas: 23.00,
    pct_lista3: 14.28, pct_lista4: 17.71, pct_venta_especial: 25.00
};

const COLUMNAS_DISPONIBLES = [
    'Folio', 'SKU', 'Descripcion', 'Marca', 'Existencia', 'Almacen',
    'PG', 'Bronce', 'Plata', 'Oro', 'Diamante', 'Plataformas',
    'Lista3', 'Lista4', 'VentaEspecial', 'ListaBoutique',
    'CostoCalculado', 'CostoWizerp'
];

const LISTAS_DEFAULT = {
    'resurtido': ['Folio', 'SKU', 'Descripcion', 'Existencia', 'PG', 'Plataformas', 'Bronce'],
    'costos': ['Almacen', 'SKU', 'Descripcion', 'Existencia', 'CostoWizerp'],
    'actualizada': ['Folio', 'SKU', 'Descripcion', 'Existencia', 'CostoCalculado', 'Plataformas'],
    'inventario': ['Folio', 'SKU', 'Descripcion', 'Existencia', 'PG', 'Bronce'],
    'venta_especial': ['Folio', 'SKU', 'Descripcion', 'Existencia', 'PG', 'VentaEspecial']
};

const ICONS_MAP = {
    'FileSpreadsheet': FileSpreadsheet,
    'Archive': Archive,
    'Box': Box,
    'Calculator': Calculator,
    'BarChart': BarChart,
    'PieChart': PieChart,
    'FileText': FileText,
    'ClipboardList': ClipboardList,
    'Tags': Tags,                 // Etiquetas (bueno para categorías o precios)
    'ShoppingCart': ShoppingCart, // Carrito de compras (para ventas especiales)
    'Truck': Truck,               // Camión (para logística o resurtido)
    'PackageOpen': PackageOpen,   // Caja abierta (para inventario)
    'ListTodo': ListTodo,         // Lista de tareas (para listas actualizadas)
    'Briefcase': Briefcase,       // Maletín (para reportes ejecutivos)
    'Folders': Folders,           // Carpetas (para organización)
    'Database': Database,         // Base de datos (reportes del sistema)
    'TrendingUp': TrendingUp,     // Gráfica hacia arriba (para crecimiento)
    'Target': Target,              // Objetivo/Diana (para metas de ventas)
    'Clock': Clock                // Reloj (para seguimiento de tiempo)
};

const SYSTEM_TEMPLATES_UI = [
    { id: 'resurtido', label: 'Resurtido', icon: Box, colorText: 'text-blue-500', bgHover: 'hover:border-blue-500/50' },
    { id: 'costos', label: 'Costos', icon: Calculator, colorText: 'text-purple-500', bgHover: 'hover:border-purple-500/50' },
    { id: 'actualizada', label: 'Actualizada', icon: Zap, colorText: 'text-amber-500', bgHover: 'hover:border-amber-500/50' },
    { id: 'inventario', label: 'Inventario', icon: Archive, colorText: 'text-emerald-500', bgHover: 'hover:border-emerald-500/50' },
    { id: 'venta_especial', label: 'Venta Especial', icon: BarChart, colorText: 'text-rose-500', bgHover: 'hover:border-rose-500/50' }
];

const GELIA_PALETTE = {
    'rosa': '#ec4899',
    'azul': '#3b82f6',
    'verde': '#10b981',
    'amarillo': '#f59e0b',
    'purple': '#a855f7',
    'indigo': '#6366f1',
    'orange': '#f97316',
};

// ----------------------------------------------------------------------
// SUBCOMPONENTE: ZONA DE CARGA (HEREDANDO CLASES NATIVAS DE GELIA NV)
// ----------------------------------------------------------------------
const UploadArea = ({ id, label, isRequired, instructions, fileRef, onChange, file }) => {

    // Si NO hay archivo: Usamos tus clases base (theme-surface, theme-border)
    // Si SÍ hay archivo: Le damos un tinte esmeralda respetando la opacidad
    const containerClasses = file
        ? "bg-emerald-500/5 dark:bg-emerald-500/10 border-emerald-500/40"
        : "theme-surface theme-border hover:border-[var(--color-primario)]";

    const textTitleClass = file ? "text-emerald-700 dark:text-emerald-400" : "theme-text-main";
    const textMutedClass = file ? "text-emerald-600/80 dark:text-emerald-400/80" : "theme-text-muted";

    const iconBoxClasses = file
        ? "bg-emerald-500/10 border border-emerald-500/30 text-emerald-500"
        : "theme-element theme-border text-[var(--color-primario)]";

    const btnClasses = file
        ? "bg-emerald-500 text-white shadow-emerald-500/30 border-transparent"
        : "theme-element theme-text-main theme-border hover:border-[var(--color-primario)]";

    return (
        <label className={`relative flex flex-col items-center justify-center p-8 border-[3px] border-dashed rounded-[2.5rem] transition-all duration-300 group cursor-pointer overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 ${containerClasses}`}>

            <input type="file" className="hidden" ref={fileRef} onChange={(e) => onChange(e, id)} accept=".csv, .xlsx" />

            {/* Marca de agua de fondo */}
            <div className={`absolute inset-0 opacity-[0.03] dark:opacity-[0.05] pointer-events-none flex items-center justify-center overflow-hidden ${file ? 'text-emerald-500' : 'text-zinc-900 dark:text-white'}`}>
                <CloudUpload className="w-48 h-48" />
            </div>

            {/* Icono Central */}
            <div className={`relative z-10 w-16 h-16 rounded-[1.5rem] flex items-center justify-center mb-5 transition-transform group-hover:scale-110 shadow-sm border ${iconBoxClasses}`}>
                <CloudUpload className="w-8 h-8" />
            </div>

            {/* Título */}
            <h3 className={`relative z-10 text-[13px] font-black uppercase tracking-widest mb-2 text-center drop-shadow-sm ${textTitleClass}`}>
                {label} {isRequired && <span className="text-red-500">*</span>}
            </h3>

            {/* Instrucciones */}
            <details className="relative z-20 w-full text-center mb-6 outline-none" onClick={(e) => e.stopPropagation()}>
                <summary className={`text-[10px] hover:text-[var(--color-primario)] transition-colors select-none outline-none font-bold cursor-pointer ${textMutedClass}`}>
                    &gt; Ver instrucciones
                </summary>
                {/* Aquí heredamos 'theme-element' que asegura contraste perfecto en tu diseño */}
                <div className="text-[10px] mt-3 p-4 rounded-2xl border leading-relaxed shadow-inner text-left theme-element theme-text-main theme-border" dangerouslySetInnerHTML={{ __html: instructions }}></div>
            </details>

            {/* Botón Inferior */}
            <div className={`relative z-10 w-full text-center py-4 px-6 rounded-2xl border-[1.5px] transition-all text-xs font-black uppercase tracking-widest flex items-center justify-center gap-3 shadow-sm group-hover:shadow-md ${btnClasses}`}>
                {file ? (
                    <><Check className="w-5 h-5" /> <span className="truncate max-w-[150px]">{file.name}</span></>
                ) : (
                    <>Seleccionar Archivo <Upload className="w-4 h-4 opacity-50" /></>
                )}
            </div>
        </label>
    );
};

// ----------------------------------------------------------------------
// COMPONENTE PRINCIPAL
// ----------------------------------------------------------------------
export default function Listados({ auth, listas_personalizadas = [], configuracion_listados = {}, usuarios_sistema = [] }) {
    const can = (permiso) => auth?.user?.permissions?.includes(permiso) || auth?.user?.roles?.includes('Super Admin');
    const miId = auth?.user?.id;

    const [isLoading, setIsLoading] = useState(false);
    const [archivos, setArchivos] = useState({ existencias: null, precios: null, costos: null });
    const [modalConfig, setModalConfig] = useState({ show: false, unlocked: false, data: { ...CONFIGURACION_POR_DEFECTO, ...configuracion_listados } });
    const [modalInconsistencias, setModalInconsistencias] = useState({ show: false, data: [], tempFile: '', nombreDescarga: '' });

    // ESTADO PARA MODAL DE ALERTAS GESTIONADO POR GELIA
    const [alerta, setAlerta] = useState({ show: false, type: 'error', title: '', message: '', details: null });

    const estadoInicialPlantilla = {
        id: null,
        titulo_lista: '',
        descripcion: '',
        color: 'azul',
        icono_personalizado: 'FileSpreadsheet',
        nombre_archivo_salida: '',
        archivos_requeridos: ['existencias'],
        columnas_exportar: [],
        solo_con_existencia: false,
        filtro_relojes: false,
        shared_users: []
    };
    const [modalPlantilla, setModalPlantilla] = useState({ show: false, data: estadoInicialPlantilla });

    const fileRefs = { existencias: useRef(null), precios: useRef(null), costos: useRef(null) };

    const activeCardClass = geliaCardClass('relative z-10');

    // Helper para disparar alertas UI
    const dispararAlerta = (type, title, message, details = null) => {
        setAlerta({ show: true, type, title, message, details });
    };

    const handleFileChange = (e, tipo) => { setArchivos(prev => ({ ...prev, [tipo]: e.target.files[0] || null })); };

    const abrirModalCrear = () => setModalPlantilla({ show: true, data: estadoInicialPlantilla });

    const abrirModalEditar = (lista) => {
        setModalPlantilla({
            show: true,
            data: {
                id: lista.id,
                titulo_lista: lista.titulo_lista,
                descripcion: lista.descripcion || '',
                icono_personalizado: lista.icono_personalizado || 'FileSpreadsheet', // Valor por defecto si es nulo
                color: lista.color || 'azul',
                nombre_archivo_salida: lista.nombre_archivo_salida,
                archivos_requeridos: lista.archivos_requeridos || ['existencias'],
                columnas_exportar: lista.columnas_exportar || [],
                solo_con_existencia: lista.solo_con_existencia === 1 || lista.solo_con_existencia === true,
                filtro_relojes: lista.filtro_relojes === 1 || lista.filtro_relojes === true,
                shared_users: lista.shared_users ? lista.shared_users.map(u => u.id) : []
            }
        });
    };

    const guardarPlantilla = async (e) => {
        e.preventDefault();

        if (modalPlantilla.data.columnas_exportar.length === 0) {
            dispararAlerta('warning', 'Validación Requerida_', 'Debes seleccionar al menos una columna para exportar el archivo.');
            return;
        }

        if (!modalPlantilla.data.archivos_requeridos.includes('existencias')) {
            modalPlantilla.data.archivos_requeridos.push('existencias');
        }

        setIsLoading(true);

        try {
            const endpoint = modalPlantilla.data.id ? route('listados.actualizar', modalPlantilla.data.id) : route('listados.guardar');
            await axios.post(endpoint, modalPlantilla.data);
            setModalPlantilla({ show: false, data: estadoInicialPlantilla });
            dispararAlerta('success', 'Éxito_', 'La plantilla ha sido guardada correctamente.');
            router.reload({ only: ['listas_personalizadas'] });
        } catch (error) {
            dispararAlerta('error', 'Error al Guardar_', 'Ocurrió un problema al procesar la plantilla.', error.response?.data?.errors || error.message);
        } finally {
            setIsLoading(false);
        }
    };

    const eliminarListaPersonalizada = async (id) => {
        if (!window.confirm("¿Seguro que deseas eliminar esta plantilla?")) return;
        setIsLoading(true);
        try {
            await axios.delete(route('listados.eliminar', id));
            router.reload({ only: ['listas_personalizadas'] });
        } catch (error) {
            dispararAlerta('error', 'Error al Eliminar_', 'No se pudo procesar la baja de la lista en el servidor.');
        } finally {
            setIsLoading(false);
        }
    };

    const procesarSolicitud = async (tipo) => {
        if (!archivos.existencias) {
            dispararAlerta('warning', 'Archivo Faltante_', 'El archivo maestro de Existencias es estrictamente obligatorio para cualquier cruce operativo.');
            return;
        }

        setIsLoading(true);
        const formData = new FormData();
        formData.append('tipo_lista', tipo);

        if (LISTAS_DEFAULT[tipo]) {
            formData.append('orden_final', LISTAS_DEFAULT[tipo].join(','));
        }

        if (archivos.existencias) formData.append('existencias', archivos.existencias);
        if (archivos.precios) formData.append('precios', archivos.precios);
        if (archivos.costos) formData.append('costos', archivos.costos);

        try {
            const response = await axios.post(route('listados.generar'), formData, { responseType: 'blob', headers: { 'Content-Type': 'multipart/form-data' } });

            if (response.data.type === 'application/json') {
                const text = await response.data.text();
                const json = JSON.parse(text);
                if (json.requiere_confirmacion) {
                    setModalInconsistencias({ show: true, data: json.inconsistencias, tempFile: json.temp_file, nombreDescarga: json.nombre_descarga });
                } else if (json.errors || json.error) {
                    dispararAlerta('error', 'Cruce Interrumpido_', 'Revisa que hayas subido los archivos requeridos para esta lista.', json.errors || json.error);
                }
                setIsLoading(false);
                return;
            }

            const url = window.URL.createObjectURL(new Blob([response.data]));
            const a = document.createElement('a');
            a.href = url;
            let fileName = `${String(tipo).toUpperCase()}-${new Date().toISOString().split('T')[0]}.xlsx`;
            const cd = response.headers['content-disposition'];
            if (cd) { const m = /filename="?([^"]+)"?/.exec(cd); if (m && m[1]) fileName = m[1]; }
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            a.remove();

            setArchivos({ existencias: null, precios: null, costos: null });
            Object.values(fileRefs).forEach(ref => { if (ref.current) ref.current.value = '' });

        } catch (error) {
            let msgLog = error.message;
            if (error.response && error.response.data && error.response.data.type === 'application/json') {
                const text = await error.response.data.text();
                const json = JSON.parse(text);
                msgLog = json.errors || json.error || 'Error desconocido';
            }
            dispararAlerta('error', 'Fallo de Procesamiento_', 'Hubo un problema de conexión o validación al generar el reporte.', msgLog);
        } finally {
            setIsLoading(false);
        }
    };

    const descargarTemporal = () => {
        setIsLoading(true);
        setModalInconsistencias(prev => ({ ...prev, show: false }));
        const url = new URL(route('listados.descargar_temporal'), window.location.origin);
        url.searchParams.append('temp_file', modalInconsistencias.tempFile);
        url.searchParams.append('nombre_descarga', modalInconsistencias.nombreDescarga);
        window.location.href = url.toString();
        setIsLoading(false);
    };

    const desbloquearConfiguracion = () => {
        const pin = window.prompt("Ingresa el PIN de seguridad:");
        if (pin === PIN_SISTEMA) setModalConfig(prev => ({ ...prev, unlocked: true }));
        else if (pin !== null) dispararAlerta('error', 'Acceso Denegado_', 'El PIN proporcionado es incorrecto.');
    };

    const guardarConfiguracionGlobal = async () => {
        setIsLoading(true);
        try {
            await axios.post(route('listados.config.guardar'), modalConfig.data);
            setModalConfig(prev => ({ ...prev, show: false, unlocked: false }));
            dispararAlerta('success', 'Operación Exitosa_', 'Las configuraciones globales han sido guardadas.');
            router.reload({ only: ['configuracion_listados'] });
        } catch (error) {
            dispararAlerta('error', 'Error_', 'No se pudo guardar la configuración.', error.response?.data?.errors || error.message);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Cruce de Inventarios | GELIANV" />
            <div className="w-full max-w-[1400px] mx-auto p-4 md:p-6 lg:p-12 space-y-8 relative animate-page-reveal">

                {/* --- HEADER --- */}
                <header className={`${activeCardClass} p-8 md:p-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6`}>
                    <div>
                        <div className="flex items-center gap-3 mb-2">
                            <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                                <FileSpreadsheet className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                            </div>
                            <h1 className="text-2xl md:text-3xl font-black uppercase theme-text-main tracking-tighter italic m-0">
                                Generador de Listados
                            </h1>
                        </div>
                        <p className="text-[11px] uppercase font-bold theme-text-muted tracking-widest ml-16">Genera tu listado personalizados.</p>
                    </div>
                    <div className="flex gap-4 w-full md:w-auto">
                        {can('listados.configurar_porcentajes') && (
                            <button onClick={() => setModalConfig(prev => ({ ...prev, show: true }))} className="flex-1 md:flex-none flex justify-center items-center gap-2 px-6 py-4 theme-surface border-[1.5px] theme-border hover:border-[var(--color-primario)] rounded-2xl text-[11px] font-black uppercase tracking-widest theme-text-main transition-all hover:shadow-md outline-none">
                                <Settings2 className="w-4 h-4" /> Globales
                            </button>
                        )}
                        {can('listados.crear') && (
                            <button onClick={abrirModalCrear} className="flex-1 md:flex-none flex justify-center items-center gap-2 px-6 py-4 text-white rounded-2xl text-[11px] font-black uppercase tracking-widest shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Plus className="w-4 h-4" /> Nueva Plantilla
                            </button>
                        )}
                    </div>
                </header>

                {/* --- SECCIÓN 1: CARGA DE ARCHIVOS CORREGIDA --- */}
                <section className={`${activeCardClass} p-8 md:p-10 space-y-8`}>
                    <div className="flex items-center gap-3">
                        <Layers className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">Archivos Operativos_</h2>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <UploadArea
                            id="existencias" label="1. Existencias" isRequired={true} fileRef={fileRefs.existencias}
                            onChange={handleFileChange} file={archivos.existencias}
                            // Pasamos colores de borde neutros para que se adapten a ambos modos cuando no hay archivo
                            colorClass="border-zinc-300 dark:border-zinc-700 hover:border-[var(--color-primario)]"
                            instructions="<span style='color: var(--color-primario)'>Ruta:</span> Almacenes > Inventarios<br><span style='color: var(--color-primario)'>Filtros:</span> Seleccionar almacen (CEDIS, TIENDA o REMATES), Existencia diferente o igual a 0<br><span style='color: var(--color-primario)'>Opciones:</span> EXCEL > Exportar en CSV."
                        />
                        <UploadArea
                            id="precios" label="2. Precios" isRequired={false} fileRef={fileRefs.precios}
                            onChange={handleFileChange} file={archivos.precios}
                            colorClass="border-zinc-300 dark:border-zinc-700 hover:border-[var(--color-primario)]"
                            instructions="<span style='color: var(--color-primario)'>Ruta:</span> Almacen > Productos<br><span style='color: var(--color-primario)'>Operaciones:</span> Exportar lista de precios<br><span style='color: var(--color-primario)'>Opciones:</span> Guardar en CSV o Excel."
                        />
                        <UploadArea
                            id="costos" label="3. Costos" isRequired={false} fileRef={fileRefs.costos}
                            onChange={handleFileChange} file={archivos.costos}
                            colorClass="border-zinc-300 dark:border-zinc-700 hover:border-[var(--color-primario)]"
                            instructions="<span style='color: var(--color-primario)'>Ruta:</span> Almacenes > Costos<br><span style='color: var(--color-primario)'>Operaciones:</span> Seleccionar Opcion Excel<br><span style='color: var(--color-primario)'>Opciones:</span> Guardar en CSV."
                        />
                    </div>
                </section>

                {/* --- SECCIÓN 2: PLANTILLAS DEL SISTEMA --- */}
                <section className={`${activeCardClass} p-8 md:p-10 space-y-8`}>
                    <div className="flex items-center gap-3">
                        <Box className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">Plantillas Base_</h2>
                    </div>

                    <div className="grid grid-cols-2 lg:grid-cols-5 gap-6">
                        {SYSTEM_TEMPLATES_UI.map((tpl) => {
                            const TemplateIcon = tpl.icon;
                            return (
                                <button
                                    key={tpl.id}
                                    onClick={() => procesarSolicitud(tpl.id)}
                                    className={`p-6 border-[1.5px] theme-border theme-surface rounded-[2rem] flex flex-col items-start gap-4 transition-all duration-300 group outline-none hover:shadow-lg hover:-translate-y-1 ${tpl.bgHover}`}
                                >
                                    <div className={`p-3 rounded-2xl bg-black/5 dark:bg-white/5 ${tpl.colorText} group-hover:scale-110 transition-transform`}>
                                        <TemplateIcon className="w-6 h-6" />
                                    </div>
                                    <div className="text-left">
                                        <span className="text-[12px] font-black uppercase theme-text-main block mb-1">{tpl.label}</span>
                                        <span className="text-[9px] theme-text-muted font-bold uppercase tracking-widest flex items-center gap-1 group-hover:text-[var(--color-primario)] transition-colors">
                                            <Download className="w-3 h-3" /> Generar Excel
                                        </span>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </section>

                {/* --- SECCIÓN 3: PLANTILLAS PERSONALIZADAS --- */}
                {listas_personalizadas.length > 0 && (
                    <section className={`${activeCardClass} p-8 md:p-10 space-y-8`}>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <FileText className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                                <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">Mis Plantillas_</h2>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            {listas_personalizadas.map((lista) => {
                                const esMia = lista.user_id === miId;

                                // Busca el icono en el mapa, si no existe o es null, usa el estándar
                                const iconoKey = lista.icono_personalizado || 'FileSpreadsheet';
                                const IconoAsignado = ICONS_MAP[iconoKey] || FileSpreadsheet;

                                // Extrae el color hexadecimal de la paleta, por defecto el color primario de Gelia
                                const colorHex = GELIA_PALETTE[lista.color] || 'var(--color-primario)';

                                return (
                                    <div
                                        key={lista.id}
                                        className="relative group p-6 border-[1.5px] theme-border theme-surface rounded-[2rem] flex flex-col overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1"
                                    >
                                        {/* Barra decorativa superior del color seleccionado */}
                                        <div className="absolute top-0 left-0 w-full h-1.5 opacity-40 group-hover:opacity-100 transition-opacity" style={{ backgroundColor: colorHex }}></div>

                                        <button
                                            type="button"
                                            onClick={() => procesarSolicitud(lista.id)}
                                            /* 👇 CAMBIO 1: Agregamos "pb-4" (padding-bottom) para darle aire y que nunca choque con la línea de abajo */
                                            className="flex flex-col items-start text-left w-full h-full outline-none flex-1 group/btn cursor-pointer pb-4"
                                        >
                                            <div className="w-full flex justify-between items-start mb-5 mt-1">
                                                <div
                                                    className="w-12 h-12 rounded-2xl theme-surface border flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform"
                                                    style={{ backgroundColor: `color-mix(in srgb, ${colorHex} 10%, transparent)`, borderColor: `color-mix(in srgb, ${colorHex} 30%, transparent)` }}
                                                >
                                                    <IconoAsignado className="w-6 h-6" style={{ color: colorHex }} />
                                                </div>
                                                <div className="flex flex-col items-end gap-2">
                                                    <span className={`text-[9px] font-black uppercase px-3 py-1.5 rounded-xl tracking-widest ${esMia ? 'bg-emerald-500/10 text-emerald-500' : 'bg-indigo-500/10 text-indigo-500'}`}>
                                                        {esMia ? 'Propietario' : 'Compartida'}
                                                    </span>
                                                </div>
                                            </div>

                                            <span
                                                className="text-sm font-black uppercase theme-text-main block tracking-tight transition-colors line-clamp-1"
                                                onMouseEnter={(e) => e.target.style.color = colorHex}
                                                onMouseLeave={(e) => e.target.style.color = ''}
                                            >
                                                {lista.titulo_lista}
                                            </span>
                                            <span className="text-[10px] theme-text-muted font-bold uppercase mt-1 block tracking-widest">
                                                <Users className="w-3 h-3 inline mr-1 opacity-70" /> {esMia ? 'Gestión Personal' : `Autor: ${lista.nombre_creador}`}
                                            </span>
                                            {lista.descripcion && <p className="text-[11px] theme-text-muted mt-2 italic line-clamp-2 leading-relaxed">{lista.descripcion}</p>}

                                            {/* 👇 CAMBIO 2: Redujimos el mt-5 a mt-4 y el gap-2 a gap-1.5 para juntar un poco más las etiquetas */}
                                            <div className="flex flex-wrap gap-1.5 mt-4">

                                                {/* 👇 CAMBIO 3: Cambiamos el padding de los tags de "px-2.5 py-1.5" a "px-2 py-1" para hacerlos más compactos */}
                                                {lista.archivos_requeridos?.includes('existencias') && (
                                                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest bg-blue-500/10 text-blue-500 border border-blue-500/20">
                                                        <FileSpreadsheet className="w-3 h-3" /> Existencias
                                                    </span>
                                                )}
                                                {lista.archivos_requeridos?.includes('precios') && (
                                                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                                                        <Tags className="w-3 h-3" /> Precios
                                                    </span>
                                                )}
                                                {lista.archivos_requeridos?.includes('costos') && (
                                                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest bg-purple-500/10 text-purple-500 border border-purple-500/20">
                                                        <Calculator className="w-3 h-3" /> Costos
                                                    </span>
                                                )}

                                                {lista.solo_con_existencia && (
                                                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest bg-orange-500/10 text-orange-500 border border-orange-500/20">
                                                        <Archive className="w-3 h-3" /> Solo c/ Exis.
                                                    </span>
                                                )}
                                                {lista.filtro_relojes && (
                                                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest bg-indigo-500/10 text-indigo-500 border border-indigo-500/20">
                                                        <Zap className="w-3 h-3" /> Relojes ('R')
                                                    </span>
                                                )}
                                            </div>
                                        </button>

                                        {(can('listados.editar') || can('listados.eliminar')) && esMia && (
                                            <div className="mt-auto pt-4 border-t border-dashed theme-border flex justify-end gap-4 relative z-10">
                                                {can('listados.editar') && (
                                                    <button
                                                        onClick={(e) => { e.stopPropagation(); abrirModalEditar(lista); }}
                                                        className="text-[10px] flex items-center gap-1.5 theme-text-muted hover:text-[var(--color-primario)] font-black uppercase tracking-widest outline-none transition-colors"
                                                    >
                                                        <Edit3 className="w-4 h-4" /> Editar
                                                    </button>
                                                )}
                                                {can('listados.eliminar') && (
                                                    <button
                                                        onClick={(e) => { e.stopPropagation(); eliminarListaPersonalizada(lista.id); }}
                                                        className="text-[10px] flex items-center gap-1.5 theme-text-muted hover:text-red-500 font-black uppercase tracking-widest outline-none transition-colors"
                                                    >
                                                        <Trash2 className="w-4 h-4" /> Borrar
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </section>
                )}
            </div>

            {/* ====================================================================== */}
            {/* PORTALS GLOBALES */}
            {/* ====================================================================== */}

            <ModalAlerta
                show={alerta.show}
                type={alerta.type}
                title={alerta.title}
                message={alerta.message}
                details={alerta.details}
                onClose={() => setAlerta({ ...alerta, show: false })}
            />

            {isLoading && createPortal(
                <div className="fixed inset-0 z-[99999] flex items-center justify-center bg-black/70 backdrop-blur-xl transition-opacity animate-fade-in">
                    <div className="flex flex-col items-center gap-6 p-8 rounded-[2.5rem] shadow-[0_0_60px_rgba(0,0,0,0.3)] border border-white/10 bg-black/40 backdrop-blur-md">
                        <GeliaLogo variant="sparkle" className="w-20 h-20 drop-shadow-2xl" />
                        <span className="text-[11px] font-black uppercase tracking-widest text-white drop-shadow-md">Procesando_</span>
                    </div>
                </div>,
                document.body
            )}

            <ModalPlantilla
                show={modalPlantilla.show}
                data={modalPlantilla.data}
                setData={(key, value) => setModalPlantilla(p => ({ ...p, data: { ...p.data, [key]: value } }))}
                onClose={() => setModalPlantilla({ show: false, data: estadoInicialPlantilla })}
                onSave={guardarPlantilla}
                usuarios_sistema={usuarios_sistema}
                ICONS_MAP={ICONS_MAP}
                COLUMNAS_DISPONIBLES={COLUMNAS_DISPONIBLES}
            />

            {modalConfig.show && createPortal(
                <div className="fixed inset-0 z-[130] flex items-center justify-center bg-black/70 backdrop-blur-xl p-4 animate-fade-in">
                    <div className="theme-surface border border-white/20 dark:border-zinc-700 w-full max-w-4xl rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col">
                        <div className="p-8 border-b theme-border flex justify-between items-center theme-element">
                            <h2 className="text-lg font-black uppercase theme-text-main flex items-center gap-3 italic">
                                <Settings2 className="w-6 h-6" style={{ color: 'var(--color-primario)' }} /> Parámetros Globales_
                            </h2>
                            <button onClick={() => setModalConfig(prev => ({ ...prev, show: false }))} className="p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none">
                                <X className="w-6 h-6" />
                            </button>
                        </div>

                        <div className="p-8 grid grid-cols-2 md:grid-cols-4 gap-6">
                            {Object.keys(CONFIGURACION_POR_DEFECTO).map((key) => (
                                <div key={key}>
                                    <label className="block text-[10px] font-black theme-text-muted mb-2 uppercase tracking-widest">
                                        {key.replace('pct_', '').replace('_', ' ')}
                                    </label>
                                    <div className="flex items-center">
                                        <input
                                            type="number" step="0.01"
                                            value={modalConfig.data[key] || ''}
                                            onChange={(e) => setModalConfig(prev => ({ ...prev, data: { ...prev.data, [key]: e.target.value } }))}
                                            disabled={!modalConfig.unlocked}
                                            className="w-full theme-surface border theme-border rounded-l-2xl p-4 theme-text-main text-center font-mono text-sm disabled:opacity-50 outline-none focus:ring-2 transition-all shadow-sm"
                                            style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                        />
                                        <span className="theme-element theme-text-muted px-5 py-4 rounded-r-2xl border border-l-0 theme-border text-sm font-black shadow-sm">%</span>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="p-8 border-t theme-border theme-element flex justify-end gap-4 items-center">
                            {!modalConfig.unlocked ? (
                                <button onClick={desbloquearConfiguracion} className="text-[11px] font-black uppercase tracking-widest px-6 py-4 border-[1.5px] border-orange-500/50 text-orange-500 rounded-2xl hover:bg-orange-500/10 transition-all outline-none">
                                    Desbloquear Edición
                                </button>
                            ) : (
                                <button onClick={guardarConfiguracionGlobal} className="text-[11px] font-black uppercase tracking-widest px-8 py-4 text-white rounded-2xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
                                    Guardar Cambios
                                </button>
                            )}
                        </div>
                    </div>
                </div>,
                document.body
            )}

            {modalInconsistencias.show && createPortal(
                <div className="fixed inset-0 z-[140] flex items-center justify-center bg-black/80 backdrop-blur-xl p-4 animate-fade-in">
                    <div className="theme-surface border-2 border-orange-500/50 w-full max-w-4xl rounded-[2.5rem] shadow-[0_0_50px_rgba(249,115,22,0.2)] overflow-hidden flex flex-col max-h-[85vh]">
                        <div className="p-8 border-b border-orange-500/30 theme-element bg-orange-500/5">
                            <h2 className="text-lg font-black uppercase text-orange-500 flex items-center gap-3 tracking-widest italic">
                                <AlertTriangle className="w-6 h-6" /> Inconsistencias Wizerp_
                            </h2>
                            <p className="text-xs theme-text-muted mt-2 font-bold">Hemos detectado diferencias en los datos proporcionados.</p>
                        </div>

                        <div className="p-8 overflow-y-auto flex-1 custom-scrollbar">
                            <div className="border theme-border rounded-2xl overflow-hidden theme-surface shadow-sm">
                                <table className="w-full text-left">
                                    <thead className="theme-element theme-text-muted text-[10px] font-black uppercase tracking-widest">
                                        <tr>
                                            <th className="px-6 py-5 border-b theme-border">SKU</th>
                                            <th className="px-6 py-5 border-b theme-border">Descripción</th>
                                            <th className="px-6 py-5 border-b theme-border text-center">Existencia</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y theme-border text-xs theme-text-main font-mono">
                                        {modalInconsistencias.data.map((item, index) => (
                                            <tr key={index} className="hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                                <td className="px-6 py-4 text-indigo-500 font-bold">{item.sku}</td>
                                                <td className="px-6 py-4 truncate max-w-sm">{item.descripcion}</td>
                                                <td className="px-6 py-4 text-center text-orange-500 font-black">{item.existencia}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="p-8 border-t border-orange-500/30 theme-element bg-orange-500/5 flex justify-end gap-4">
                            <button onClick={() => setModalInconsistencias({ show: false, data: [] })} className="text-[11px] font-black uppercase tracking-widest px-6 py-4 theme-text-muted hover:theme-text-main transition-all outline-none rounded-2xl hover:bg-black/5 dark:hover:bg-white/5">
                                Cancelar
                            </button>
                            <button onClick={descargarTemporal} className="text-[11px] font-black uppercase tracking-widest px-8 py-4 bg-orange-500 hover:bg-orange-600 text-white rounded-2xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all outline-none flex items-center gap-2">
                                <Download className="w-4 h-4" /> Descargar de todos modos
                            </button>
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </AppLayout>
    );
}