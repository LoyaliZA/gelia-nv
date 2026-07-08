import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { startImportacionAlmacenTracking } from '@/utils/importacionAlmacenTracker';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_BTN_PRIMARY } from '@/utils/geliaTheme';
import InstruccionesImportacion from '@/Components/Catalogos/InstruccionesImportacion';
import ImportacionResumenModal from '@/Components/Almacenes/ImportacionResumenModal';
import { X, UploadCloud, FileSpreadsheet, AlertTriangle, ArrowRight } from 'lucide-react';

function autoMapearHeaders(headers, mapping) {
    const newMapping = { ...mapping };
    headers.forEach((h) => {
        const lower = String(h).toLowerCase();
        if (lower.includes('folio') && !newMapping.folio) newMapping.folio = h;
        if ((lower.includes('sku') || (lower.includes('codigo') && !lower.includes('barras'))) && !newMapping.sku) newMapping.sku = h;
        if ((lower.includes('desc') || lower.includes('nombre')) && !newMapping.descripcion) newMapping.descripcion = h;
        if ((lower.includes('cat') || lower.includes('fam')) && !newMapping.categoria) newMapping.categoria = h;
        if (lower.includes('marca') && !newMapping.marca) newMapping.marca = h;
        if ((lower.includes('exis') || lower.includes('stock')) && !newMapping.existencia) newMapping.existencia = h;
        if (lower.includes('repos') && !newMapping.costo_reposicion) newMapping.costo_reposicion = h;
        else if (lower.includes('costo') && !newMapping.costo) newMapping.costo = h;
        if ((lower.includes('precio') || lower.includes('venta')) && !newMapping.precio_venta) newMapping.precio_venta = h;
        if (lower.includes('barras') && !newMapping.codigo_barras) newMapping.codigo_barras = h;
        if (lower.includes('peso') && !newMapping.peso) newMapping.peso = h;
        if (lower.includes('activ') && !newMapping.activo) newMapping.activo = h;
    });
    return newMapping;
}

