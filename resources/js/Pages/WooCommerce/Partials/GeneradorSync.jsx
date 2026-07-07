import React, { useRef, useState } from 'react';
import { UploadCloud, Eye, Download, CloudUpload, Info } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import GeliaLoader from '../../../Components/GeliaLoader';
import ModalPrevisualizacion from './ModalPrevisualizacion';
import ModalMapeoPrecios from './ModalMapeoPrecios';
import { startWooSyncTracking } from '../../../utils/woocommerceSyncTracker';

export default function GeneradorSync({ permisos, configuracion, margenes, onTemplateGenerado }) {
    const fileRef = useRef(null);
    const [archivo, setArchivo] = useState(null);
    const [procesando, setProcesando] = useState(false);
    const [errorMsg, setErrorMsg] = useState(null);
    const [previewData, setPreviewData] = useState(null);
    const [mapeoModal, setMapeoModal] = useState(null);
    const [ultimoMapeo, setUltimoMapeo] = useState(null);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

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
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Error del servidor.');
        return data;
    };

    const ejecutarConMapeo = async (modo, payload) => {
        setMapeoModal(null);
        setUltimoMapeo(payload);
        setProcesando(true);
        setErrorMsg(null);
        try {
            if (modo === 'local') {
                const data = await postConMapeo(route('woocommerce.procesar'), payload);
                const a = document.createElement('a');
                a.href = data.download_url;
                a.click();
                onTemplateGenerado?.();
            } else if (modo === 'previsualizar') {
                const data = await postConMapeo(route('woocommerce.previsualizar'), payload);
                setPreviewData(data.detalles);
            } else if (modo === 'nube') {
                const data = await postConMapeo(route('woocommerce.sincronizar'), payload);
                startWooSyncTracking(data.log_id);
            }
        } catch (e) {
            setErrorMsg(e.message);
        } finally {
            setProcesando(false);
        }
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

            <h2 className="text-xl font-black uppercase tracking-tight theme-text-main border-b theme-border pb-4">
                Sincronización de Precios
            </h2>

            {errorMsg && (
                <div className="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-sm font-bold flex items-center gap-2">
                    <Info className="w-5 h-5 shrink-0" /> {errorMsg}
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
                    }}
                />
                <UploadCloud className="w-10 h-10 mx-auto mb-3 theme-text-muted" style={archivo ? { color: 'var(--color-primario)' } : {}} />
                <h4 className="text-sm font-black uppercase theme-text-main">Lista de Resurtido / Wizerp</h4>
                <p className="text-[10px] font-bold theme-text-muted mt-1 uppercase">
                    {archivo ? archivo.name : 'Selecciona el Excel y mapea SKU + Precio base'}
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
