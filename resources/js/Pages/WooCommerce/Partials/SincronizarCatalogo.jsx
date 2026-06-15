import React, { useRef, useState } from 'react';
import { UploadCloud, Database, Download, Loader2 } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import ModalProgreso from './ModalProgreso';

export default function SincronizarCatalogo({ permisos, configuracion }) {
    const csvRef = useRef(null);
    const preciosRef = useRef(null);
    const [csvFile, setCsvFile] = useState(null);
    const [preciosFile, setPreciosFile] = useState(null);
    const [loading, setLoading] = useState(false);
    const [msg, setMsg] = useState(null);
    const [fetchLogId, setFetchLogId] = useState(null);

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const uploadCsv = async () => {
        if (!csvFile) return;
        setLoading(true);
        setMsg(null);
        const fd = new FormData();
        fd.append('woocommerce_csv', csvFile);
        try {
            const res = await fetch(route('woocommerce.catalogo.sincronizar'), {
                method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            const data = await res.json();
            setMsg(res.ok ? { ok: true, text: data.message } : { ok: false, text: data.message });
            if (res.ok) window.location.reload();
        } catch (e) { setMsg({ ok: false, text: e.message }); }
        finally { setLoading(false); }
    };

    const uploadPreciosLocales = async () => {
        if (!preciosFile) return;
        setLoading(true);
        const fd = new FormData();
        fd.append('archivo_precios_locales', preciosFile);
        try {
            const res = await fetch(route('woocommerce.precios_locales'), {
                method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            const data = await res.json();
            setMsg(res.ok ? { ok: true, text: data.message } : { ok: false, text: data.message });
        } catch (e) { setMsg({ ok: false, text: e.message }); }
        finally { setLoading(false); }
    };

    const fetchPrecios = async () => {
        setLoading(true);
        try {
            const res = await fetch(route('woocommerce.fetch_precios'), {
                method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            const data = await res.json();
            if (res.ok) setFetchLogId(data.log_id);
            else setMsg({ ok: false, text: data.message });
        } catch (e) { setMsg({ ok: false, text: e.message }); }
        finally { setLoading(false); }
    };

    if (!permisos.sincronizar) return null;

    return (
        <div className={`${geliaCardClass()} p-6 md:p-8 flex flex-col gap-6`}>
            <h2 className="text-xl font-black uppercase tracking-tight theme-text-main border-b theme-border pb-4 flex items-center gap-2">
                <Database className="w-5 h-5" style={{ color: 'var(--color-primario)' }} /> Estructura y Precios Locales
            </h2>

            {msg && (
                <div className={`p-3 rounded-xl text-xs font-bold ${msg.ok ? 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20' : 'bg-red-500/10 text-red-500 border border-red-500/20'}`}>
                    {msg.text}
                </div>
            )}

            <div>
                <p className="text-[10px] font-black uppercase theme-text-muted mb-2">CSV Estructura WooCommerce</p>
                <div className="border-2 border-dashed theme-border rounded-2xl p-4 text-center cursor-pointer hover:border-[var(--color-primario)]"
                    onClick={() => csvRef.current?.click()}>
                    <input ref={csvRef} type="file" className="hidden" accept=".csv" onChange={(e) => setCsvFile(e.target.files[0])} />
                    <UploadCloud className="w-8 h-8 mx-auto mb-2 theme-text-muted" />
                    <p className="text-xs font-bold theme-text-main">{csvFile ? csvFile.name : 'ID, SKU, Nombre, Tipo, Superior'}</p>
                </div>
                <button onClick={uploadCsv} disabled={!csvFile || loading}
                    className="mt-3 w-full py-3 rounded-xl font-black text-[10px] uppercase tracking-widest text-white disabled:opacity-50"
                    style={{ backgroundColor: 'var(--color-primario)' }}>
                    {loading ? 'Importando...' : 'Actualizar Catálogo Local'}
                </button>
            </div>

            <div className="border-t theme-border pt-6">
                <p className="text-[10px] font-black uppercase theme-text-muted mb-2">CSV Precios GELIANV (local)</p>
                <div className="border-2 border-dashed theme-border rounded-2xl p-4 text-center cursor-pointer"
                    onClick={() => preciosRef.current?.click()}>
                    <input ref={preciosRef} type="file" className="hidden" accept=".csv" onChange={(e) => setPreciosFile(e.target.files[0])} />
                    <p className="text-xs font-bold theme-text-main">{preciosFile ? preciosFile.name : 'SKU, Precio normal, Precio rebajado'}</p>
                </div>
                <button onClick={uploadPreciosLocales} disabled={!preciosFile || loading}
                    className="mt-3 w-full py-3 rounded-xl border theme-border font-black text-[10px] uppercase tracking-widest theme-text-main">
                    Actualizar Precios Internos
                </button>
            </div>

            <div className="border-t theme-border pt-6">
                <p className="text-[10px] font-black uppercase theme-text-muted mb-2">Fetch API (precios en vivo)</p>
                <button onClick={fetchPrecios} disabled={loading || !configuracion.credenciales_configuradas}
                    className="w-full py-3 rounded-xl border theme-border font-black text-[10px] uppercase tracking-widest flex items-center justify-center gap-2 disabled:opacity-50">
                    {loading ? <Loader2 className="w-4 h-4 animate-spin" /> : <Download className="w-4 h-4" />}
                    Descargar Precios desde WooCommerce
                </button>
            </div>

            {fetchLogId && <ModalProgreso logId={fetchLogId} onClose={() => { setFetchLogId(null); window.location.reload(); }} />}
        </div>
    );
}
