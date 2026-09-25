import React, { useCallback, useRef, useState } from 'react';
import { ChevronDown, Plus, Search, Trash2 } from 'lucide-react';
import InputConEscanner from '../../../Components/Escanner/InputConEscanner';
import { desbloquearBipAudio, reproducirBipConfirmacion, reproducirBipError } from '../../../Components/Escanner/bipScanner';
import {
    LABELS_ESTADO_FISICO,
    badgeEstadoFisico,
    BTN_SECONDARY,
} from '../../ControlPedidos/Partials/pedidosBmaStyles';
import { THEME_INPUT, THEME_LABEL, THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import { TEXTO_AVISO } from './traspasosStyles';
import {
    ESTADOS_FISICOS,
    inicializarRevisionesDesdeTraspaso,
    instanciasRevision,
    puedeAgregarPieza,
    productoPermitidoEnSolicitud,
    requiereComentario,
    requiereEvidencia,
    revisionDesdeLinea,
} from './revisionFisicaTraspasoUtils';

const SECCION = `${THEME_LABEL} mb-2 block`;

function GaleriaEvidenciasRevision({ archivos, previews, onChange, obligatorio }) {
    const camaraRef = useRef(null);

    const agregar = (lista) => {
        const nuevos = Array.from(lista || []).filter((f) => f?.type?.startsWith('image/') || f?.type === 'application/pdf');
        if (!nuevos.length) return;
        const nextFiles = [...(archivos || []), ...nuevos];
        const nextPreviews = [
            ...(previews || []),
            ...nuevos.map((f) => ({ name: f.name, url: URL.createObjectURL(f), mime: f.type || '' })),
        ];
        onChange(nextFiles, nextPreviews);
    };

    const quitar = (idx) => {
        const p = previews[idx];
        const nextPreviews = [...previews];
        nextPreviews.splice(idx, 1);
        let localIdx = 0;
        for (let i = 0; i < idx; i += 1) {
            if (!previews[i]?.remoto) localIdx += 1;
        }
        const nextFiles = (archivos || []).filter((_, i) => i !== localIdx);
        if (p?.url?.startsWith('blob:')) URL.revokeObjectURL(p.url);
        onChange(nextFiles, nextPreviews);
    };

    return (
        <div className="space-y-2">
            <p className={`${SECCION} m-0`}>
                Evidencia del producto{obligatorio ? ' *' : ''}
            </p>
            <div className="flex flex-wrap gap-2">
                {(previews || []).map((p, idx) => (
                    <div key={`${p.url}-${idx}`} className="relative w-16 h-16 rounded-lg overflow-hidden border theme-border">
                        {p.mime?.startsWith('image/') ? (
                            <img src={p.url} alt="" className="w-full h-full object-cover" />
                        ) : (
                            <div className="w-full h-full flex items-center justify-center text-[8px] font-black uppercase">PDF</div>
                        )}
                        {!p.remoto && (
                            <button
                                type="button"
                                onClick={() => quitar(idx)}
                                className="absolute top-0 right-0 p-1 bg-black/60 text-white rounded-bl-lg"
                                aria-label="Quitar"
                            >
                                <Trash2 className="w-3 h-3" />
                            </button>
                        )}
                    </div>
                ))}
            </div>
            <label className={`${BTN_SECONDARY} text-xs inline-flex items-center gap-1.5 cursor-pointer min-h-[44px]`}>
                <Plus className="w-3.5 h-3.5" /> Agregar foto
                <input
                    ref={camaraRef}
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
        </div>
    );
}

export default function RevisionFisicaTraspasoPanel({
    traspaso,
    almacenBusquedaId,
    revisiones,
    setRevisiones,
}) {
    const [skuQuery, setSkuQuery] = useState('');
    const [skuResultados, setSkuResultados] = useState([]);
    const [skuCargando, setSkuCargando] = useState(false);
    const [skuError, setSkuError] = useState('');
    const skuAbortRef = useRef(null);
    const skuPistolaRef = useRef(null);

    const actualizarRevision = useCallback((idx, campo, valor) => {
        setRevisiones((prev) => prev.map((r, i) => (i === idx ? { ...r, [campo]: valor } : r)));
    }, [setRevisiones]);

    const setEvidenciasRevision = useCallback((idx, files, previews) => {
        setRevisiones((prev) => prev.map((r, i) => (i === idx ? { ...r, evidencias: files, previews } : r)));
    }, [setRevisiones]);

    const quitarRevision = (idx) => {
        const rev = revisiones[idx];
        (rev?.previews || []).forEach((p) => {
            if (p?.url?.startsWith('blob:')) URL.revokeObjectURL(p.url);
        });
        setRevisiones((prev) => prev.filter((_, i) => i !== idx));
    };

    const agregarProducto = (producto) => {
        if (!productoPermitidoEnSolicitud(traspaso, producto)) {
            setSkuError('Este producto no está en la solicitud de traspaso.');
            reproducirBipError();
            return;
        }
        if (!puedeAgregarPieza(traspaso, revisiones, producto.id)) {
            setSkuError('Ya registró todas las piezas solicitadas de este SKU.');
            reproducirBipError();
            return;
        }
        const linea = (traspaso.productos || []).find((l) => String(l.producto_id) === String(producto.id));
        setRevisiones((prev) => [...prev, revisionDesdeLinea(linea)]);
        setSkuError('');
        setSkuQuery('');
        setSkuResultados([]);
        reproducirBipConfirmacion();
    };

    const buscarProductos = async (termino, { autoAgregar = false } = {}) => {
        const q = String(termino || '').trim();
        if (q.length < 2) {
            setSkuResultados([]);
            setSkuCargando(false);
            return;
        }
        if (!almacenBusquedaId) {
            setSkuError('No hay almacén configurado para búsqueda.');
            return;
        }
        skuAbortRef.current?.abort();
        const controller = new AbortController();
        skuAbortRef.current = controller;
        setSkuCargando(true);
        setSkuError('');
        try {
            const params = new URLSearchParams({ q, per_page: '15', almacen_id: String(almacenBusquedaId) });
            const resp = await fetch(`${route('gestion_interna.productos.buscar')}?${params}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!resp.ok) {
                setSkuResultados([]);
                setSkuError('No se pudo buscar en el catálogo.');
                return;
            }
            const json = await resp.json();
            const lote = json.data || [];
            setSkuResultados(lote);
            const exacto = lote.find(
                (p) => String(p.sku).toLowerCase() === q.toLowerCase()
                    || String(p.codigo_barras || '').toLowerCase() === q.toLowerCase()
            );
            if (exacto) {
                agregarProducto(exacto);
            } else if (autoAgregar) {
                if (lote.length === 1) agregarProducto(lote[0]);
                else if (lote.length === 0) {
                    setSkuError('Sin coincidencias.');
                    reproducirBipError();
                }
            }
        } catch (err) {
            if (err.name !== 'AbortError') setSkuError('No se pudo buscar.');
        } finally {
            setSkuCargando(false);
        }
    };

    const onSkuChange = (valor) => {
        setSkuQuery(valor);
        buscarProductos(valor);
    };

    const onSkuKeyDown = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            buscarProductos(skuQuery, { autoAgregar: true });
        }
    };

    const instancias = instanciasRevision(revisiones);

    return (
        <div className="space-y-4 p-4 rounded-xl border theme-border theme-element">
            <p className={`${SECCION} m-0`}>Revisión física de productos</p>
            <p className="text-[10px] theme-text-muted font-bold m-0">
                Piezas precargadas en Bueno. Use el escáner para validar o expanda una fila si hay detalle.
            </p>

            <div className="space-y-2">
                <label className={SECCION}>SKU / código de barras</label>
                <InputConEscanner
                    value={skuQuery}
                    onChange={onSkuChange}
                    label="SKU"
                    escaneoContinuo
                    inputProps={{
                        ref: skuPistolaRef,
                        placeholder: 'Escanear o escribir SKU…',
                        className: `${THEME_INPUT} w-full py-3 min-h-[44px]`,
                        onKeyDown: onSkuKeyDown,
                        autoComplete: 'off',
                        disabled: !almacenBusquedaId,
                        onFocus: desbloquearBipAudio,
                    }}
                />
                <button
                    type="button"
                    onClick={() => buscarProductos(skuQuery, { autoAgregar: true })}
                    disabled={skuCargando || String(skuQuery).trim().length < 2 || !almacenBusquedaId}
                    className={`${BTN_SECONDARY} text-xs flex items-center gap-1.5 min-h-[44px]`}
                >
                    <Search className="w-3.5 h-3.5" /> {skuCargando ? 'Buscando…' : 'Buscar y agregar'}
                </button>
                {skuError && <p className={`${TEXTO_AVISO} m-0`}>{skuError}</p>}
                {skuResultados.length > 0 && (
                    <div className="theme-surface border theme-border rounded-xl max-h-40 overflow-y-auto p-2">
                        {skuResultados.map((p) => (
                            <button
                                key={p.id}
                                type="button"
                                onClick={() => agregarProducto(p)}
                                className="w-full text-left p-2 rounded-lg text-xs font-bold theme-text-main hover:bg-black/5"
                            >
                                <span className="font-mono">{p.sku}</span> — {p.descripcion}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {revisiones.length === 0 && (
                <button
                    type="button"
                    onClick={() => setRevisiones(inicializarRevisionesDesdeTraspaso(traspaso))}
                    className={`${BTN_SECONDARY} text-xs w-full min-h-[44px]`}
                >
                    Cargar piezas de la solicitud (OK por defecto)
                </button>
            )}

            <div className="space-y-2 max-h-[40vh] overflow-y-auto custom-scrollbar">
                {revisiones.map((rev, idx) => {
                    const badge = badgeEstadoFisico(rev.estado_fisico);
                    const instancia = instancias[idx];
                    return (
                        <details
                            key={`${rev.solicitud_traspaso_producto_id}-${idx}`}
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
                                    <p className="text-sm font-bold theme-text-main m-0 truncate">{rev.descripcion_producto}</p>
                                    <span className={badge.className} style={badge.style}>{badge.label}</span>
                                </div>
                                <button
                                    type="button"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        quitarRevision(idx);
                                    }}
                                    className="p-2 min-h-[40px] min-w-[40px] rounded-xl border theme-border theme-element outline-none inline-flex items-center justify-center theme-text-main hover:theme-text-peligro shrink-0 transition-colors"
                                    aria-label="Eliminar pieza"
                                >
                                    <Trash2 className="w-4 h-4 shrink-0" />
                                </button>
                            </summary>
                            <div className="px-3 pb-3 space-y-3 border-t theme-border pt-3">
                                <div>
                                    <label className={SECCION}>Estado físico</label>
                                    <select
                                        value={rev.estado_fisico}
                                        onChange={(e) => actualizarRevision(idx, 'estado_fisico', e.target.value)}
                                        className={`${THEME_SELECT} w-full py-2.5 min-h-[44px]`}
                                    >
                                        {ESTADOS_FISICOS.map((c) => (
                                            <option key={c} value={c}>{LABELS_ESTADO_FISICO[c]}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className={SECCION}>
                                        Comentario{requiereComentario(rev.estado_fisico) ? ' *' : ''}
                                    </label>
                                    <textarea
                                        value={rev.comentario}
                                        onChange={(e) => actualizarRevision(idx, 'comentario', e.target.value)}
                                        className={`${THEME_TEXTAREA} w-full py-2.5 min-h-[60px]`}
                                    />
                                </div>
                                <div className="flex flex-wrap gap-4">
                                    <label className="flex items-center gap-2 text-xs font-bold theme-text-main">
                                        <input
                                            type="checkbox"
                                            checked={rev.unica_pieza}
                                            onChange={(e) => actualizarRevision(idx, 'unica_pieza', e.target.checked)}
                                        />
                                        Única pieza
                                    </label>
                                    <label className="flex items-center gap-2 text-xs font-bold theme-text-main">
                                        <input
                                            type="checkbox"
                                            checked={rev.mejor_ejemplar}
                                            onChange={(e) => actualizarRevision(idx, 'mejor_ejemplar', e.target.checked)}
                                        />
                                        Mejor ejemplar
                                    </label>
                                </div>
                                <GaleriaEvidenciasRevision
                                    archivos={rev.evidencias || []}
                                    previews={rev.previews || []}
                                    obligatorio={requiereEvidencia(rev.estado_fisico)}
                                    onChange={(files, previews) => setEvidenciasRevision(idx, files, previews)}
                                />
                            </div>
                        </details>
                    );
                })}
            </div>
        </div>
    );
}

export { inicializarRevisionesDesdeTraspaso };
