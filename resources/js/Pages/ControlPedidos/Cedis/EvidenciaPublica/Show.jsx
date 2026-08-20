import React, { useEffect, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { compressImageToWebp, validateImageSource } from '../../../../utils/compressImage';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../../utils/geliaTheme';

export default function EvidenciaPublicaShow({
    codigo = '',
    error = null,
    folio = '',
    estado = '',
    expira_en = null,
    productos = [],
    cajas = [],
    fotos = [],
}) {
    const [listaProductos, setListaProductos] = useState(productos);
    const [listaCajas, setListaCajas] = useState(cajas);
    const [listaFotos, setListaFotos] = useState(fotos);
    const [objetivo, setObjetivo] = useState(null);
    const [msg, setMsg] = useState(error || '');
    const [subiendo, setSubiendo] = useState(false);
    const camaraRef = useRef(null);

    useEffect(() => {
        if (error || !codigo) return undefined;
        const t = window.setInterval(async () => {
            try {
                const { data } = await axios.get(`/cedis-evidencia/${codigo}/estado`);
                setListaProductos(data.productos || []);
                setListaCajas(data.cajas || []);
                setListaFotos(data.fotos || []);
                if (data.estado && data.estado !== 'activa' && data.estado !== 'pendiente') {
                    setMsg('La sesión se cerró en la computadora.');
                }
            } catch (e) {
                const texto = e.response?.data?.errors?.codigo?.[0] || e.response?.data?.message;
                if (texto) setMsg(texto);
            }
        }, 2000);
        return () => window.clearInterval(t);
    }, [codigo, error]);

    const tomar = async (file) => {
        if (!file || !objetivo || !codigo) return;
        const err = validateImageSource(file, 'Foto');
        if (err) {
            setMsg(err);
            return;
        }
        setSubiendo(true);
        setMsg('');
        try {
            const comprimida = await compressImageToWebp(file);
            const form = new FormData();
            form.append('foto', comprimida);
            form.append('objetivo_tipo', objetivo.tipo);
            form.append('objetivo_uuid', objetivo.uuid);
            if (objetivo.indice != null) form.append('indice_caja', String(objetivo.indice));
            const { data } = await axios.post(`/cedis-evidencia/${codigo}/fotos`, form);
            if (data?.foto) {
                setListaFotos((prev) => [...prev, data.foto]);
            }
        } catch (e) {
            setMsg(e.response?.data?.errors?.foto?.[0]
                || e.response?.data?.errors?.codigo?.[0]
                || e.response?.data?.message
                || 'No se pudo subir la foto.');
        } finally {
            setSubiendo(false);
        }
    };

    const fotosDe = (tipo, uuid) => listaFotos.filter((f) => f.objetivo_tipo === tipo && f.objetivo_uuid === uuid);

    if (error) {
        return (
            <div className="min-h-screen px-4 py-10" style={{ background: 'var(--color-fondo, #f4f4f5)' }}>
                <Head title="Evidencias CEDIS" />
                <div className={`mx-auto max-w-lg ${geliaCardClass()} p-6`}>
                    <p className="text-[10px] font-black uppercase tracking-[0.35em] m-0" style={{ color: 'var(--color-primario)' }}>GELIA</p>
                    <h1 className="mt-2 text-2xl font-black uppercase theme-text-main m-0">Sesión no disponible</h1>
                    <p className="mt-3 text-sm theme-text-muted m-0">{error}</p>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen px-4 py-6 pb-[max(1.5rem,env(safe-area-inset-bottom))]" style={{ background: 'var(--color-fondo, #f4f4f5)' }}>
            <Head title={`Evidencias ${folio || ''}`.trim()} />
            <div className={`mx-auto max-w-lg ${geliaCardClass()} p-5 space-y-4`}>
                <p className="text-[10px] font-black uppercase tracking-[0.35em] m-0" style={{ color: 'var(--color-primario)' }}>GELIA · CEDIS</p>
                <h1 className="text-2xl font-black uppercase italic tracking-tight theme-text-main m-0">Tomar evidencias</h1>
                <p className="text-sm theme-text-muted m-0">Pedido {folio || '—'}. Elija producto o caja y tome fotos. No cierra otras pantallas de GELIA.</p>
                {expira_en && (
                    <p className="text-[10px] font-black uppercase theme-text-muted m-0">Expira {new Date(expira_en).toLocaleTimeString()}</p>
                )}
                {msg && <p className="text-xs font-bold text-amber-700 m-0">{msg}</p>}

                <div>
                    <p className="text-[10px] font-black uppercase theme-text-muted m-0 mb-2">Productos</p>
                    {listaProductos.length === 0 && (
                        <p className="text-xs theme-text-muted m-0">Aún no hay SKU en la PC. Escanee en la computadora.</p>
                    )}
                    <div className="space-y-2">
                        {listaProductos.map((p) => {
                            const n = fotosDe('producto', p.client_uuid).length;
                            const sel = objetivo?.uuid === p.client_uuid;
                            return (
                                <button
                                    key={p.client_uuid}
                                    type="button"
                                    onClick={() => setObjetivo({ tipo: 'producto', uuid: p.client_uuid, label: p.sku || p.descripcion })}
                                    className={`${THEME_BTN_SECONDARY} w-full text-left min-h-[48px] ${sel ? 'ring-2 ring-[var(--color-primario)]' : ''}`}
                                >
                                    <span className="font-mono text-xs">{p.sku || '—'}</span>
                                    <span className="block text-[11px] theme-text-muted">{p.descripcion || ''}</span>
                                    {n > 0 && <span className="text-[10px] font-black uppercase">{n} foto(s)</span>}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div>
                    <p className="text-[10px] font-black uppercase theme-text-muted m-0 mb-2">Cajas</p>
                    {listaCajas.length === 0 && (
                        <p className="text-xs theme-text-muted m-0">Sin cajas en el formulario de la PC.</p>
                    )}
                    <div className="space-y-2">
                        {listaCajas.map((c) => {
                            const n = fotosDe('caja', c.client_uuid).length;
                            const sel = objetivo?.uuid === c.client_uuid;
                            return (
                                <button
                                    key={c.client_uuid}
                                    type="button"
                                    onClick={() => setObjetivo({ tipo: 'caja', uuid: c.client_uuid, indice: c.indice, label: c.etiqueta })}
                                    className={`${THEME_BTN_SECONDARY} w-full text-left min-h-[48px] ${sel ? 'ring-2 ring-[var(--color-primario)]' : ''}`}
                                >
                                    {c.etiqueta || `Envío ${(c.indice ?? 0) + 1}`}
                                    {n > 0 && <span className="block text-[10px] font-black uppercase">{n} foto(s)</span>}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <input
                    ref={camaraRef}
                    type="file"
                    accept="image/*"
                    capture="environment"
                    className="hidden"
                    onChange={(e) => {
                        tomar(e.target.files?.[0]);
                        e.target.value = '';
                    }}
                />
                <button
                    type="button"
                    disabled={!objetivo || subiendo}
                    onClick={() => camaraRef.current?.click()}
                    className={`${THEME_BTN_PRIMARY} w-full min-h-[48px] disabled:opacity-40`}
                >
                    {subiendo ? 'Subiendo…' : (objetivo ? `Tomar foto · ${objetivo.label || 'selección'}` : 'Seleccione producto o caja')}
                </button>
            </div>
        </div>
    );
}
