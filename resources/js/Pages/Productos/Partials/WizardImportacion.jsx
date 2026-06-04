import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_BTN_PRIMARY } from '@/utils/geliaTheme';
import { X, UploadCloud, FileSpreadsheet, CheckCircle2, AlertTriangle, ArrowRight } from 'lucide-react';

export default function WizardImportacion({ almacenes, onClose }) {
    const [step, setStep] = useState(1); // 1: Upload, 2: Map, 3: Confirm/Process
    const [file, setFile] = useState(null);
    const [almacenId, setAlmacenId] = useState('');
    
    // Preview data
    const [headers, setHeaders] = useState([]);
    const [filePath, setFilePath] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    // Mapping
    const [mapping, setMapping] = useState({
        sku: '',
        descripcion: '',
        categoria: '',
        existencia: '',
        costo: '',
        precio_venta: ''
    });

    const labels = {
        sku: 'SKU del Producto',
        descripcion: 'Descripción Comercial',
        categoria: 'Categoría / Familia',
        existencia: 'Existencia Actual',
        costo: 'Costo Adquisición',
        precio_venta: 'Precio Venta Público'
    };

    const handleUpload = async () => {
        if (!file || !almacenId) {
            setError('Por favor selecciona un almacén y un archivo de Excel.');
            return;
        }
        setError('');
        setLoading(true);

        const formData = new FormData();
        formData.append('archivo', file);
        formData.append('almacen_id', almacenId);

        try {
            const response = await axios.post(route('admin.catalogo-maestro.import_preview'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            setHeaders(response.data.headers);
            setFilePath(response.data.file_path);
            
            // Try to auto-map based on common header names
            const newMapping = { ...mapping };
            const lowerHeaders = response.data.headers.map(h => String(h).toLowerCase());
            
            response.data.headers.forEach((h, index) => {
                const lower = String(h).toLowerCase();
                if (lower.includes('sku') || lower.includes('codigo')) newMapping.sku = newMapping.sku || h;
                if (lower.includes('desc') || lower.includes('nombre')) newMapping.descripcion = newMapping.descripcion || h;
                if (lower.includes('cat') || lower.includes('fam')) newMapping.categoria = newMapping.categoria || h;
                if (lower.includes('exis') || lower.includes('stock') || lower.includes('cant')) newMapping.existencia = newMapping.existencia || h;
                if (lower.includes('costo')) newMapping.costo = newMapping.costo || h;
                if (lower.includes('precio') || lower.includes('venta')) newMapping.precio_venta = newMapping.precio_venta || h;
            });
            setMapping(newMapping);
            
            setStep(2);
        } catch (err) {
            setError(err.response?.data?.message || 'Error al procesar el archivo. Asegúrate que sea un Excel válido.');
        } finally {
            setLoading(false);
        }
    };

    const processImport = () => {
        // Validate mapping
        const missing = Object.keys(mapping).filter(k => !mapping[k]);
        if (missing.length > 0) {
            setError('Por favor mapea todas las columnas obligatorias.');
            return;
        }

        setError('');
        setLoading(true);

        router.post(route('admin.catalogo-maestro.import_process'), {
            file_path: filePath,
            almacen_id: almacenId,
            mapping
        }, {
            onSuccess: () => onClose(),
            onError: (err) => {
                setError('Error en la importación. Revisa la consola o los logs del sistema.');
                setLoading(false);
            }
        });
    };

    return createPortal(
        <div className={THEME_MODAL_OVERLAY} onClick={onClose}>
            <div className={`${THEME_MODAL_SHELL} max-w-2xl text-left modal-pop`} onClick={e => e.stopPropagation()}>
                
                <div className="p-6 border-b theme-border flex justify-between items-center">
                    <h2 className="text-xl font-black italic uppercase theme-text-main flex items-center gap-2">
                        <FileSpreadsheet className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        Asistente de Importación
                    </h2>
                    <button onClick={onClose} className="theme-text-muted hover:theme-text-main">
                        <X className="w-6 h-6" />
                    </button>
                </div>

                <div className="p-6">
                    {/* Stepper */}
                    <div className="flex items-center justify-between mb-8 relative">
                        <div className="absolute left-0 top-1/2 -translate-y-1/2 w-full h-0.5 bg-black/10 dark:bg-white/10 -z-10" />
                        {[1, 2, 3].map(s => (
                            <div key={s} className={`flex items-center justify-center w-8 h-8 rounded-full font-black text-[11px] ${step >= s ? 'bg-[var(--color-primario)] text-white shadow-lg' : 'bg-gray-200 dark:bg-gray-800 theme-text-muted'}`}>
                                {step > s ? <CheckCircle2 className="w-4 h-4" /> : s}
                            </div>
                        ))}
                    </div>

                    {error && (
                        <div className="mb-4 p-3 rounded-lg bg-red-500/10 text-red-500 text-[11px] font-bold border border-red-500/20 flex items-start gap-2">
                            <AlertTriangle className="w-4 h-4 shrink-0" /> {error}
                        </div>
                    )}

                    {step === 1 && (
                        <div className="space-y-6">
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted">1. Seleccionar Almacén Destino</label>
                                <select 
                                    className="theme-input w-full px-4 py-3 font-bold text-[11px] uppercase"
                                    value={almacenId} onChange={e => setAlmacenId(e.target.value)}
                                >
                                    <option value="">Selecciona un almacén...</option>
                                    {almacenes.map(a => <option key={a.id} value={a.id}>{a.codigo} - {a.nombre}</option>)}
                                </select>
                            </div>
                            
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted">2. Archivo de Inventario (Excel/CSV)</label>
                                <label className="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed theme-border rounded-xl cursor-pointer hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                    <div className="flex flex-col items-center justify-center pt-5 pb-6">
                                        <UploadCloud className="w-8 h-8 theme-text-muted mb-2" />
                                        <p className="text-[11px] font-bold theme-text-main">{file ? file.name : 'Haz clic para seleccionar o arrastra aquí'}</p>
                                    </div>
                                    <input type="file" className="hidden" accept=".xlsx,.xls,.csv" onChange={e => setFile(e.target.files[0])} />
                                </label>
                            </div>

                            <button onClick={handleUpload} disabled={loading || !file || !almacenId} className={`${THEME_BTN_PRIMARY} w-full flex justify-center py-3 mt-4 disabled:opacity-50`}>
                                {loading ? 'Leyendo archivo...' : 'Siguiente: Mapear Columnas'}
                            </button>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="space-y-4">
                            <p className="text-[11px] font-bold theme-text-muted mb-4 uppercase tracking-widest">
                                Selecciona a qué columna del Excel corresponde cada campo del sistema.
                            </p>
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {Object.keys(mapping).map(key => (
                                    <div key={key} className="space-y-1">
                                        <label className="text-[10px] font-black uppercase tracking-widest theme-text-main">{labels[key]}</label>
                                        <select 
                                            value={mapping[key]} 
                                            onChange={e => setMapping({...mapping, [key]: e.target.value})}
                                            className="theme-input w-full px-3 py-2 text-[11px] font-bold"
                                        >
                                            <option value="">- Ignorar / No mapear -</option>
                                            {headers.map((h, i) => <option key={i} value={h}>{h}</option>)}
                                        </select>
                                    </div>
                                ))}
                            </div>

                            <div className="flex gap-3 mt-6 pt-6 border-t theme-border">
                                <button onClick={() => setStep(1)} className="flex-1 py-3 text-[11px] font-black uppercase rounded-xl theme-element border theme-border">Volver</button>
                                <button onClick={() => {
                                    const missing = Object.keys(mapping).filter(k => !mapping[k]);
                                    if(missing.length) { setError('Faltan columnas obligatorias por mapear.'); return; }
                                    setError(''); setStep(3);
                                }} className={`${THEME_BTN_PRIMARY} flex-1 py-3`}>
                                    Siguiente: Confirmar
                                </button>
                            </div>
                        </div>
                    )}

                    {step === 3 && (
                        <div className="space-y-6">
                            <div className="p-4 rounded-xl bg-orange-500/10 border border-orange-500/20 text-orange-500 space-y-2">
                                <h4 className="font-black uppercase flex items-center gap-2"><AlertTriangle className="w-5 h-5" /> Advertencia de Actualización</h4>
                                <p className="text-[11px] font-bold leading-relaxed">
                                    Esta acción aplicará la metodología de carga masiva para el almacén seleccionado:
                                </p>
                                <ul className="list-disc pl-5 text-[10px] font-bold space-y-1">
                                    <li>Todos los productos actuales en el almacén serán marcados como inactivos.</li>
                                    <li>Los productos que vengan en el Excel se actualizarán o crearán y se activarán.</li>
                                    <li>Cualquier producto que no esté en el Excel quedará desactivado.</li>
                                </ul>
                            </div>

                            <button onClick={processImport} disabled={loading} className={`${THEME_BTN_PRIMARY} w-full flex justify-center py-3 disabled:opacity-50`}>
                                {loading ? (
                                    <span className="flex items-center gap-2"><div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"/> Procesando...</span>
                                ) : (
                                    <span className="flex items-center gap-2"><ArrowRight className="w-4 h-4" /> Entendido, Procesar Importación</span>
                                )}
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
