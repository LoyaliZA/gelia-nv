import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_BTN_PRIMARY } from '@/utils/geliaTheme';
import { X, UploadCloud, FileSpreadsheet, CheckCircle2, AlertTriangle, ArrowRight } from 'lucide-react';

export default function WizardImportacion({ almacenes, onClose }) {
    const [step, setStep] = useState(1);
    const [file, setFile] = useState(null);
    const [almacenId, setAlmacenId] = useState('');
    const [headers, setHeaders] = useState([]);
    const [filePath, setFilePath] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [mapping, setMapping] = useState({
        sku: '',
        descripcion: '',
        categoria: '',
        marca: '',
        existencia: '',
        costo: '',
        costo_reposicion: '',
        precio_venta: '',
    });

    const labels = {
        sku: 'SKU del Producto *',
        descripcion: 'Descripción *',
        categoria: 'Categoría',
        marca: 'Marca',
        existencia: 'Existencia *',
        costo: 'Costo',
        costo_reposicion: 'Costo Reposición',
        precio_venta: 'Precio Venta',
    };

    const required = ['sku', 'descripcion', 'existencia'];

    const handleUpload = async () => {
        if (!file || !almacenId) {
            setError('Selecciona un almacén y un archivo.');
            return;
        }
        setError('');
        setLoading(true);
        const formData = new FormData();
        formData.append('archivo', file);
        formData.append('almacen_id', almacenId);
        try {
            const response = await axios.post(route('almacenes.inventarios.import_preview'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setHeaders(response.data.headers);
            setFilePath(response.data.file_path);
            const newMapping = { ...mapping };
            response.data.headers.forEach((h) => {
                const lower = String(h).toLowerCase();
                if (lower.includes('sku') || lower.includes('codigo')) newMapping.sku = newMapping.sku || h;
                if (lower.includes('desc') || lower.includes('nombre')) newMapping.descripcion = newMapping.descripcion || h;
                if (lower.includes('cat') || lower.includes('fam')) newMapping.categoria = newMapping.categoria || h;
                if (lower.includes('marca')) newMapping.marca = newMapping.marca || h;
                if (lower.includes('exis') || lower.includes('stock')) newMapping.existencia = newMapping.existencia || h;
                if (lower.includes('repos')) newMapping.costo_reposicion = newMapping.costo_reposicion || h;
                else if (lower.includes('costo')) newMapping.costo = newMapping.costo || h;
                if (lower.includes('precio') || lower.includes('venta')) newMapping.precio_venta = newMapping.precio_venta || h;
            });
            setMapping(newMapping);
            setStep(2);
        } catch (err) {
            setError(err.response?.data?.message || 'Error al leer el archivo.');
        } finally {
            setLoading(false);
        }
    };

    const processImport = () => {
        const missing = required.filter((k) => !mapping[k]);
        if (missing.length) {
            setError('Mapea las columnas obligatorias: SKU, descripción y existencia.');
            return;
        }
        setLoading(true);
        router.post(route('almacenes.inventarios.import_process'), {
            file_path: filePath,
            almacen_id: almacenId,
            mapping,
        }, {
            onSuccess: () => onClose(),
            onError: () => { setError('Error en la importación.'); setLoading(false); },
        });
    };

    return createPortal(
        <div className={THEME_MODAL_OVERLAY} onClick={onClose}>
            <div className={`${THEME_MODAL_SHELL} max-w-2xl text-left modal-pop`} onClick={(e) => e.stopPropagation()}>
                <div className="p-6 border-b theme-border flex justify-between items-center">
                    <h2 className="text-xl font-black italic uppercase theme-text-main flex items-center gap-2">
                        <FileSpreadsheet className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        Importar Inventario
                    </h2>
                    <button onClick={onClose}><X className="w-6 h-6" /></button>
                </div>
                <div className="p-6">
                    {error && (
                        <div className="mb-4 p-3 rounded-lg bg-red-500/10 text-red-500 text-[11px] font-bold flex gap-2">
                            <AlertTriangle className="w-4 h-4 shrink-0" /> {error}
                        </div>
                    )}
                    {step === 1 && (
                        <div className="space-y-6">
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Almacén destino</label>
                                <select value={almacenId} onChange={(e) => setAlmacenId(e.target.value)} className="theme-input w-full mt-2 px-4 py-3 font-bold text-[11px] uppercase">
                                    <option value="">Selecciona...</option>
                                    {almacenes.map((a) => <option key={a.id} value={a.id}>{a.codigo} - {a.nombre}</option>)}
                                </select>
                            </div>
                            <label className="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed theme-border rounded-xl cursor-pointer">
                                <UploadCloud className="w-8 h-8 theme-text-muted mb-2" />
                                <span className="text-[11px] font-bold">{file ? file.name : 'Seleccionar Excel/CSV'}</span>
                                <input type="file" className="hidden" accept=".xlsx,.xls,.csv" onChange={(e) => setFile(e.target.files[0])} />
                            </label>
                            <button onClick={handleUpload} disabled={loading || !file || !almacenId} className={`${THEME_BTN_PRIMARY} w-full py-3 disabled:opacity-50`}>
                                {loading ? 'Leyendo...' : 'Siguiente: Mapear'}
                            </button>
                        </div>
                    )}
                    {step === 2 && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {Object.keys(mapping).map((key) => (
                                    <div key={key}>
                                        <label className="text-[10px] font-black uppercase">{labels[key]}</label>
                                        <select value={mapping[key]} onChange={(e) => setMapping({ ...mapping, [key]: e.target.value })} className="theme-input w-full mt-1 px-3 py-2 text-[11px] font-bold">
                                            <option value="">— No mapear —</option>
                                            {headers.map((h, i) => <option key={i} value={h}>{h}</option>)}
                                        </select>
                                    </div>
                                ))}
                            </div>
                            <div className="flex gap-3 pt-4 border-t theme-border">
                                <button onClick={() => setStep(1)} className="flex-1 py-3 text-[11px] font-black uppercase rounded-xl theme-element border theme-border">Volver</button>
                                <button onClick={() => { if (required.some((k) => !mapping[k])) { setError('Faltan columnas obligatorias.'); return; } setError(''); setStep(3); }} className={`${THEME_BTN_PRIMARY} flex-1 py-3`}>Confirmar</button>
                            </div>
                        </div>
                    )}
                    {step === 3 && (
                        <div className="space-y-6">
                            <p className="text-[11px] font-bold theme-text-muted">Se importarán productos, existencias y costos (si se mapearon) al almacén seleccionado.</p>
                            <button onClick={processImport} disabled={loading} className={`${THEME_BTN_PRIMARY} w-full py-3 flex justify-center gap-2`}>
                                {loading ? 'Procesando...' : <><ArrowRight className="w-4 h-4" /> Procesar importación</>}
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
