import React, { useRef, useState } from 'react';
import { UploadCloud, Eye, Download, CloudUpload, Info } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import GeliaLoader from '../../../Components/GeliaLoader';
import ModalPrevisualizacion from './ModalPrevisualizacion';
import ModalProgreso from './ModalProgreso';

export default function GeneradorSync({ permisos, configuracion, onTemplateGenerado }) {
    const fileRef = useRef(null);
    const [archivo, setArchivo] = useState(null);
    const [procesando, setProcesando] = useState(false);
    const [errorMsg, setErrorMsg] = useState(null);
    const [previewData, setPreviewData] = useState(null);
    const [progresoId, setProgresoId] = useState(null);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const postArchivo = async (url) => {
        const formData = new FormData();
        formData.append('listado_aromas', archivo);
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Error del servidor.');
        return data;
    };

    const ejecutar = async (modo) => {
        if (!archivo) {
            setErrorMsg('Selecciona el Excel de Wizerp (Listado Aromas) primero.');
            return;
        }
        setErrorMsg(null);
        setProcesando(true);
        try {
            if (modo === 'local') {
                const data = await postArchivo(route('woocommerce.procesar'));
                const a = document.createElement('a');
                a.href = data.download_url;
                a.click();
                onTemplateGenerado?.();
            } else if (modo === 'previsualizar') {
                const data = await postArchivo(route('woocommerce.previsualizar'));
                setPreviewData(data.detalles);
            } else if (modo === 'nube') {
                const data = await postArchivo(route('woocommerce.sincronizar'));
                setProgresoId(data.log_id);
            }
        } catch (e) {
            setErrorMsg(e.message);
        } finally {
            setProcesando(false);
        }
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
                <input ref={fileRef} type="file" className="hidden" accept=".xlsx,.xls" onChange={(e) => setArchivo(e.target.files[0] || null)} />
                <UploadCloud className="w-10 h-10 mx-auto mb-3 theme-text-muted" style={archivo ? { color: 'var(--color-primario)' } : {}} />
                <h4 className="text-sm font-black uppercase theme-text-main">Wizerp Excel (Plantilla Resurtido)</h4>
                <p className="text-[10px] font-bold theme-text-muted mt-1 uppercase">{archivo ? archivo.name : 'Columnas requeridas: SKU y Plataforma (Precio)'}</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                <button type="button" onClick={() => ejecutar('previsualizar')} disabled={!archivo || procesando}
                    className="py-3 rounded-xl border theme-border font-black text-[10px] uppercase tracking-widest flex items-center justify-center gap-2 hover:border-[var(--color-primario)] transition-all disabled:opacity-50">
                    <Eye className="w-4 h-4" /> Previsualizar
                </button>
                <button type="button" onClick={() => ejecutar('local')} disabled={!archivo || procesando}
                    className="py-3 rounded-xl border theme-border font-black text-[10px] uppercase tracking-widest flex items-center justify-center gap-2 hover:border-[var(--color-primario)] transition-all disabled:opacity-50">
                    <Download className="w-4 h-4" /> Generar CSV
                </button>
                <button type="button" onClick={() => ejecutar('nube')} disabled={!archivo || procesando || !configuracion.credenciales_configuradas}
                    className="py-3 rounded-xl font-black text-[10px] uppercase tracking-widest text-white flex items-center justify-center gap-2 disabled:opacity-50"
                    style={{ backgroundColor: 'var(--color-primario)' }}>
                    <CloudUpload className="w-4 h-4" /> Sync WooCommerce
                </button>
            </div>

            {previewData && <ModalPrevisualizacion detalles={previewData} onClose={() => setPreviewData(null)} onConfirm={() => { setPreviewData(null); ejecutar('nube'); }} />}
            {progresoId && <ModalProgreso logId={progresoId} onClose={() => setProgresoId(null)} />}
        </div>
    );
}