export default function WizardImportacionCatalogo({ config, almacenes = [], onClose }) {
    const { flash } = usePage().props;
    const [step, setStep] = useState(1);
    const [file, setFile] = useState(null);
    const [almacenId, setAlmacenId] = useState('');
    const [headers, setHeaders] = useState([]);
    const [filePath, setFilePath] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [mapping, setMapping] = useState({ ...config.mapping });
    const [resumen, setResumen] = useState(null);

    useEffect(() => {
        if (flash?.reporte_importacion_almacenes) {
            setResumen(flash.reporte_importacion_almacenes);
        }
    }, [flash]);

    const labels = config.labels;
    const required = config.required;

    const descargarPlantilla = () => {
        window.location.href = route(config.rutaPlantilla);
    };

    const handleUpload = async () => {
        if (!file || (config.requiereAlmacen && !almacenId)) {
            setError(config.requiereAlmacen ? 'Selecciona un almacén y un archivo.' : 'Selecciona un archivo.');
            return;
        }
        setError('');
        setLoading(true);
        const formData = new FormData();
        formData.append('archivo', file);
        if (config.requiereAlmacen) {
            formData.append('almacen_id', almacenId);
        }
        try {
            const response = await axios.post(route(config.rutaPreview), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setHeaders(response.data.headers);
            setFilePath(response.data.file_path);
            setMapping(autoMapearHeaders(response.data.headers, mapping));
            setStep(2);
        } catch (err) {
            setError(err.response?.data?.message || 'Error al leer el archivo.');
        } finally {
            setLoading(false);
        }
    };

    const confirmarMapeo = () => {
        if (required.some((k) => !mapping[k])) {
            setError('Faltan columnas obligatorias.');
            return;
        }
        setError('');
        setStep(3);
    };

    const processImport = async () => {
        const missing = required.filter((k) => !mapping[k]);
        if (missing.length) {
            setError(`Mapea las columnas obligatorias: ${missing.join(', ')}.`);
            return;
        }
        setLoading(true);
        setError('');
        const payload = { file_path: filePath, mapping };
        if (config.requiereAlmacen) {
            payload.almacen_id = almacenId;
        }
        try {
            const response = await axios.post(route(config.rutaIniciar), payload);
            if (response.data?.log_id) {
                startImportacionAlmacenTracking(response.data.log_id);
                onClose();
            } else {
                setError('No se pudo iniciar la importación.');
            }
        } catch (err) {
            setError(err.response?.data?.message || 'Error al iniciar la importación.');
        } finally {
            setLoading(false);
        }
    };

    const cerrarResumen = () => {
        setResumen(null);
        onClose();
    };

    if (resumen) {
        return <ImportacionResumenModal data={resumen} onClose={cerrarResumen} />;
    }

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg sm:max-w-xl w-full text-left modal-pop`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex justify-between items-center gap-3 p-5 md:p-6 border-b theme-border shrink-0">
                    <h2 className="text-lg md:text-xl font-black italic uppercase theme-text-main flex items-center gap-2 m-0 leading-tight">
                        <FileSpreadsheet className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        {config.titulo}
                    </h2>
                    <button type="button" onClick={onClose} className="p-2 hover:bg-black/5 dark:hover:bg-white/5 rounded-full shrink-0">
                        <X className="w-5 h-5 theme-text-muted" />
                    </button>
                </div>

                <div className="gelia-modal-body p-5 md:p-6 custom-scrollbar">
                    {error && (
                        <div className="mb-4 p-3 rounded-lg bg-red-500/10 text-red-600 dark:text-red-400 text-[11px] font-bold flex gap-2">
                            <AlertTriangle className="w-4 h-4 shrink-0" /> {error}
                        </div>
                    )}

                    {step === 1 && (
                        <div className="space-y-5">
                            <div className="flex justify-end">
                                <button
                                    type="button"
                                    onClick={descargarPlantilla}
                                    className="px-4 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl flex items-center gap-2 theme-text-main"
                                >
                                    <FileSpreadsheet className="w-4 h-4" /> Descargar plantilla
                                </button>
                            </div>
                            {config.requiereAlmacen && (
                                <div>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">Almacén destino</label>
                                    <select value={almacenId} onChange={(e) => setAlmacenId(e.target.value)} className="theme-input w-full mt-2 px-4 py-3 font-bold text-[11px] uppercase">
                                        <option value="">Selecciona...</option>
                                        {almacenes.map((a) => <option key={a.id} value={a.id}>{a.codigo} - {a.nombre}</option>)}
                                    </select>
                                </div>
                            )}
                            <label className="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed theme-border rounded-xl cursor-pointer theme-element">
                                <UploadCloud className="w-8 h-8 theme-text-muted mb-2" />
                                <span className="text-[11px] font-bold theme-text-main text-center px-4">{file ? file.name : 'Seleccionar Excel/CSV'}</span>
                                <input type="file" className="hidden" accept=".xlsx,.xls,.csv" onChange={(e) => setFile(e.target.files[0])} />
                            </label>
                            <InstruccionesImportacion columnas={config.columnas} notas={config.notas} />
                        </div>
                    )}

                    {step === 2 && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {Object.keys(mapping).map((key) => (
                                <div key={key}>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">{labels[key]}</label>
                                    <select value={mapping[key]} onChange={(e) => setMapping({ ...mapping, [key]: e.target.value })} className="theme-input w-full mt-1 px-3 py-2 text-[11px] font-bold">
                                        <option value="">— No mapear —</option>
                                        {headers.map((h, i) => <option key={i} value={h}>{h}</option>)}
                                    </select>
                                </div>
                            ))}
                        </div>
                    )}

                    {step === 3 && (
                        <p className="text-[11px] font-bold theme-text-muted m-0">
                            Revisa el mapeo y confirma para procesar la importación.
                        </p>
                    )}
                </div>

                <div className="gelia-modal-footer p-5 md:p-6">
                    {step === 1 && (
                        <button
                            type="button"
                            onClick={handleUpload}
                            disabled={loading || !file || (config.requiereAlmacen && !almacenId)}
                            className={`${THEME_BTN_PRIMARY} w-full py-3 disabled:opacity-50`}
                        >
                            {loading ? 'Leyendo...' : 'Siguiente: Mapear'}
                        </button>
                    )}
                    {step === 2 && (
                        <div className="flex gap-3">
                            <button type="button" onClick={() => setStep(1)} className="flex-1 py-3 text-[11px] font-black uppercase rounded-xl theme-element border theme-border theme-text-main">
                                Volver
                            </button>
                            <button type="button" onClick={confirmarMapeo} className={`${THEME_BTN_PRIMARY} flex-1 py-3`}>
                                Confirmar
                            </button>
                        </div>
                    )}
                    {step === 3 && (
                        <button type="button" onClick={processImport} disabled={loading} className={`${THEME_BTN_PRIMARY} w-full py-3 flex justify-center items-center gap-2`}>
                            {loading ? 'Iniciando...' : <><ArrowRight className="w-4 h-4" /> Procesar en segundo plano</>}
                        </button>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
