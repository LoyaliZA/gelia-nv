import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { X, Scale, Plus, Trash2, FileText, ChevronDown, ImagePlus, Search } from 'lucide-react';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    formatearFechaNegocio,
    LABELS_MOTIVO_REPESAJE,
    LABELS_ESTADO_FISICO,
    badgeEstadoFisico,
    calcularPesoCobradoGuia,
    etiquetaAlmacen,
    etiquetasInstanciaRevision,
    etiquetaEnvio,
} from '../../Partials/pedidosBmaStyles';
import { THEME_INPUT, THEME_SELECT, THEME_TEXTAREA } from '../../../../utils/geliaTheme';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import AvisoOperativoPedido from '../../Partials/AvisoOperativoPedido';
import ModalAlertaPedido from '../../Partials/ModalAlertaPedido';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';
import InputConEscanner from '../../../../Components/Escanner/InputConEscanner';

const SECCION = `${THEME_LABEL} mb-2 block`;
const ESTADOS = Object.keys(LABELS_ESTADO_FISICO);
const PREVIEW_SURFACE = 'theme-element border theme-border rounded-xl';
const DRAFT_DB = 'cedis_pesaje_drafts_v1';
const DRAFT_STORE = 'drafts';
const MAX_PRODUCTOS_ABIERTOS = 3;

const envioVacio = () => ({
    catalogo_tipo_caja_id: '',
    largo: '',
    ancho: '',
    alto: '',
    peso_real_kg: '',
    peso_volumetrico_kg: '',
});

const revisionDesdeProducto = (producto) => ({
    producto_id: producto?.id || null,
    sku: producto?.sku || '',
    descripcion_producto: producto
        ? `${producto.sku} — ${producto.descripcion}`
        : '',
    estado_fisico: 'bueno',
    comentario: '',
    unica_pieza: false,
    mejor_ejemplar: false,
    evidencias: [],
    previews: [],
    expandido: false,
});

const slotEnvioVacio = () => ({ archivos: [], previews: [] });

const requiereEvidencia = (estado) => estado === 'malo' || estado === 'danado';
const requiereComentario = (estado) => requiereEvidencia(estado) || estado === 'sin_existencia';

const draftKey = (pedidoId) => `pesaje:${pedidoId}`;

function openDraftDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DRAFT_DB, 1);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains(DRAFT_STORE)) {
                db.createObjectStore(DRAFT_STORE);
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function leerBorradorPesaje(pedidoId) {
    const db = await openDraftDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(DRAFT_STORE, 'readonly');
        const req = tx.objectStore(DRAFT_STORE).get(draftKey(pedidoId));
        req.onsuccess = () => resolve(req.result || null);
        req.onerror = () => reject(req.error);
    });
}

