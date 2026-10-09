import React, { useEffect, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Camera, ImagePlus, Loader2, CheckCircle2 } from 'lucide-react';
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
    const [estadoSesion, setEstadoSesion] = useState(estado);
    const [confirmacion, setConfirmacion] = useState('');
    const galeriaRef = useRef(null);
    const disponible = !error && ['activa', 'pendiente'].includes(estadoSesion);
    const fotosSeleccionadas = objetivo ? listaFotos.filter((f) => f.objetivo_tipo === objetivo.tipo && f.objetivo_uuid === objetivo.uuid) : [];
    const camaraRef = useRef(null);

    useEffect(() => {
        if (error || !codigo) return undefined;
        const t = window.setInterval(async () => {
            try {
                const { data } = await axios.get(`/cedis-evidencia/${codigo}/estado`);
                setListaProductos(data.productos || []);
                setListaCajas(data.cajas || []);
                setListaFotos(data.fotos || []);
                if (data.estado) setEstadoSesion(data.estado);
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
        if (!file || !objetivo || !codigo || subiendo || !disponible) return;
        const err = validateImageSource(file, 'Foto');
        if (err) {
            setMsg(err);
            return;
        }
        setSubiendo(true);
        setMsg('');
        setConfirmacion('');
        try {
            const comprimida = await compressImageToWebp(file);
            const form = new FormData();
            form.append('foto', comprimida);
            form.append('objetivo_tipo', objetivo.tipo);
            form.append('objetivo_uuid', objetivo.uuid);
            if (objetivo.indice != null) form.append('indice_caja', String(objetivo.indice));
            const { data } = await axios.post(`/cedis-evidencia/${codigo}/fotos`, form);
            if (data?.foto) {
                setListaFotos((prev) => prev.some((f) => f.id === data.foto.id) ? prev : [...prev, data.foto]);
                setConfirmacion(`Foto enviada para ${objetivo.label || 'la selección'}. Puedes tomar otra.`);
            }
        } catch (e) {
            setMsg(e.response?.data?.errors?.foto?.[0]
                || e.response?.data?.errors?.codigo?.[0]
                || e.response?.data?.message
                || 'No se pudo enviar la foto. Revisa tu conexión e intenta otra vez.');
        } finally {
            setSubiendo(false);
        }
    };

    const fotosDe = (tipo, uuid) => listaFotos.filter((f) => f.objetivo_tipo === tipo && f.objetivo_uuid === uuid);

    if (error) {
        return (
            <div className="gelia-pedidos-bma gelia-pedidos-captura min-h-screen px-4 py-10" >
                <Head title="Evidencias CEDIS" />
                <div className={`mx-auto max-w-lg ${geliaCardClass()} p-6`}>
                    <p className="text-xs font-semibold tracking-[0.35em] m-0" style={{ color: 'var(--color-primario)' }}>GELIA</p>
                    <h1 className="mt-2 text-2xl font-semibold theme-text-main m-0">Sesión no disponible</h1>
                    <p className="mt-3 text-sm theme-text-muted m-0">{error}</p>
                </div>
            </div>
        );
    }

    return (
        <div className="gelia-pedidos-bma gelia-pedidos-captura min-h-screen px-4 py-6 pb-[max(1.5rem,env(safe-area-inset-bottom))]" >
            <Head title={`Evidencias ${folio || ''}`.trim()} />
            <div className={`mx-auto max-w-lg ${geliaCardClass()} p-5 space-y-4`}>
                <p className="text-xs font-semibold tracking-[0.35em] m-0" style={{ color: 'var(--color-primario)' }}>GELIA · CEDIS</p>
                <h1 className="text-2xl font-bold theme-text-main m-0">Tomar evidencias</h1>
                <p className="text-sm theme-text-muted m-0">Pedido <strong className="theme-text-main">{folio || '—'}</strong>. Selecciona a qué producto o caja corresponde la foto antes de tomarla.</p>
                <p className="text-xs theme-text-muted m-0">{listaFotos.length} {listaFotos.length === 1 ? 'foto recibida' : 'fotos recibidas'} en la computadora</p>
                {expira_en && (
                    <p className="text-xs font-semibold theme-text-muted m-0">Expira {new Date(expira_en).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}</p>
                )}
                {msg && <p role="alert" className="text-sm font-semibold theme-text-peligro m-0">{msg}</p>}
                <p role="status" className="text-sm theme-text-main m-0">{confirmacion}</p>

                <div>
                    <p className="text-xs font-semibold theme-text-muted m-0 mb-2">Productos</p>
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
                                    disabled={subiendo || !disponible}
                                    aria-pressed={sel}
                                    onClick={() => { setConfirmacion(''); setObjetivo({ tipo: 'producto', uuid: p.client_uuid, label: p.sku || p.descripcion }); }}
                                    className={`${THEME_BTN_SECONDARY} gelia-pedidos-captura-seleccion w-full !text-left !items-start !whitespace-normal break-words min-h-[48px] ${sel ? 'ring-2 ring-[var(--color-primario)]' : ''}`}
                                >
                                    <span className="text-sm font-semibold">{p.sku || '—'}</span>
                                    <span className="block text-sm theme-text-muted">{p.descripcion || ''}</span>
                                    {n > 0 && <span className="text-xs font-semibold">{n} foto(s)</span>}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div>
                    <p className="text-xs font-semibold theme-text-muted m-0 mb-2">Cajas</p>
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
                                    disabled={subiendo || !disponible}
                                    aria-pressed={sel}
                                    onClick={() => { setConfirmacion(''); setObjetivo({ tipo: 'caja', uuid: c.client_uuid, indice: c.indice, label: c.etiqueta }); }}
                                    className={`${THEME_BTN_SECONDARY} gelia-pedidos-captura-seleccion w-full !text-left !items-start !whitespace-normal break-words min-h-[48px] ${sel ? 'ring-2 ring-[var(--color-primario)]' : ''}`}
                                >
                                    {c.etiqueta || `Envío ${(c.indice ?? 0) + 1}`}
                                    {n > 0 && <span className="block text-xs font-semibold">{n} foto(s)</span>}
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
                    className="hidden" aria-label="Tomar foto de la selección"
                    onChange={(e) => {
                        tomar(e.target.files?.[0]);
                        e.target.value = '';
                    }}
                />
                {fotosSeleccionadas.length > 0 && (
                    <section aria-label="Fotos de la selección" className="space-y-2">
                        <p className="text-sm font-semibold theme-text-main m-0 inline-flex items-center gap-2"><CheckCircle2 className="w-4 h-4" aria-hidden="true" /> {fotosSeleccionadas.length} fotos recibidas</p>
                        <div className="grid grid-cols-3 gap-2">
                            {fotosSeleccionadas.map((foto) => <a key={foto.id} href={foto.url} target="_blank" rel="noopener noreferrer" aria-label={`Ver foto de ${objetivo.label || 'la selección'}`}>
                                <img src={foto.url} alt={foto.nombre || 'Evidencia recibida'} width="144" height="144" loading="lazy" className="w-full aspect-square object-cover rounded-xl border theme-border" />
                            </a>)}
                        </div>
                    </section>
                )}
                <input ref={galeriaRef} type="file" accept="image/*" className="hidden" aria-label="Elegir foto de la galería"
                    onChange={(e) => { tomar(e.target.files?.[0]); e.target.value = ''; }} />
                <div className="gelia-pedidos-captura-accion space-y-2">
                    <p className="text-sm theme-text-muted m-0 break-words">{!disponible ? 'Sesión cerrada. Solicita un nuevo QR en la computadora.' : (objetivo ? `Evidencia para: ${objetivo.label || 'selección'}` : 'Selecciona arriba un producto o una caja.')}</p>
                    <div className="flex gap-2">
                        <button type="button" disabled={!objetivo || subiendo || !disponible} onClick={() => camaraRef.current?.click()} className={`${THEME_BTN_PRIMARY} flex-1 min-h-[48px] inline-flex items-center justify-center gap-2 disabled:opacity-40`}>
                            {subiendo ? <Loader2 className="w-5 h-5 animate-spin" aria-hidden="true" /> : <Camera className="w-5 h-5" aria-hidden="true" />}
                            {subiendo ? 'Enviando…' : 'Tomar foto'}
                        </button>
                        <button type="button" disabled={!objetivo || subiendo || !disponible} onClick={() => galeriaRef.current?.click()} className={`${THEME_BTN_SECONDARY} min-h-[48px] inline-flex items-center justify-center gap-2 disabled:opacity-40`}><ImagePlus className="w-4 h-4" aria-hidden="true" /> Galería</button>
                    </div>
                </div>
            </div>
        </div>
    );
}
