import React, { useEffect, useRef, useState } from 'react';
import { UploadCloud, Eye, Download, CloudUpload, Info, CheckSquare, Square } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import GeliaLoader from '../../../Components/GeliaLoader';
import ModalPrevisualizacion from './ModalPrevisualizacion';
import ModalMapeoPrecios from './ModalMapeoPrecios';
import ModalProgresoGenerarCsv from './ModalProgresoGenerarCsv';
import { startWooSyncTracking } from '../../../utils/woocommerceSyncTracker';

export default function GeneradorSync({ permisos, configuracion, margenes, onTemplateGenerado }) {
    const fileRef = useRef(null);
    const [archivo, setArchivo] = useState(null);
    const [procesando, setProcesando] = useState(false);
    const [errorMsg, setErrorMsg] = useState(null);
    const [successMsg, setSuccessMsg] = useState(null);
    const [previewData, setPreviewData] = useState(null);
    const [mapeoModal, setMapeoModal] = useState(null);
    const [ultimoMapeo, setUltimoMapeo] = useState(null);
    const [exportCsvLog, setExportCsvLog] = useState(null);
    const [columnasExport, setColumnasExport] = useState({
        sku: true,
        nombre: true,
        precio_rebajado: true,
        precio_normal: true,
    });

    const OPCIONES_COLUMNAS_CSV = [
        { clave: 'sku', label: 'SKU', fija: true },
        { clave: 'nombre', label: 'Nombre' },
        { clave: 'precio_rebajado', label: 'Precio rebaja' },
        { clave: 'precio_normal', label: 'Precio normal' },
    ];

    const columnasExportSeleccionadas = () =>
        OPCIONES_COLUMNAS_CSV.filter((op) => columnasExport[op.clave]).map((op) => op.clave);

    const toggleColumnaExport = (clave) => {
        if (clave === 'sku') return;
        setColumnasExport((prev) => {
            const siguiente = { ...prev, [clave]: !prev[clave] };
            const activas = OPCIONES_COLUMNAS_CSV.filter((op) => siguiente[op.clave] && op.clave !== 'sku');
            if (activas.length === 0) return prev;
            return siguiente;
        });
    };

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    useEffect(() => {
        if (!exportCsvLog?.id) return undefined;
        if (['completado', 'error', 'cancelado'].includes(exportCsvLog.estado)) return undefined;

        const poll = async () => {
            try {
                const res = await fetch(route('woocommerce.progreso', exportCsvLog.id), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) return;
                const data = await res.json();
                setExportCsvLog(data);
                if (data.estado === 'completado') {
                    onTemplateGenerado?.();
                    if (data.payload?.resultado?.message) {
                        setSuccessMsg(data.payload.resultado.message);
                    }
                }
                if (data.estado === 'error') {
                    setErrorMsg(data.mensaje_error || 'Error al generar el CSV.');
                }
            } catch {
                // siguiente tick
            }
        };

        const iv = setInterval(poll, 600);
        poll();

        return () => clearInterval(iv);
    }, [exportCsvLog?.id, exportCsvLog?.estado, onTemplateGenerado]);

    const postConMapeo = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const rawBody = await response.text();
        let data = {};
        try {
            data = rawBody ? JSON.parse(rawBody) : {};
        } catch {
            if (!response.ok && rawBody) {
                data.message = `Error del servidor (${response.status}). Respuesta no JSON.`;
            }
        }

        if (!response.ok) {
            throw new Error(data.message || `Error del servidor (${response.status}).`);
        }

        return data;
    };

    const ejecutarConMapeo = async (modo, payload) => {
        setMapeoModal(null);
        setUltimoMapeo(payload);
        setErrorMsg(null);
        setSuccessMsg(null);
        try {
            if (modo === 'local') {
                const data = await postConMapeo(route('woocommerce.procesar'), {
                    ...payload,
                    columnas_export: columnasExportSeleccionadas(),
                });
                setExportCsvLog({
                    id: data.log_id,
                    estado: 'pendiente',
                    procesados: 0,
                    total_productos: 1,
                    payload: { fase: 'iniciando' },
                });
            } else if (modo === 'previsualizar') {
                setProcesando(true);
                const data = await postConMapeo(route('woocommerce.previsualizar'), payload);
                setPreviewData(data.detalles);
            } else if (modo === 'nube') {
                setProcesando(true);
                const data = await postConMapeo(route('woocommerce.sincronizar'), payload);
                startWooSyncTracking(data.log_id);
            }
        } catch (e) {
            setErrorMsg(e.message);
            setExportCsvLog(null);
        } finally {
            if (modo !== 'local') setProcesando(false);
        }
    };

    const cerrarModalExportCsv = () => {
        setExportCsvLog(null);
        setProcesando(false);
    };

    const solicitarMapeo = (modo) => {
        if (!archivo) {
            setErrorMsg('Selecciona el Excel de Lista de Resurtido o Wizerp primero.');
            return;
        }
        setErrorMsg(null);
        if (ultimoMapeo?.file_path && ultimoMapeo?.mapping) {
            ejecutarConMapeo(modo, ultimoMapeo);
            return;
        }
        setMapeoModal({ modo });
    };

    if (!permisos.sincronizar) return null;

    return (
        <div className={`${geliaCardClass()} p-6 md:p-8 flex flex-col gap-6 relative`}>
            <GeliaLoader isVisible={procesando} message="Procesando precios_" />

            <ModalProgresoGenerarCsv log={exportCsvLog} onClose={cerrarModalExportCsv} />

            <h2 className="text-xl font-black uppercase tracking-tight theme-text-main border-b theme-border pb-4">
                Sincronización de Precios
            </h2>

            {errorMsg && (
                <div className="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-sm font-bold flex items-center gap-2">
                    <Info className="w-5 h-5 shrink-0" /> {errorMsg}
                </div>
            )}

            {successMsg && (
                <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 text-sm font-bold flex items-center gap-2">
                    <Info className="w-5 h-5 shrink-0" /> {successMsg}
                </div>
            )}

            {!configuracion.credenciales_configuradas && (
                <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-600 text-xs font-bold">
                    Configura URL y credenciales REST de WooCommerce para sincronizar a la nube.
                </div>
            )}

            <div
                className={`border-2 border-dashed rounded-2xl p-6 text-center cursor-pointer transition-all ${archivo ? 'bg-black/5 dark:bg-white/5' : 'theme-border hover:bg-black/5 dark:hover:bg-white/5'}`}
                style={archivo ? { borderColor: 'var(--color-primario)' } : {}}
                onClick={() => fileRef.current?.click()}
            >
                <input
                    ref={fileRef}
                    type="file"
                    className="hidden"
                    accept=".xlsx,.xls"
                    onChange={(e) => {
                        setArchivo(e.target.files[0] || null);
                        setPreviewData(null);
                        setUltimoMapeo(null);
                        setErrorMsg(null);
                        setSuccessMsg(null);
                    }}
                />
                <UploadCloud className="w-10 h-10 mx-auto mb-3 theme-text-muted" style={archivo ? { color: 'var(--color-primario)' } : {}} />
                <h4 className="text-sm font-black uppercase theme-text-main">Lista de Resurtido / Wizerp</h4>
                <p className="text-[10px] font-bold theme-text-muted mt-1 uppercase">
                    {archivo ? archivo.name : 'Selecciona el Excel y mapea SKU + Precio base'}
                </p>
            </div>

            <div className="rounded-xl border theme-border p-4 space-y-3">
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                    Columnas del CSV manual (solo productos con cambio de precio)
                </p>
                <div className="flex flex-wrap gap-3">
                    {OPCIONES_COLUMNAS_CSV.map((op) => {
                        const activa = columnasExport[op.clave];
                        const Icon = activa ? CheckSquare : Square;
                        return (
                            <button
                                key={op.clave}
                                type="button"
                                disabled={op.fija}
                                onClick={() => toggleColumnaExport(op.clave)}
                                className={`inline-flex items-center gap-2 px-3 py-2 rounded-lg border text-[10px] font-bold uppercase tracking-wide transition-all ${
                                    activa ? 'border-[var(--color-primario)] theme-text-main' : 'theme-border theme-text-muted opacity-60'
                                } ${op.fija ? 'cursor-default opacity-100' : 'hover:border-[var(--color-primario)]'}`}
                            >
                                <Icon className="w-4 h-4 shrink-0" style={activa ? { color: 'var(--color-primario)' } : {}} />
                                {op.label}
                            </button>
                        );
                    })}
                </div>
                <p className="text-[10px] theme-text-muted">
                    Al generar el CSV también se actualizan los precios en la base de datos de GELIANV.
                </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                <button type="button" onClick={() => solicitarMapeo('previsualizar')} disabled={!archivo || procesando}
                    className="py-3 rounded-xl border theme-border font-black text-[10px] uppercase tracking-widest flex items-center justify-center gap-2 hover:border-[var(--color-primario)] transition-all disabled:opacity-50">
                    <Eye className="w-4 h-4" /> Previsualizar
                </button>
                <button type="button" onClick={() => solicitarMapeo('local')} disabled={!archivo || procesando}
                    className="py-3 rounded-xl border theme-border font-black text-[10px] uppercase tracking-widest flex items-center justify-center gap-2 hover:border-[var(--color-primario)] transition-all disabled:opacity-50">
                    <Download className="w-4 h-4" /> Generar CSV
                </button>
                <button type="button" onClick={() => solicitarMapeo('nube')} disabled={!archivo || procesando || !configuracion.credenciales_configuradas}
                    className="py-3 rounded-xl font-black text-[10px] uppercase tracking-widest text-white flex items-center justify-center gap-2 disabled:opacity-50"
                    style={{ backgroundColor: 'var(--color-primario)' }}>
                    <CloudUpload className="w-4 h-4" /> Sync WooCommerce
                </button>
            </div>

            {mapeoModal && (
                <ModalMapeoPrecios
                    archivo={archivo}
                    configuracion={configuracion}
                    margenes={margenes}
                    permisos={permisos}
                    modo={mapeoModal.modo}
                    onClose={() => setMapeoModal(null)}
                    onConfirm={(payload) => ejecutarConMapeo(mapeoModal.modo, {
                        file_path: payload.file_path,
                        mapping: payload.mapping,
                    })}
                />
            )}

            {previewData && (
                <ModalPrevisualizacion
                    detalles={previewData}
                    onClose={() => setPreviewData(null)}
                    onConfirm={() => {
                        setPreviewData(null);
                        solicitarMapeo('nube');
                    }}
                />
            )}
        </div>
    );
}
