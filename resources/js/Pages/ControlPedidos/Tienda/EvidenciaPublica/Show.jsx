import React, { useEffect, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { compressImageToWebp, validateImageSource } from '../../../../utils/compressImage';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';

export default function EvidenciaPublicaShow({
    codigo = '',
    error = null,
    folio = '',
    estado = '',
    expira_en = null,
    productos = [],
    fotos_count = 0,
}) {
    const [count, setCount] = useState(fotos_count);
    const [msg, setMsg] = useState(error || '');
    const [subiendo, setSubiendo] = useState(false);
    const camaraRef = useRef(null);

    useEffect(() => {
        if (error || !codigo) return undefined;
        const t = window.setInterval(async () => {
            try {
                const { data } = await axios.get(`/tienda-evidencia/${codigo}/estado`);
                setCount(data.fotos_count || 0);
            } catch (e) {
                const texto = e.response?.data?.errors?.codigo?.[0];
                if (texto) setMsg(texto);
            }
        }, 2000);
        return () => window.clearInterval(t);
    }, [codigo, error]);

    const tomar = async (file) => {
        if (!file || !codigo) return;
        const err = validateImageSource(file, 'Foto');
        if (err) { setMsg(err); return; }
        setSubiendo(true);
        setMsg('');
        try {
            const comprimida = await compressImageToWebp(file);
            const form = new FormData();
            form.append('foto', comprimida);
            await axios.post(`/tienda-evidencia/${codigo}/fotos`, form);
            setCount((c) => c + 1);
        } catch (e) {
            setMsg(e.response?.data?.message || 'No se pudo subir la foto.');
        } finally {
            setSubiendo(false);
        }
    };

    if (error) {
        return (
            <div className="gelia-pedidos-bma gelia-pedidos-captura min-h-screen px-4 py-10">
                <Head title="Evidencias Tienda" />
                <div className={`mx-auto max-w-lg ${geliaCardClass()} p-6`}>
                    <p className="text-xs font-semibold tracking-[0.35em] m-0" style={{ color: 'var(--color-primario)' }}>GELIA</p>
                    <h1 className="mt-2 text-2xl font-semibold theme-text-main m-0">Sesión no disponible</h1>
                    <p className="mt-3 text-sm theme-text-muted m-0">{error}</p>
                </div>
            </div>
        );
    }

    return (
        <div className="gelia-pedidos-bma gelia-pedidos-captura min-h-screen px-4 py-8 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
            <Head title={`Evidencias Tienda ${folio || ''}`} />
            <div className={`mx-auto max-w-lg ${geliaCardClass()} p-6 space-y-4`}>
                <p className="text-xs font-semibold tracking-[0.35em] m-0" style={{ color: 'var(--color-primario)' }}>GELIA · TIENDA</p>
                <h1 className="text-lg font-black theme-text-main m-0">Evidencia — Tienda</h1>
                {folio && <p className="text-sm theme-text-muted m-0">Pedido {folio}</p>}
                <p className="text-xs theme-text-muted m-0">Estado: {estado} · Fotos: {count}</p>
                {expira_en && <p className="text-xs theme-text-muted m-0">Expira: {new Date(expira_en).toLocaleTimeString('es-MX')}</p>}
                <input ref={camaraRef} type="file" accept="image/*" capture="environment" className="hidden"
                    onChange={(e) => tomar(e.target.files?.[0])} />
                <button type="button" className={THEME_BTN_PRIMARY} disabled={subiendo}
                    onClick={() => camaraRef.current?.click()}>
                    {subiendo ? 'Subiendo…' : 'Tomar / subir foto'}
                </button>
                {msg && <p role="alert" className="text-sm font-semibold theme-text-peligro m-0">{msg}</p>}
            </div>
        </div>
    );
}
