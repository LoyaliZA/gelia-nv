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
            <div className="min-h-screen px-4 py-10" style={{ background: 'var(--color-fondo, #f4f4f5)' }}>
                <Head title="Evidencias Tienda" />
                <div className={`mx-auto max-w-lg ${geliaCardClass()} p-6`}>
                    <p className="text-red-600 font-bold">{error}</p>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen px-4 py-8" style={{ background: 'var(--color-fondo, #f4f4f5)' }}>
            <Head title={`Evidencias Tienda ${folio || ''}`} />
            <div className={`mx-auto max-w-lg ${geliaCardClass()} p-6 space-y-4`}>
                <h1 className="text-lg font-black">Evidencia — Tienda</h1>
                {folio && <p className="text-sm theme-text-muted">Pedido {folio}</p>}
                <p className="text-xs">Estado: {estado} · Fotos: {count}</p>
                {expira_en && <p className="text-xs theme-text-muted">Expira: {new Date(expira_en).toLocaleTimeString('es-MX')}</p>}
                <input ref={camaraRef} type="file" accept="image/*" capture="environment" className="hidden"
                    onChange={(e) => tomar(e.target.files?.[0])} />
                <button type="button" className={THEME_BTN_PRIMARY} disabled={subiendo}
                    onClick={() => camaraRef.current?.click()}>
                    {subiendo ? 'Subiendo…' : 'Tomar / subir foto'}
                </button>
                {msg && <p className="text-sm text-red-600">{msg}</p>}
            </div>
        </div>
    );
}