async function guardarBorradorPesaje(pedidoId, payload) {
    const db = await openDraftDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(DRAFT_STORE, 'readwrite');
        tx.objectStore(DRAFT_STORE).put(payload, draftKey(pedidoId));
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

async function borrarBorradorPesaje(pedidoId) {
    const db = await openDraftDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(DRAFT_STORE, 'readwrite');
        tx.objectStore(DRAFT_STORE).delete(draftKey(pedidoId));
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

const previewsDesdeArchivos = (archivos) => (archivos || []).map((f) => ({
    name: f.name,
    url: URL.createObjectURL(f),
    mime: f.type || '',
}));

/** Almacén del pedido, o el configurado con permite_busqueda_productos. */
const resolverAlmacenBusqueda = (pedido, almacenesBusqueda = []) => {
    const idPedido = pedido?.almacen_id || pedido?.almacen?.id || null;
    if (idPedido) {
        if (pedido?.almacen?.id) return pedido.almacen;
        const match = (almacenesBusqueda || []).find((a) => String(a.id) === String(idPedido));
        return match || { id: idPedido, codigo: null, nombre: `Almacén #${idPedido}` };
    }
    const lista = Array.isArray(almacenesBusqueda) ? almacenesBusqueda : [];
    if (lista.length >= 1) return lista[0];
    return null;
};

const archivoADoc = (file, url) => ({
    url,
    nombre_original: file.name,
    mime_type: file.type || '',
    tipo: 'evidencia_condicion',
});

function GaleriaEvidencias({
    archivos,
    previews,
    onChange,
    onVer,
    label = 'Evidencias',
    obligatorio = false,
}) {
    const agregar = (lista) => {
        const nuevos = Array.from(lista || []);
        if (!nuevos.length) return;
        const nextFiles = [...archivos, ...nuevos];
        const nextPreviews = [
            ...previews,
            ...nuevos.map((f) => ({ name: f.name, url: URL.createObjectURL(f), mime: f.type || '' })),
        ];
        onChange(nextFiles, nextPreviews);
    };

    const quitar = (idx) => {
        const nextFiles = archivos.filter((_, i) => i !== idx);
        const nextPreviews = [...previews];
        const [removed] = nextPreviews.splice(idx, 1);
        if (removed?.url) URL.revokeObjectURL(removed.url);
        onChange(nextFiles, nextPreviews);
    };

    const docs = previews.map((p, i) => archivoADoc(archivos[i] || { name: p.name, type: p.mime }, p.url));

    return (
        <div className="space-y-2">
            <label className={SECCION}>{label}{obligatorio ? ' *' : ''}</label>
            <label className="flex items-center gap-2 px-4 py-3 border theme-border border-dashed rounded-xl cursor-pointer w-fit theme-element theme-text-main">
                <ImagePlus className="w-4 h-4 theme-text-muted" />
                <span className="text-xs font-black uppercase">
                    {archivos.length ? `${archivos.length} archivo(s)` : 'Adjuntar fotos'}
                </span>
                <input
                    type="file"
                    accept="image/*,application/pdf"
                    multiple
                    className="hidden"
                    onChange={(e) => {
                        agregar(e.target.files);
                        e.target.value = '';
                    }}
                />
            </label>
            {previews.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {previews.map((p, idx) => {
                        const esPdf = (p.mime || '').includes('pdf') || String(p.name || '').toLowerCase().endsWith('.pdf');
                        return (
                            <div key={`${p.url}-${idx}`} className="relative w-20 h-20 rounded-xl overflow-hidden border theme-border theme-element group">
                                <button type="button" className="w-full h-full outline-none" onClick={() => onVer(docs, idx)} title="Ver evidencia">
                                    {esPdf ? (
                                        <div className="w-full h-full flex items-center justify-center text-[9px] font-black uppercase theme-text-muted">PDF</div>
                                    ) : (
                                        <img src={p.url} alt={p.name} className="w-full h-full object-cover transition-opacity group-hover:opacity-90" />
                                    )}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => quitar(idx)}
                                    className="absolute top-1 right-1 p-1 min-h-[28px] min-w-[28px] rounded-full theme-element border theme-border outline-none inline-flex items-center justify-center"
                                    aria-label="Quitar evidencia"
                                >
                                    <Trash2 className="w-3 h-3 theme-text-main" />
                                </button>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

export default function ModalResponderPesaje({
    abierto, onClose, pedido, tiposCaja = [], almacenesBusqueda = [],
}) {
    const [envios, setEnvios] = useState([envioVacio()]);
    const [procesando, setProcesando] = useState(false);
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'error', titulo: '', mensaje: '' });
    const [evidenciasPorEnvio, setEvidenciasPorEnvio] = useState([slotEnvioVacio()]);
    const [revisiones, setRevisiones] = useState([]);
    const [galeria, setGaleria] = useState({ abierto: false, documentos: [], indice: 0 });
    const [skuQuery, setSkuQuery] = useState('');
    const [skuResultados, setSkuResultados] = useState([]);
    const [skuCargando, setSkuCargando] = useState(false);
    const [skuError, setSkuError] = useState('');
    const [borradorMsg, setBorradorMsg] = useState(null);
    const [listaProductosAbierta, setListaProductosAbierta] = useState(false);
    const skuAbortRef = useRef(null);
    const skuDebounceRef = useRef(null);
    const avisoPiezasRef = useRef(false);
    const skipAutosaveRef = useRef(true);
    const hydratingRef = useRef(false);

    const revocarPreviews = (lista) => {
        (lista || []).forEach((p) => {
            if (p?.url) URL.revokeObjectURL(p.url);
        });
    };

    const resetFormularioVacio = () => {
        setEnvios([envioVacio()]);
        setProcesando(false);
        setAlerta({ abierto: false, tipo: 'error', titulo: '', mensaje: '' });
        setEvidenciasPorEnvio((prev) => {
            prev.forEach((s) => revocarPreviews(s.previews));
            return [slotEnvioVacio()];
        });
        setRevisiones((prev) => {
            prev.forEach((r) => revocarPreviews(r.previews));
            return [];
        });
        setGaleria({ abierto: false, documentos: [], indice: 0 });
        setSkuQuery('');
        setSkuResultados([]);
        setSkuError('');
        setListaProductosAbierta(false);
        setBorradorMsg(null);
        avisoPiezasRef.current = false;
    };

    useEffect(() => {
        if (!abierto || !pedido?.id) return undefined;

        let cancelado = false;
        skipAutosaveRef.current = true;
        hydratingRef.current = true;

        (async () => {
            try {
                const draft = await leerBorradorPesaje(pedido.id);
                if (cancelado) return;

                if (draft?.envios?.length) {
                    setEnvios(draft.envios.map((e) => ({ ...envioVacio(), ...e })));
                    setRevisiones((prev) => {
                        prev.forEach((r) => revocarPreviews(r.previews));
                        return (draft.revisiones || []).map((r) => {
                            const evidencias = Array.isArray(r.evidencias) ? r.evidencias.filter((f) => f instanceof Blob) : [];
                            return {
                                ...revisionDesdeProducto({ id: r.producto_id, sku: r.sku, descripcion: '' }),
                                ...r,
                                evidencias,
                                previews: previewsDesdeArchivos(evidencias),
                                expandido: false,
                            };
                        });
                    });
                    setEvidenciasPorEnvio((prev) => {
                        prev.forEach((s) => revocarPreviews(s.previews));
                        const slots = (draft.evidenciasPorEnvio || []).map((slot) => {
                            const archivos = Array.isArray(slot?.archivos)
                                ? slot.archivos.filter((f) => f instanceof Blob)
                                : [];
                            return { archivos, previews: previewsDesdeArchivos(archivos) };
                        });
                        return slots.length ? slots : [slotEnvioVacio()];
                    });
                    setProcesando(false);
                    setAlerta({ abierto: false, tipo: 'error', titulo: '', mensaje: '' });
                    setGaleria({ abierto: false, documentos: [], indice: 0 });
                    setSkuQuery('');
                    setSkuResultados([]);
                    setSkuError('');
                    setListaProductosAbierta(false);
                    setBorradorMsg('Borrador recuperado');
                } else {
                    resetFormularioVacio();
                }
            } catch {
                if (!cancelado) resetFormularioVacio();
            } finally {
                window.setTimeout(() => {
                    hydratingRef.current = false;
                    skipAutosaveRef.current = false;
                }, 200);
            }
        })();

        return () => {
            cancelado = true;
            skipAutosaveRef.current = true;
        };
    }, [abierto, pedido?.id]);

    useEffect(() => {
        if (skipAutosaveRef.current || hydratingRef.current) return;
        setEvidenciasPorEnvio((prev) => {
            const next = envios.map((_, i) => prev[i] || slotEnvioVacio());
            prev.slice(envios.length).forEach((s) => revocarPreviews(s.previews));
            return next;
        });
    }, [envios.length]);

    useEffect(() => {
        if (!abierto || !pedido?.id || skipAutosaveRef.current || hydratingRef.current) return undefined;

        const timer = window.setTimeout(() => {
            const payload = {
                envios,
                revisiones: revisiones.map(({ previews, expandido, ...rest }) => ({
                    ...rest,
                    evidencias: rest.evidencias || [],
                })),
                evidenciasPorEnvio: evidenciasPorEnvio.map((s) => ({
                    archivos: s.archivos || [],
                })),
                savedAt: new Date().toISOString(),
            };
            guardarBorradorPesaje(pedido.id, payload)
                .then(() => setBorradorMsg('Autoguardado'))
                .catch(() => {});
        }, 700);

        return () => window.clearTimeout(timer);
    }, [abierto, pedido?.id, envios, revisiones, evidenciasPorEnvio]);

    useEffect(() => () => {
        evidenciasPorEnvio.forEach((s) => revocarPreviews(s.previews));
        revisiones.forEach((r) => revocarPreviews(r.previews));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    if (!abierto || !pedido) return null;

    const almacenBusqueda = resolverAlmacenBusqueda(pedido, almacenesBusqueda);
    const almacenBusquedaId = almacenBusqueda?.id || null;

    const esImagenDoc = (doc) => {
        const mime = String(doc?.mime_type || '');
        const nombre = String(doc?.nombre_original || '').toLowerCase();
        return mime.startsWith('image/') || /\.(jpe?g|png|webp)$/.test(nombre);
    };

    const pdfPedido = (pedido.documentos || []).find((d) => d.tipo === 'pdf_pedido');
    const anexoPiezas = (pedido.documentos || []).find((d) => d.tipo === 'anexo_piezas');
    const severidadEstado = { bueno: 0, regular: 1, sin_existencia: 2, malo: 3, danado: 4 };
    const estadoGeneralDerivado = revisiones.reduce((acc, r) => {
        const s = r.estado_fisico || 'bueno';
        return (severidadEstado[s] ?? 0) > (severidadEstado[acc] ?? 0) ? s : acc;
    }, 'bueno');

    const abrirGaleria = (documentos, indice = 0) => {
        const lista = (documentos || []).filter((d) => d?.url);
        if (!lista.length) return;
        setGaleria({ abierto: true, documentos: lista, indice });
    };

    const renderSoporte = (doc, titulo) => {
        if (!doc?.url) {
            return <p className="text-sm theme-text-muted m-0">Sin archivo adjunto</p>;
        }
        if (esImagenDoc(doc)) {
            return (
                <button type="button" onClick={() => abrirGaleria([doc], 0)} className={`block w-full overflow-hidden outline-none ${PREVIEW_SURFACE}`} title="Ver foto">
                    <img src={doc.url} alt={doc.nombre_original || titulo} className="w-full max-h-[min(40vh,360px)] object-contain hover:opacity-90 transition-opacity" />
                </button>
            );
        }
        // PDF embebido siempre (no tarjeta "tocar para ver").
        return (
            <div className={`overflow-hidden ${PREVIEW_SURFACE}`}>
                <iframe
                    src={doc.url}
                    title={doc.nombre_original || titulo}
                    className="w-full border-0 bg-white"
                    style={{ height: 'min(55vh, 480px)' }}
                />
            </div>
        );
    };

    const actualizarEnvio = (idx, campo, valor) => {
        // Solo tipo de caja (rellena catálogo) y peso real son editables.
        if (campo !== 'catalogo_tipo_caja_id' && campo !== 'peso_real_kg') return;
        setEnvios((prev) => prev.map((e, i) => {
            if (i !== idx) return e;
            if (campo === 'peso_real_kg') return { ...e, peso_real_kg: valor };
            const tipo = tiposCaja.find((c) => String(c.id) === String(valor));
            return {
                ...e,
                catalogo_tipo_caja_id: valor,
                largo: tipo?.largo != null ? String(tipo.largo) : '',
                ancho: tipo?.ancho != null ? String(tipo.ancho) : '',
                alto: tipo?.alto != null ? String(tipo.alto) : '',
                peso_volumetrico_kg: tipo?.peso_volumetrico != null ? String(tipo.peso_volumetrico) : '',
            };
        }));
    };

    const agregarEnvio = () => setEnvios((prev) => [...prev, envioVacio()]);
    const quitarEnvio = (idx) => {
        setEnvios((prev) => (prev.length <= 1 ? prev : prev.filter((_, i) => i !== idx)));
    };

    const actualizarRevision = (idx, campo, valor) => {
        setRevisiones((prev) => prev.map((r, i) => (i === idx ? { ...r, [campo]: valor } : r)));
    };

    const setEvidenciasRevision = (idx, files, previews) => {
        setRevisiones((prev) => prev.map((r, i) => (i === idx ? { ...r, evidencias: files, previews } : r)));
    };

    const quitarRevision = (idx) => {
        const rev = revisiones[idx];
        if (!rev) return;
        if (rev.estado_fisico !== 'sin_existencia') {
            setRevisiones((prev) => prev.map((r, i) => (i === idx ? {
                ...r,
                estado_fisico: 'sin_existencia',
                expandido: true,
            } : r)));
            setAlerta({
                abierto: true,
                tipo: 'warning',
                titulo: 'No omita la pieza',
                mensaje: 'Si no hay existencias, márquela así (comentario obligatorio). No la quite de la revisión.',
            });
            return;
        }
        if (!String(rev.comentario || '').trim()) {
            setAlerta({
                abierto: true,
                tipo: 'error',
                titulo: 'Sin existencias',
                mensaje: 'Indique un comentario para Ventas. La pieza debe quedar revisada, no omitida.',
            });
            actualizarRevision(idx, 'expandido', true);
        }
    };

    const agregarProducto = (producto) => {
        setRevisiones((prev) => [...prev, revisionDesdeProducto(producto)]);
        setSkuError('');
        setSkuQuery('');
        setSkuResultados([]);
    };

    const buscarProductos = async (termino, { autoAgregar = false } = {}) => {
        const q = String(termino || '').trim();
        if (q.length < 2) {
            setSkuResultados([]);
            setSkuCargando(false);
            return;
        }
        const almacenId = almacenBusquedaId;
        if (!almacenId) {
            setSkuResultados([]);
            setSkuCargando(false);
            setSkuError('No hay almacén disponible para búsqueda (active el flag en catálogo o asigne almacén al pedido).');
            return;
        }
        skuAbortRef.current?.abort();
        const controller = new AbortController();
        skuAbortRef.current = controller;
        setSkuCargando(true);
        setSkuError('');
        try {
            const params = new URLSearchParams({ q, per_page: '15', almacen_id: String(almacenId) });
            const resp = await fetch(`${route('gestion_interna.productos.buscar')}?${params}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!resp.ok) {
                setSkuResultados([]);
                let msg = 'No se pudo buscar en el catálogo.';
                if (resp.status === 403) {
                    msg = 'Sin permiso para buscar productos (CEDIS).';
                } else {
                    try {
                        const errJson = await resp.json();
                        if (errJson?.message) msg = errJson.message;
                    } catch { /* ignore */ }
                }
                setSkuError(msg);
                return;
            }
            const json = await resp.json();
            const lote = json.data || [];
            setSkuResultados(lote);
            if (autoAgregar) {
                const exacto = lote.find((p) => String(p.sku).toLowerCase() === q.toLowerCase()
                    || String(p.codigo_barras || '').toLowerCase() === q.toLowerCase());
                if (exacto) {
                    agregarProducto(exacto);
                } else if (lote.length === 1) {
                    agregarProducto(lote[0]);
                } else if (lote.length === 0) {
                    setSkuError('Sin coincidencias en el inventario de este almacén.');
                }
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                setSkuResultados([]);
                setSkuError('No se pudo buscar en el catálogo.');
            }
        } finally {
            setSkuCargando(false);
        }
    };

    const onSkuChange = (e) => {
        const v = e.target.value;
        setSkuQuery(v);
        setSkuError('');
        clearTimeout(skuDebounceRef.current);
        if (!e.nativeEvent) {
            buscarProductos(v, { autoAgregar: true });
            return;
        }
        skuDebounceRef.current = setTimeout(() => buscarProductos(v), 350);
    };

    const onSkuKeyDown = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(skuDebounceRef.current);
            buscarProductos(skuQuery, { autoAgregar: true });
        }
    };

    const confirmar = () => {
        const cajas = [];
        for (let i = 0; i < envios.length; i++) {
            const e = envios[i];
            const tipoId = Number(e.catalogo_tipo_caja_id);
            const pesoReal = Number(e.peso_real_kg);
            const pesoVol = Number(e.peso_volumetrico_kg);
            const largo = Number(e.largo);
            const ancho = Number(e.ancho);
            const alto = Number(e.alto);
            const n = i + 1;

            if (!tipoId) {
                setAlerta({ abierto: true, tipo: 'error', titulo: `Envío ${n}`, mensaje: 'Seleccione el tipo de caja.' });
                return;
            }
            if (e.largo === '' || Number.isNaN(largo) || largo < 0
                || e.ancho === '' || Number.isNaN(ancho) || ancho < 0
                || e.alto === '' || Number.isNaN(alto) || alto < 0) {
                setAlerta({ abierto: true, tipo: 'error', titulo: `Envío ${n}`, mensaje: 'Indique largo, ancho y alto válidos.' });
                return;
            }
            if (e.peso_real_kg === '' || Number.isNaN(pesoReal) || pesoReal < 0) {
                setAlerta({ abierto: true, tipo: 'error', titulo: `Envío ${n}`, mensaje: 'Indique el peso real en kg.' });
                return;
            }
            if (e.peso_volumetrico_kg === '' || Number.isNaN(pesoVol) || pesoVol < 0) {
                setAlerta({ abierto: true, tipo: 'error', titulo: `Envío ${n}`, mensaje: 'Indique el peso volumétrico.' });
                return;
            }

            cajas.push({
                catalogo_tipo_caja_id: tipoId,
                largo,
                ancho,
                alto,
                peso_real_kg: pesoReal,
                peso_volumetrico_kg: pesoVol,
            });
        }

        for (let i = 0; i < envios.length; i++) {
            if (!(evidenciasPorEnvio[i]?.archivos?.length)) {
                setAlerta({ abierto: true, tipo: 'error', titulo: `Envío ${i + 1}`, mensaje: 'Adjunte al menos una foto del contenido de esta caja.' });
                return;
            }
        }

        const piezasPedido = Number(pedido.cantidad_piezas || 0);
        if (piezasPedido > 0 && revisiones.length < piezasPedido && !avisoPiezasRef.current) {
            avisoPiezasRef.current = true;
            setAlerta({
                abierto: true,
                tipo: 'warning',
                titulo: 'Piezas por revisar',
                mensaje: `El pedido tiene ${piezasPedido} piezas y solo revisó ${revisiones.length}. Si falta alguna, márquela Sin existencias; no la omita. Confirme de nuevo para continuar.`,
            });
            return;
        }

        for (let i = 0; i < revisiones.length; i++) {
            const r = revisiones[i];
            if (!r.descripcion_producto.trim()) {
                setAlerta({ abierto: true, tipo: 'error', titulo: `Producto ${i + 1}`, mensaje: 'Producto sin descripción.' });
                return;
            }
            if (requiereComentario(r.estado_fisico)) {
                if (!r.comentario.trim()) {
                    setAlerta({
                        abierto: true,
                        tipo: 'error',
                        titulo: `Producto ${i + 1}`,
                        mensaje: r.estado_fisico === 'sin_existencia'
                            ? 'Sin existencias requiere un comentario para Ventas.'
                            : 'Estado malo/dañado requiere comentario.',
                    });
                    return;
                }
            }
            if (requiereEvidencia(r.estado_fisico) && !r.evidencias?.length) {
                setAlerta({ abierto: true, tipo: 'error', titulo: `Producto ${i + 1}`, mensaje: 'Estado malo/dañado requiere evidencia.' });
                return;
            }
        }

        const form = new FormData();
        cajas.forEach((c, i) => {
            Object.entries(c).forEach(([k, v]) => form.append(`cajas[${i}][${k}]`, String(v)));
        });
        form.append('estado_fisico_general', estadoGeneralDerivado);
        form.append('comentario_fisico_general', '');
        evidenciasPorEnvio.forEach((slot, i) => {
            (slot.archivos || []).forEach((f, j) => form.append(`evidencias_envios[${i}][${j}]`, f));
        });
        revisiones.forEach((r, i) => {
            form.append(`revisiones[${i}][descripcion_producto]`, r.descripcion_producto);
            if (r.producto_id) form.append(`revisiones[${i}][producto_id]`, String(r.producto_id));
            if (r.sku) form.append(`revisiones[${i}][sku]`, String(r.sku));
            form.append(`revisiones[${i}][estado_fisico]`, r.estado_fisico);
            form.append(`revisiones[${i}][comentario]`, r.comentario || '');
            form.append(`revisiones[${i}][unica_pieza]`, r.unica_pieza ? '1' : '0');
            form.append(`revisiones[${i}][mejor_ejemplar]`, r.mejor_ejemplar ? '1' : '0');
            (r.evidencias || []).forEach((f, j) => form.append(`revisiones[${i}][evidencias][${j}]`, f));
        });

        setProcesando(true);
        router.post(route('control_pedidos.cedis.responder_pesaje', pedido.id), form, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setProcesando(false),
            onSuccess: (page) => {
                if (page?.props?.flash?.error) {
                    setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: page.props.flash.error });
                    return;
                }
                borrarBorradorPesaje(pedido.id).catch(() => {});
                onClose();
            },
            onError: (errs) => {
                const msg = Object.values(errs || {})[0];
                setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: typeof msg === 'string' ? msg : 'No se pudo guardar el pesaje.' });
            },
        });
    };

    const totalCobrado = envios.reduce((acc, e) => {
        const cobrado = calcularPesoCobradoGuia(e.peso_real_kg, e.peso_volumetrico_kg);
        return acc + (cobrado === '' ? 0 : Number(cobrado));
    }, 0);

    const productosCompactos = revisiones.length > MAX_PRODUCTOS_ABIERTOS;
    const productosConDetalle = revisiones.filter((r) => (
        r.estado_fisico !== 'bueno'
        || Boolean(r.comentario)
        || Boolean(r.unica_pieza)
        || Boolean(r.mejor_ejemplar)
        || (r.evidencias || []).length > 0
    )).length;
    const instancias = etiquetasInstanciaRevision(revisiones);

    const listaProductos = revisiones.map((rev, idx) => {
        const badge = badgeEstadoFisico(rev.estado_fisico);
        const instancia = instancias[idx];
        return (
            <details
                key={`${rev.producto_id || rev.descripcion_producto}-${idx}`}
                className="rounded-xl border theme-border theme-element overflow-hidden"
                open={Boolean(rev.expandido)}
                onToggle={(e) => actualizarRevision(idx, 'expandido', e.target.open)}
            >
                <summary className="flex items-center justify-between gap-2 px-3 py-2.5 cursor-pointer list-none min-h-[44px]">
                    <div className="min-w-0 flex-1 flex flex-wrap items-center gap-2">
                        <ChevronDown className={`w-4 h-4 theme-text-muted shrink-0 transition-transform ${rev.expandido ? 'rotate-180' : ''}`} />
                        {instancia && (
                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-black tabular-nums theme-element border theme-border theme-text-main shrink-0">
                                {instancia}
                            </span>
                        )}
                        <p className="text-sm font-bold theme-text-main m-0 break-words line-clamp-1">{rev.descripcion_producto}</p>
                        <span className={badge.className} style={badge.style}>{badge.label}</span>
                    </div>
                    <button
                        type="button"
                        onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            quitarRevision(idx);
                        }}
                        className="p-2 min-h-[40px] min-w-[40px] rounded-xl border theme-border theme-element outline-none inline-flex items-center justify-center theme-text-main shrink-0"
                        aria-label="Marcar sin existencias"
                    >
                        <Trash2 className="w-4 h-4" />
                    </button>
                </summary>
                <div className="px-3 pb-3 space-y-3 border-t theme-border pt-3">
                    <p className="text-[10px] theme-text-muted font-bold m-0">Agregue detalle solo si el estado no es Bueno o necesita foto/comentario.</p>
                    <div>
                        <label className={SECCION}>Estado físico</label>
                        <select value={rev.estado_fisico} onChange={(e) => actualizarRevision(idx, 'estado_fisico', e.target.value)} className={`${THEME_SELECT} w-full py-2.5 min-h-[44px]`}>
                            {ESTADOS.map((c) => (
                                <option key={c} value={c}>{LABELS_ESTADO_FISICO[c]}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className={SECCION}>Comentario{requiereComentario(rev.estado_fisico) ? ' *' : ''}</label>
                        <textarea
                            value={rev.comentario}
                            onChange={(e) => actualizarRevision(idx, 'comentario', e.target.value)}
                            className={`${THEME_TEXTAREA} w-full py-2.5 min-h-[60px]`}
                            placeholder={
                                rev.estado_fisico === 'sin_existencia'
                                    ? 'Indique qué falta / cómo debe proceder Ventas…'
                                    : (requiereComentario(rev.estado_fisico) ? 'Comentario obligatorio…' : 'Opcional…')
                            }
                        />
                    </div>
                    {rev.estado_fisico === 'sin_existencia' && (
                        <p className="text-[10px] font-bold text-sky-600 m-0">
                            Sin existencias: Ventas verá este aviso en el detalle del pedido (no hace falta adjuntar foto).
                        </p>
                    )}
                    <div className="flex flex-wrap gap-4">
                        <label className="flex items-center gap-2 text-xs font-bold theme-text-main">
                            <input type="checkbox" checked={rev.unica_pieza} onChange={(e) => actualizarRevision(idx, 'unica_pieza', e.target.checked)} className="w-4 h-4" />
                            Única pieza
                        </label>
                        <label className="flex items-center gap-2 text-xs font-bold theme-text-main">
                            <input type="checkbox" checked={rev.mejor_ejemplar} onChange={(e) => actualizarRevision(idx, 'mejor_ejemplar', e.target.checked)} className="w-4 h-4" />
                            Mejor ejemplar
                        </label>
                    </div>
                    <GaleriaEvidencias
                        archivos={rev.evidencias || []}
                        previews={rev.previews || []}
                        obligatorio={requiereEvidencia(rev.estado_fisico)}
                        label="Evidencia del producto (solo si hay detalle)"
                        onChange={(files, previews) => setEvidenciasRevision(idx, files, previews)}
                        onVer={abrirGaleria}
                    />
                </div>
            </details>
        );
    });

    return createPortal(
        <>
            <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
                <div
                    className={`${THEME_MODAL_SHELL} max-w-3xl w-full flex flex-col`}
                    style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="p-4 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                        <div className="min-w-0">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1">Responder pesaje</p>
                            <EncabezadoFolioPedido pedido={pedido} />
                            <p className="text-xs theme-text-muted m-0 mt-1">
                                {pedido.cliente?.nombre || '—'} · {formatearFechaNegocio(pedido.fecha)}
                            </p>
                        </div>
                        <button type="button" onClick={onClose} className="p-2 min-h-[44px] min-w-[44px] rounded-xl theme-element border theme-border outline-none shrink-0 inline-flex items-center justify-center theme-text-main" aria-label="Cerrar">
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    <div className="gelia-modal-body p-4 md:p-6 space-y-5">
                        {pedido.motivo_repesaje && (
                            <AvisoOperativoPedido label="Re-pesaje" tono="warning" icon={Scale}>
                                Motivo: {LABELS_MOTIVO_REPESAJE[pedido.motivo_repesaje] || pedido.motivo_repesaje}
                            </AvisoOperativoPedido>
                        )}

                        <div className="space-y-4 p-4 rounded-xl border theme-border theme-element">
                            <div className="space-y-2">
                                <div className="flex items-center justify-between gap-2 flex-wrap">
                                    <label className={`${SECCION} m-0`}>PDF o foto del pedido</label>
                                    {pdfPedido?.url && (
                                        <button type="button" onClick={() => abrirGaleria([pdfPedido], 0)} className={`${BTN_SECONDARY} inline-flex items-center justify-center gap-1.5 text-xs min-h-[40px]`}>
                                            <FileText className="w-3.5 h-3.5" /> Ver
                                        </button>
                                    )}
                                </div>
                                {renderSoporte(pdfPedido, 'Soporte del pedido')}
                            </div>
                            {anexoPiezas?.url && (
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between gap-2 flex-wrap">
                                        <label className={`${SECCION} m-0`}>Piezas adicionales</label>
                                        <button type="button" onClick={() => abrirGaleria([anexoPiezas], 0)} className={`${BTN_SECONDARY} inline-flex items-center justify-center gap-1.5 text-xs min-h-[40px]`}>
                                            <FileText className="w-3.5 h-3.5" /> Ver
                                        </button>
                                    </div>
                                    {renderSoporte(anexoPiezas, 'Anexo de piezas')}
                                </div>
                            )}
                        </div>

                        <div className="space-y-4 p-4 rounded-xl border theme-border theme-element">
                            <div className="flex items-center justify-between gap-2 flex-wrap">
                                <p className={`${SECCION} m-0`}>Revisión física de productos</p>
                                {borradorMsg && (
                                    <p className="text-[10px] font-black uppercase theme-text-muted m-0">{borradorMsg}</p>
                                )}
                            </div>
                            <p className="text-[10px] theme-text-muted font-bold m-0">
                                Cada producto se agrega en Bueno. Expanda solo si CEDIS debe cambiar el estado o adjuntar evidencia individual.
                                Si no hay existencias, márquela Sin existencias (no la omita).
                            </p>

                            <div className="space-y-2">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-xs font-black uppercase theme-text-muted m-0">Productos revisados</p>
                                    <button type="button" onClick={() => document.getElementById('cedis-sku-pesaje')?.focus()} className={`${BTN_SECONDARY} text-xs flex items-center gap-1.5 min-h-[40px]`}>
                                        <Plus className="w-3.5 h-3.5" /> Producto
                                    </button>
                                </div>
                                <label className={SECCION}>SKU / código de barras</label>
                                <p className="text-[10px] font-black uppercase theme-text-muted m-0 mb-2">
                                    Buscando en:{' '}
                                    <span className="theme-text-main">
                                        {etiquetaAlmacen(almacenBusqueda) !== '—'
                                            ? etiquetaAlmacen(almacenBusqueda)
                                            : (almacenBusquedaId ? `Almacén #${almacenBusquedaId}` : 'Sin almacén')}
                                    </span>
                                </p>
                                <InputConEscanner
                                    value={skuQuery}
                                    onChange={onSkuChange}
                                    label="SKU"
                                    escaneoContinuo
                                    className=""
                                    inputProps={{
                                        id: 'cedis-sku-pesaje',
                                        placeholder: 'Escanear o escribir SKU…',
                                        className: `${THEME_INPUT} w-full py-3 min-h-[44px]`,
                                        onKeyDown: onSkuKeyDown,
                                        autoComplete: 'off',
                                        disabled: !almacenBusquedaId,
                                    }}
                                />
                                <div className="flex flex-wrap gap-2">
                                    <button type="button" onClick={() => buscarProductos(skuQuery, { autoAgregar: true })} disabled={skuCargando || String(skuQuery).trim().length < 2 || !almacenBusquedaId} className={`${BTN_SECONDARY} text-xs flex items-center gap-1.5 min-h-[40px] outline-none`}>
                                        <Search className="w-3.5 h-3.5" /> {skuCargando ? 'Buscando…' : 'Buscar y agregar'}
                                    </button>
                                </div>
                                {skuError && <p className="text-[10px] font-bold text-amber-600 m-0">{skuError}</p>}
                                {skuResultados.length > 0 && (
                                    <div className="theme-surface border theme-border rounded-xl shadow-xl max-h-48 overflow-y-auto p-2">
                                        {skuResultados.map((p) => (
                                            <button key={p.id} type="button" onClick={() => agregarProducto(p)} className="w-full text-left p-3 rounded-lg hover:bg-[color-mix(in_srgb,var(--color-texto)_6%,transparent)] text-xs font-bold theme-text-main outline-none">
                                                <span className="font-mono">{p.sku}</span>
                                                <span className="theme-text-muted"> — </span>
                                                {p.descripcion}
                                                <span className="block text-[10px] theme-text-muted font-bold mt-0.5">
                                                    {p.almacen_codigo || p.almacen_nombre
                                                        ? `${p.almacen_codigo ? `${p.almacen_codigo} · ` : ''}${p.almacen_nombre || ''}`
                                                        : 'Almacén del pedido'}
                                                    {p.disponible != null || p.existencia != null
                                                        ? ` · Disp. ${p.disponible ?? p.existencia}`
                                                        : ''}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {revisiones.length === 0 && (
                                <p className="text-xs theme-text-muted font-bold m-0">Sin productos agregados.</p>
                            )}

                            {productosCompactos ? (
                                <details
                                    className="rounded-xl border theme-border overflow-hidden"
                                    open={listaProductosAbierta}
                                    onToggle={(e) => setListaProductosAbierta(e.target.open)}
                                >
                                    <summary className="flex items-center justify-between gap-2 px-3 py-3 cursor-pointer list-none min-h-[48px] theme-element">
                                        <div className="min-w-0">
                                            <p className="text-sm font-black theme-text-main m-0">
                                                {revisiones.length} productos
                                                {productosConDetalle > 0 ? ` · ${productosConDetalle} con detalle` : ''}
                                            </p>
                                            <p className="text-[10px] theme-text-muted font-bold m-0 mt-1 line-clamp-2">
                                                {revisiones.map((r, i) => {
                                                    const tag = instancias[i];
                                                    const base = r.sku || r.descripcion_producto;
                                                    return tag ? `${base} (${tag})` : base;
                                                }).join(' · ')}
                                            </p>
                                        </div>
                                        <ChevronDown className={`w-4 h-4 theme-text-muted shrink-0 transition-transform ${listaProductosAbierta ? 'rotate-180' : ''}`} />
                                    </summary>
                                    <div className="p-3 space-y-2 border-t theme-border max-h-[40vh] overflow-y-auto">
                                        {listaProductos}
                                    </div>
                                </details>
                            ) : (
                                <div className="space-y-2">{listaProductos}</div>
                            )}
                        </div>

                        <div>
                            <div className="flex items-center justify-between gap-2 mb-3">
                                <label className={`${SECCION} m-0`}>Envíos</label>
                                <button type="button" onClick={agregarEnvio} className={`${BTN_SECONDARY} text-xs flex items-center gap-1.5 outline-none min-h-[40px]`}>
                                    <Plus className="w-3.5 h-3.5" /> Otro envío
                                </button>
                            </div>
                            <p className="text-[10px] theme-text-muted font-bold m-0 mb-3">
                                Por cada caja adjunte la foto del lote de productos que van en ese envío.
                            </p>
                            <div className="space-y-4">
                                {envios.map((envio, idx) => {
                                    const cobrado = calcularPesoCobradoGuia(envio.peso_real_kg, envio.peso_volumetrico_kg);
                                    const slot = evidenciasPorEnvio[idx] || slotEnvioVacio();
                                    return (
                                        <div key={idx} className="p-4 rounded-xl border theme-border theme-element space-y-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <p className="text-sm font-black theme-text-main m-0">{etiquetaEnvio(idx, { tipo_caja: tiposCaja.find((t) => String(t.id) === String(envio.catalogo_tipo_caja_id)) })}</p>
                                                <button type="button" onClick={() => quitarEnvio(idx)} disabled={envios.length <= 1} className="p-2 min-h-[40px] min-w-[40px] rounded-xl border theme-border theme-element outline-none disabled:opacity-40 inline-flex items-center justify-center theme-text-main" aria-label={`Quitar envío ${idx + 1}`}>
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                            <div className="space-y-3">
                                                <div>
                                                    <label className={SECCION}>Tipo de caja</label>
                                                    <select value={envio.catalogo_tipo_caja_id} onChange={(e) => actualizarEnvio(idx, 'catalogo_tipo_caja_id', e.target.value)} className={`${THEME_SELECT} w-full py-3 min-h-[44px]`}>
                                                        <option value="">Seleccionar…</option>
                                                        {tiposCaja.map((c) => (
                                                            <option key={c.id} value={c.id}>{c.nombre}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                {envio.catalogo_tipo_caja_id ? (
                                                    <div className="rounded-xl border theme-border theme-surface p-3 sm:p-4">
                                                        <p className="text-[10px] font-black uppercase tracking-wide theme-text-muted m-0 mb-3">Datos del catálogo</p>
                                                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                                            <div>
                                                                <p className="text-[10px] font-black uppercase theme-text-muted m-0">Largo</p>
                                                                <p className="text-base font-black theme-text-main m-0 mt-0.5 tabular-nums">{envio.largo || '—'} <span className="text-xs font-bold theme-text-muted">cm</span></p>
                                                            </div>
                                                            <div>
                                                                <p className="text-[10px] font-black uppercase theme-text-muted m-0">Ancho</p>
                                                                <p className="text-base font-black theme-text-main m-0 mt-0.5 tabular-nums">{envio.ancho || '—'} <span className="text-xs font-bold theme-text-muted">cm</span></p>
                                                            </div>
                                                            <div>
                                                                <p className="text-[10px] font-black uppercase theme-text-muted m-0">Alto</p>
                                                                <p className="text-base font-black theme-text-main m-0 mt-0.5 tabular-nums">{envio.alto || '—'} <span className="text-xs font-bold theme-text-muted">cm</span></p>
                                                            </div>
                                                            <div>
                                                                <p className="text-[10px] font-black uppercase theme-text-muted m-0">Peso vol.</p>
                                                                <p className="text-base font-black theme-text-main m-0 mt-0.5 tabular-nums">{envio.peso_volumetrico_kg || '—'} <span className="text-xs font-bold theme-text-muted">kg</span></p>
                                                            </div>
                                                            <div className="col-span-2 sm:col-span-2">
                                                                <p className="text-[10px] font-black uppercase theme-text-muted m-0">Peso cobrado</p>
                                                                <p className="text-lg font-black theme-text-main m-0 mt-0.5 tabular-nums">{cobrado !== '' ? cobrado : '—'} <span className="text-xs font-bold theme-text-muted">kg</span></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <p className="text-[11px] theme-text-muted font-bold m-0">Seleccione el tipo de caja para ver medidas del catálogo.</p>
                                                )}
                                                <div>
                                                    <label className={SECCION}>Peso real (kg) *</label>
                                                    <input type="number" step="0.0001" min="0" inputMode="decimal" value={envio.peso_real_kg} onChange={(e) => actualizarEnvio(idx, 'peso_real_kg', e.target.value)} className={`${THEME_INPUT} w-full py-3 min-h-[44px]`} placeholder="0.0000" />
                                                </div>
                                            </div>
                                            <GaleriaEvidencias
                                                archivos={slot.archivos}
                                                previews={slot.previews}
                                                obligatorio
                                                label={`Fotos del lote — envío ${idx + 1} (contenido de esta caja)`}
                                                onChange={(files, previews) => {
                                                    setEvidenciasPorEnvio((prev) => prev.map((s, i) => (i === idx ? { archivos: files, previews } : s)));
                                                }}
                                                onVer={abrirGaleria}
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                            {envios.length > 1 && (
                                <p className="text-xs theme-text-muted font-bold m-0 mt-3">
                                    Total peso cobrado: {Math.round(totalCobrado * 10000) / 10000} kg · {envios.length} envíos
                                </p>
                            )}
                        </div>

                    </div>

                    <div className="gelia-modal-footer flex flex-col-reverse sm:flex-row flex-wrap gap-3 sm:justify-end p-4 md:p-6 border-t theme-border shrink-0">
                        <button type="button" onClick={onClose} className={`${BTN_SECONDARY} outline-none min-h-[44px] w-full sm:w-auto`} disabled={procesando}>Cancelar</button>
                        <button type="button" onClick={confirmar} disabled={procesando} className={`${BTN_PRIMARY} flex items-center justify-center gap-2 outline-none min-h-[44px] w-full sm:w-auto`}>
                            <Scale className="w-4 h-4" /> {procesando ? 'Guardando…' : 'Registrar pesaje'}
                        </button>
                    </div>
                </div>
            </div>
            <ModalAlertaPedido
                abierto={alerta.abierto}
                tipo={alerta.tipo}
                titulo={alerta.titulo}
                mensaje={alerta.mensaje}
                onClose={() => setAlerta((a) => ({ ...a, abierto: false }))}
            />
            <ModalVistaPreviaDocumento
                abierto={galeria.abierto}
                documentos={galeria.documentos}
                indice={galeria.indice}
                onClose={() => setGaleria({ abierto: false, documentos: [], indice: 0 })}
            />
        </>,
        document.body
    );
}
