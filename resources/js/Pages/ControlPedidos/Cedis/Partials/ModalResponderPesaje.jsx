import { resumenActualizacionConsulta } from './resumenActualizacionConsulta';
import { pedidoEsTrasladoInternoPdv } from '../../Partials/destinoSucursalPedido';
import usePedidoDialog from '../../Partials/usePedidoDialog';
import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { X, Scale, Plus, Trash2, FileText, ChevronDown, Search, ExternalLink, Smartphone } from 'lucide-react';
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
    cedisConsultaEsRevisionMercancia,
    terminologiaEmpaqueConsultaCedis,
    etiquetaAlmacen,
    etiquetaSucursal,
    etiquetasInstanciaRevision,
    etiquetaEnvio,
} from '../../Partials/pedidosBmaStyles';
import { THEME_INPUT, THEME_SELECT, THEME_TEXTAREA } from '../../../../utils/geliaTheme';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import AvisoOperativoPedido from '../../Partials/AvisoOperativoPedido';
import ModalAlertaPedido from '../../Partials/ModalAlertaPedido';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';
import VisorPdfPaginas from '../../Partials/VisorPdfPaginas';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import GaleriaEvidencias from '../../Partials/GaleriaEvidenciasPedido';
import InputConEscanner from '../../../../Components/Escanner/InputConEscanner';
import { desbloquearBipAudio, reproducirBipConfirmacion, reproducirBipError } from '../../../../Components/Escanner/bipScanner';
import ModalSesionEvidenciaCedis from './ModalSesionEvidenciaCedis';
import useDispositivoCampo, { esDispositivoCampo } from '../../../Activos/Partials/useDispositivoCampo';

const SECCION = `${THEME_LABEL} mb-2 block`;
const ESTADOS = Object.keys(LABELS_ESTADO_FISICO);
const PREVIEW_SURFACE = 'theme-element border theme-border rounded-xl';
const DRAFT_DB = 'cedis_pesaje_drafts_v1';
const DRAFT_STORE = 'drafts';
const MAX_PRODUCTOS_ABIERTOS = 3;

const esUuidValido = (v) => /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(v || ''));

const nuevoUuid = () => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    // Fallback RFC4122 v4 (navegadores sin crypto.randomUUID).
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const n = c === 'x' ? r : ((r & 0x3) | 0x8);
        return n.toString(16);
    });
};

const uuidOperativo = (v) => (esUuidValido(v) ? String(v) : nuevoUuid());

const envioVacio = () => ({
    client_uuid: nuevoUuid(),
    catalogo_tipo_caja_id: '',
    largo: '',
    ancho: '',
    alto: '',
    peso_real_kg: '',
    peso_volumetrico_kg: '',
});

const revisionDesdeProducto = (producto) => ({
    client_uuid: nuevoUuid(),
    producto_id: producto?.id || null,
    sku: producto?.sku || '',
    descripcion_producto: producto
        ? `${producto.sku} — ${producto.descripcion}`
        : '',
    cantidad_solicitada: 1,
    cantidad_encontrada: 1,
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
export const resolverAlmacenBusqueda = (pedido, almacenesBusqueda = []) => {
    const idPedido = pedido?.almacen_id || pedido?.almacen?.id || null;
    if (idPedido) {
        const match = (almacenesBusqueda || []).find((a) => String(a.id) === String(idPedido));
        return match || null;
    }
    const lista = Array.isArray(almacenesBusqueda) ? almacenesBusqueda : [];
    if (lista.length === 1) return lista[0];
    return null;
};

const previewsVigentes = (pedido, relacionTipo, relacionId) => (pedido?.documentos || [])
    .filter((d) => d.tipo === 'evidencia_condicion'
        && d.relacion_tipo === relacionTipo
        && String(d.relacion_id) === String(relacionId)
        && d.activo !== false
        && d.url)
    .map((d) => ({
        url: d.url,
        name: d.nombre_original || 'Evidencia vigente',
        mime: d.mime_type || '',
        remoto: true,
        vigente: true,
        documento_id: d.id,
    }));

const evidenciaProductoSigue = (rev) => (rev.previews || []).some((p) => {
    if (p.vigente) {
        return rev.estado_fisico === rev.estado_fisico_original
            && String(rev.producto_id || '') === String(rev.producto_id_original || '')
            && String(rev.sku || '') === String(rev.sku_original || '');
    }
    return Boolean(p.remoto);
});

export default function ModalResponderPesaje({
    abierto, onClose, pedido, tiposCaja = [], almacenesBusqueda = [],
}) {
    const soloRevisiones = cedisConsultaEsRevisionMercancia(pedido);
    const trasladoInterno = pedidoEsTrasladoInternoPdv(pedido);
    const exigeCajaSinPeso = soloRevisiones && !trasladoInterno && Boolean(pedido?.paqueteria?.requiere_caja);
    const empaqueConsulta = soloRevisiones
        ? terminologiaEmpaqueConsultaCedis(pedido)
        : null;
    const [envios, setEnvios] = useState([envioVacio()]);
    const [uuidsGuardados, setUuidsGuardados] = useState([]);
    const [motivoRetiro, setMotivoRetiro] = useState('');
    const [pedirMotivoRetiro, setPedirMotivoRetiro] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'error', titulo: '', mensaje: '' });
    const [evidenciasPorEnvio, setEvidenciasPorEnvio] = useState([slotEnvioVacio()]);
    /** Tienda (sin cajas): fotos del lote final, como el contenido de un envío en pesaje. */
    const [evidenciasLote, setEvidenciasLote] = useState(slotEnvioVacio());
    /** Tienda: bultos a preparar (aprox.) — se guarda en numero_cajas para resguardos PDV. */
    const [bultosAproximados, setBultosAproximados] = useState('');
    const [tipoCajaRevision, setTipoCajaRevision] = useState('');
    const loteUuidRef = useRef(nuevoUuid());
    const [revisiones, setRevisiones] = useState([]);
    const [galeria, setGaleria] = useState({ abierto: false, documentos: [], indice: 0 });
    const [skuQuery, setSkuQuery] = useState('');
    const [almacenElegidoId, setAlmacenElegidoId] = useState('');
    useEffect(() => { setAlmacenElegidoId(''); setSkuResultados([]); }, [pedido?.id]);
    const [skuResultados, setSkuResultados] = useState([]);
    const [skuCargando, setSkuCargando] = useState(false);
    const [skuError, setSkuError] = useState('');
    const [sesionEvidencia, setSesionEvidencia] = useState(null);
    const [modalQr, setModalQr] = useState(false);
    const [celularConectado, setCelularConectado] = useState(false);
    const skuPistolaRef = useRef(null);
    const [borradorMsg, setBorradorMsg] = useState(null);
    const [errorCampo, setErrorCampo] = useState(null);
    const [paso, setPaso] = useState(0);
    const cuerpoRef = useRef(null);
    const [listaProductosAbierta, setListaProductosAbierta] = useState(false);
    const skuAbortRef = useRef(null);
    const skuDebounceRef = useRef(null);
    const avisoPiezasRef = useRef(false);
    const skipAutosaveRef = useRef(true);
    const hydratingRef = useRef(false);
    const envioNuevoRef = useRef(null);
    const enviosRef = useRef(envios);
    enviosRef.current = envios;
    const focusNuevoEnvioRef = useRef(false);
    const [confirmacion, setConfirmacion] = useState(null);
    /** Snapshot al abrir actualización: para resumen antes → después. */
    const [baselineConsulta, setBaselineConsulta] = useState(null);
    const { esCampo, esMovil } = useDispositivoCampo();

    const revocarPreviews = (lista) => {
        (lista || []).forEach((p) => {
            if (p?.url && !p.vigente) URL.revokeObjectURL(p.url);
        });
    };

    const resetFormularioVacio = () => {
        setEnvios([envioVacio()]);
        setUuidsGuardados([]);
        setMotivoRetiro('');
        setPedirMotivoRetiro(false);
        setProcesando(false);
        setAlerta({ abierto: false, tipo: 'error', titulo: '', mensaje: '' });
        setBaselineConsulta(null);
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
        setErrorCampo(null);
        setBultosAproximados('');
        avisoPiezasRef.current = false;
    };

    const claveProducto = (r) => String(r.sku || r.descripcion_producto || '').trim().toLowerCase();

    const snapshotConsulta = (revs, cajas) => ({
        productos: (revs || []).filter((r) => !r.retirada_at).map((r) => ({
            clave: claveProducto(r),
            revision_id: r.revision_id || r.id || null,
            client_uuid: r.client_uuid || null,
            producto_id: r.producto_id || null,
            sku: r.sku || '',
            label: r.descripcion_producto || r.sku || '—',
            estado: r.estado_fisico || 'bueno',
            cantidad_solicitada: r.cantidad_solicitada ?? null,
            cantidad_encontrada: r.cantidad_encontrada ?? null,
        })),
        envios: (cajas || []).length,
        pesoCobrado: (cajas || []).reduce((acc, c) => {
            const cob = calcularPesoCobradoGuia(c.peso_real_kg, c.peso_volumetrico_kg);
            return acc + (cob === '' || cob == null ? 0 : Number(cob));
        }, 0),
    });

    useEffect(() => {
        if (!abierto || !pedido?.id) return undefined;

        let cancelado = false;
        setPaso(0);
        skipAutosaveRef.current = true;
        hydratingRef.current = true;
        loteUuidRef.current = nuevoUuid();
        setEvidenciasLote((prev) => {
            revocarPreviews(prev.previews);
            return slotEnvioVacio();
        });

        (async () => {
            try {
                const draft = await leerBorradorPesaje(pedido.id);
                if (cancelado) return;
                setAlmacenElegidoId(draft?.almacenId || '');

                if (draft?.envios?.length || (soloRevisiones && draft?.revisiones?.length) || draft?.evidenciasLote) {
                    setEnvios(
                        draft.envios?.length
                            ? draft.envios.map((e) => ({
                                ...envioVacio(),
                                ...e,
                                client_uuid: uuidOperativo(e.client_uuid || e.uuid_operativo),
                            }))
                            : [envioVacio()]
                    );
                    setRevisiones((prev) => {
                        prev.forEach((r) => revocarPreviews(r.previews));
                        const previas = (pedido.revisiones_producto || pedido.revisionesProducto || []).filter((row) => !row.retirada_at);
                        const usadas = new Set();
                        return (draft.revisiones || []).map((r) => {
                            const evidencias = Array.isArray(r.evidencias) ? r.evidencias.filter((f) => f instanceof Blob) : [];
                            const previa = previas.find((row) => !usadas.has(row.id) && (
                                String(row.id) === String(r.revision_id)
                                || (r.producto_id && String(row.producto_id) === String(r.producto_id))
                                || (r.descripcion_producto && row.descripcion_producto === r.descripcion_producto)
                            ));
                            if (previa) usadas.add(previa.id);
                            return {
                                ...revisionDesdeProducto({ id: r.producto_id, sku: r.sku, descripcion: '' }),
                                ...r,
                                revision_id: r.revision_id || previa?.id || null,
                                estado_fisico_original: previa?.estado_fisico || r.estado_fisico_original || r.estado_fisico || 'bueno',
                                producto_id_original: previa?.producto_id ?? r.producto_id_original ?? r.producto_id ?? null,
                                sku_original: previa?.sku ?? r.sku_original ?? r.sku ?? '',
                                client_uuid: uuidOperativo(r.client_uuid),
                                evidencias,
                                previews: [
                                    ...previewsDesdeArchivos(evidencias),
                                    ...(previa ? previewsVigentes(pedido, 'revision_producto', previa.id) : []),
                                ],
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
                    if (draft.evidenciasLote) {
                        const archivos = Array.isArray(draft.evidenciasLote.archivos)
                            ? draft.evidenciasLote.archivos.filter((f) => f instanceof Blob)
                            : [];
                        setEvidenciasLote((prev) => {
                            revocarPreviews(prev.previews);
                            return { archivos, previews: previewsDesdeArchivos(archivos) };
                        });
                    }
                    if (draft.loteUuid) loteUuidRef.current = draft.loteUuid;
                    if (draft.bultosAproximados != null && draft.bultosAproximados !== '') {
                        setBultosAproximados(String(draft.bultosAproximados));
                    }
                    setProcesando(false);
                    setAlerta({ abierto: false, tipo: 'error', titulo: '', mensaje: '' });
                    setGaleria({ abierto: false, documentos: [], indice: 0 });
                    setSkuQuery('');
                    setSkuResultados([]);
                    setSkuError('');
                    setListaProductosAbierta(false);
                    setBorradorMsg('Borrador recuperado');
                    if (pedido.consulta_actualizacion_pendiente || pedido.motivo_repesaje) {
                        const cajasPrevias = pedido.cajas || pedido.cajas_pesaje || [];
                        const revsPrevias = pedido.revisiones_producto || pedido.revisionesProducto || [];
                        if (cajasPrevias.length || revsPrevias.length) {
                            setBaselineConsulta(snapshotConsulta(
                                revsPrevias,
                                soloRevisiones ? [] : cajasPrevias.map((c) => ({
                                    peso_real_kg: c.peso_real_kg,
                                    peso_volumetrico_kg: c.peso_volumetrico_kg,
                                })),
                            ));
                        }
                    }
                } else {
                    // Precarga respuesta previa (actualización de consulta).
                    const cajasPrevias = pedido.cajas || pedido.cajas_pesaje || [];
                    const revsPrevias = pedido.revisiones_producto || pedido.revisionesProducto || [];
                    if (cajasPrevias.length || revsPrevias.length) {
                        if (!soloRevisiones && cajasPrevias.length) {
                            const nextEnvios = cajasPrevias.map((c) => ({
                                ...envioVacio(),
                                client_uuid: uuidOperativo(c.uuid_operativo || c.client_uuid),
                                catalogo_tipo_caja_id: String(c.catalogo_tipo_caja_id || c.tipo_caja?.id || ''),
                                largo: c.largo ?? '',
                                ancho: c.ancho ?? '',
                                alto: c.alto ?? '',
                                peso_real_kg: c.peso_real_kg ?? '',
                                peso_volumetrico_kg: c.peso_volumetrico_kg ?? '',
                            }));
                            setEnvios(nextEnvios);
                            // Solo marcar como "ya guardados" los que traen uuid de BD válido (retiro con motivo).
                            setUuidsGuardados(cajasPrevias
                                .map((c) => (esUuidValido(c.uuid_operativo) ? c.uuid_operativo : null))
                                .filter(Boolean));
                            setEvidenciasPorEnvio(cajasPrevias.map((c) => ({
                                archivos: [],
                                previews: previewsVigentes(pedido, 'envio_caja', c.id),
                            })));
                        } else {
                            setEnvios([envioVacio()]);
                            setEvidenciasPorEnvio([slotEnvioVacio()]);
                        }
                        setRevisiones((prev) => {
                            prev.forEach((r) => revocarPreviews(r.previews));
                            return revsPrevias.filter((r) => !r.retirada_at).map((r) => ({
                                ...revisionDesdeProducto({
                                    id: r.producto_id,
                                    sku: r.sku,
                                    descripcion: String(r.descripcion_producto || '').replace(/^[^—]+—\s*/, ''),
                                }),
                                revision_id: r.id,
                                producto_id: r.producto_id,
                                producto_id_original: r.producto_id || null,
                                sku: r.sku || '',
                                sku_original: r.sku || '',
                                descripcion_producto: r.descripcion_producto || '',
                                cantidad_solicitada: r.cantidad_solicitada ?? '',
                                cantidad_encontrada: r.cantidad_encontrada ?? '',
                                estado_fisico: r.estado_fisico || 'bueno',
                                estado_fisico_original: r.estado_fisico || 'bueno',
                                comentario: r.comentario || '',
                                unica_pieza: Boolean(r.unica_pieza),
                                mejor_ejemplar: Boolean(r.mejor_ejemplar),
                                evidencias: [],
                                previews: previewsVigentes(pedido, 'revision_producto', r.id),
                                expandido: false,
                            }));
                        });
                        const loteDocs = (pedido.documentos || []).filter((d) => d.tipo === 'evidencia_condicion' && d.relacion_tipo === 'revision_general' && d.activo !== false && d.url);
                        if (loteDocs.length) {
                            setEvidenciasLote({
                                archivos: [],
                                previews: loteDocs.map((d) => ({
                                    url: d.url,
                                    name: d.nombre_original || 'Evidencia vigente',
                                    mime: d.mime_type || '',
                                    remoto: true,
                                    vigente: true,
                                    documento_id: d.id,
                                })),
                            });
                        }
                        if (pedido.consulta_actualizacion_pendiente || pedido.motivo_repesaje) {
                            setBaselineConsulta(snapshotConsulta(
                                revsPrevias,
                                soloRevisiones ? [] : cajasPrevias.map((c) => ({
                                    peso_real_kg: c.peso_real_kg,
                                    peso_volumetrico_kg: c.peso_volumetrico_kg,
                                })),
                            ));
                        } else {
                            setBaselineConsulta(null);
                        }
                        if (soloRevisiones && pedido.numero_cajas != null && pedido.numero_cajas !== '') {
                            setBultosAproximados(String(pedido.numero_cajas));
                        }
                        setProcesando(false);
                        setBorradorMsg(pedido.consulta_actualizacion_pendiente ? 'Precarga: respuesta anterior' : null);
                    } else {
                        resetFormularioVacio();
                    }
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
        if (!focusNuevoEnvioRef.current) return;
        focusNuevoEnvioRef.current = false;
        const el = envioNuevoRef.current;
        el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el?.querySelector('select')?.focus();
    }, [envios.length]);

    useEffect(() => {
        if (!abierto || !pedido?.id || skipAutosaveRef.current || hydratingRef.current) return undefined;

        const timer = window.setTimeout(() => {
            const payload = {
                almacenId: almacenElegidoId || resolverAlmacenBusqueda(pedido, almacenesBusqueda)?.id || null,
                envios,
                revisiones: revisiones.map(({ previews, expandido, ...rest }) => ({
                    ...rest,
                    evidencias: rest.evidencias || [],
                })),
                evidenciasPorEnvio: evidenciasPorEnvio.map((s) => ({
                    archivos: s.archivos || [],
                })),
                evidenciasLote: { archivos: evidenciasLote.archivos || [] },
                loteUuid: loteUuidRef.current,
                bultosAproximados,
                savedAt: new Date().toISOString(),
            };
            guardarBorradorPesaje(pedido.id, payload)
                .then(() => setBorradorMsg('Autoguardado'))
                .catch(() => setBorradorMsg('No se pudo guardar el borrador. Mantén esta pantalla abierta y registra al terminar.'));
        }, 700);

        return () => window.clearTimeout(timer);
    }, [abierto, pedido?.id, envios, revisiones, evidenciasPorEnvio, evidenciasLote, bultosAproximados, almacenElegidoId]);

    const anexarFotoRemota = (foto) => {
        if (!foto?.objetivo_uuid) return;
        const preview = {
            name: foto.nombre || 'foto',
            url: foto.url,
            mime: foto.mime || 'image/jpeg',
            remoto: true,
            id: foto.id,
        };
        if (foto.objetivo_tipo === 'caja') {
            if (soloRevisiones || foto.objetivo_uuid === loteUuidRef.current) {
                setEvidenciasLote((prev) => {
                    if (prev.previews.some((p) => p.id === foto.id)) return prev;
                    return { ...prev, previews: [...prev.previews, preview] };
                });
                return;
            }
            setEvidenciasPorEnvio((prev) => {
                const idxUuid = enviosRef.current.findIndex((e) => e.client_uuid === foto.objetivo_uuid);
                const i = idxUuid >= 0 ? idxUuid : (foto.indice_caja ?? -1);
                if (i < 0 || !prev[i]) return prev;
                if (prev[i].previews.some((p) => p.id === foto.id)) return prev;
                return prev.map((s, j) => (j === i ? { ...s, previews: [...s.previews, preview] } : s));
            });
            return;
        }
        setRevisiones((prev) => prev.map((r) => {
            if (r.client_uuid !== foto.objetivo_uuid) return r;
            if ((r.previews || []).some((p) => p.id === foto.id)) return r;
            return { ...r, previews: [...(r.previews || []), preview], expandido: true };
        }));
    };

    const sesionId = sesionEvidencia?.sesion_id || sesionEvidencia?.id;

    useEffect(() => {
        if (!abierto || !pedido?.id || !sesionId) return undefined;
        const canalName = `pedido-bma.${pedido.id}.evidencias`;
        if (window.Echo) {
            const canal = window.Echo.private(canalName);
            canal.listen('.evidencia-cedis.actualizada', (payload) => {
                if (payload?.tipo === 'sesion_reclamada') setCelularConectado(true);
                if (payload?.tipo === 'cancelada') {
                    setSesionEvidencia(null);
                    setCelularConectado(false);
                    setModalQr(false);
                }
                if (payload?.tipo === 'foto' && payload.foto) anexarFotoRemota(payload.foto);
            });
        }
        const recuperarFotos = async () => {
            try {
                const { data } = await window.axios.get(route('control_pedidos.cedis.sesion_evidencia.show', pedido.id));
                (data.fotos || []).forEach(anexarFotoRemota);
                if (data.estado === 'activa') setCelularConectado(true);
            } catch { /* La sesión pudo cerrarse mientras se recuperaba la conexión. */ }
        };
        const connection = window.Echo?.connector?.pusher?.connection;
        connection?.bind('connected', recuperarFotos);
        window.addEventListener('focus', recuperarFotos);
        window.addEventListener('online', recuperarFotos);
        recuperarFotos();
        return () => {
            window.Echo?.leave(canalName);
            connection?.unbind('connected', recuperarFotos);
            window.removeEventListener('focus', recuperarFotos);
            window.removeEventListener('online', recuperarFotos);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [abierto, pedido?.id, sesionId]);

    useEffect(() => {
        if (!abierto || !pedido?.id || !sesionId) return undefined;
        const t = window.setTimeout(() => {
            const productos = revisiones.map((r) => ({
                client_uuid: r.client_uuid,
                sku: r.sku || '',
                descripcion: r.descripcion_producto || '',
            }));
            const cajas = soloRevisiones
                ? [{
                    client_uuid: loteUuidRef.current,
                    indice: 0,
                    etiqueta: 'Evidencia final (lote)',
                }]
                : envios.map((e, i) => ({
                    client_uuid: e.client_uuid,
                    indice: i,
                    etiqueta: etiquetaEnvio(i, { tipo_caja: tiposCaja.find((t) => String(t.id) === String(e.catalogo_tipo_caja_id)) }),
                }));
            window.axios.put(route('control_pedidos.cedis.sesion_evidencia.snapshot', pedido.id), { productos, cajas }).catch(() => {});
        }, 300);
        return () => window.clearTimeout(t);
    }, [abierto, pedido?.id, sesionId, revisiones, envios, tiposCaja, soloRevisiones]);

    useEffect(() => {
        if (!abierto || esCampo || paso !== 1) return undefined;
        const t = window.setTimeout(() => skuPistolaRef.current?.focus(), 200);
        return () => window.clearTimeout(t);
    }, [abierto, esCampo, paso]);

    useEffect(() => () => {
        evidenciasPorEnvio.forEach((s) => revocarPreviews(s.previews));
        revocarPreviews(evidenciasLote.previews);
        revisiones.forEach((r) => revocarPreviews(r.previews));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const dialog = usePedidoDialog({ abierto: abierto && Boolean(pedido), onClose: () => pedirCerrar(), bloqueado: procesando });

    if (!abierto || !pedido) return null;

    const almacenBusqueda = almacenElegidoId ? (almacenesBusqueda || []).find((a) => String(a.id) === String(almacenElegidoId)) : resolverAlmacenBusqueda(pedido, almacenesBusqueda);
    const almacenBusquedaId = almacenBusqueda?.id || null;

    const esImagenDoc = (doc) => {
        const mime = String(doc?.mime_type || '');
        const nombre = String(doc?.nombre_original || '').toLowerCase();
        return mime.startsWith('image/') || /\.(jpe?g|png|webp)$/.test(nombre);
    };

    const pdfPedido = (pedido.documentos || []).find((d) => d.tipo === 'pdf_pedido')
        || (pedido.documentos || []).find((d) => d.tipo === 'cotizacion');
    const anexosPiezas = (pedido.documentos || []).filter((d) => d.tipo === 'anexo_piezas');
    const severidadEstado = { bueno: 0, regular: 1, sin_existencia: 2, malo: 3, danado: 4 };
    const cantidadSolicitadaRevisada = trasladoInterno
        ? revisiones.reduce((total, r) => total + Number(r.cantidad_solicitada || 0), 0)
        : revisiones.length;
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
                    <img src={doc.url} alt={doc.nombre_original || titulo} width="480" height="360" className="w-full max-h-[min(40vh,360px)] object-contain hover:opacity-90 transition-opacity" />
                </button>
            );
        }
        if (esCampo || esMovil) {
            return (
                <div className={`overflow-hidden ${PREVIEW_SURFACE}`}>
                    <VisorPdfPaginas url={doc.url} titulo={doc.nombre_original || titulo} maxHeight="min(40dvh, 360px)" />
                    <div className="p-2 border-t theme-border">
                        <a
                            href={doc.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={`${BTN_SECONDARY} w-full min-h-[44px] inline-flex items-center justify-center gap-2 text-xs no-underline`}
                        >
                            <ExternalLink className="w-4 h-4" /> Abrir en pestaña
                        </a>
                    </div>
                </div>
            );
        }
        return (
            <div className={`overflow-hidden ${PREVIEW_SURFACE}`}>
                <iframe
                    src={doc.url}
                    title={doc.nombre_original || titulo}
                    className="w-full border-0 bg-white"
                    style={{ height: 'min(45vh, 400px)' }}
                />
            </div>
        );
    };

    const actualizarEnvio = (idx, campo, valor) => {
        // Solo tipo de caja (rellena catálogo) y peso real son editables.
        if (campo !== 'catalogo_tipo_caja_id' && campo !== 'peso_real_kg') return;
        setErrorCampo(null);
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

    const agregarEnvio = () => {
        focusNuevoEnvioRef.current = true;
        setEnvios((prev) => [...prev, envioVacio()]);
    };
    const quitarEnvio = (idx) => {
        setEnvios((prev) => (prev.length <= 1 ? prev : prev.filter((_, i) => i !== idx)));
    };

    const actualizarRevision = (idx, campo, valor) => {
        if (campo !== 'expandido') setErrorCampo(null);
        setRevisiones((prev) => prev.map((r, i) => (i === idx ? { ...r, [campo]: valor } : r)));
    };

    const setEvidenciasRevision = (idx, files, previews) => {
        setRevisiones((prev) => prev.map((r, i) => (i === idx ? { ...r, evidencias: files, previews } : r)));
    };

    const quitarRevision = (idx) => {
        const rev = revisiones[idx];
        if (!rev) return;
        revocarPreviews(rev.previews);
        setRevisiones((prev) => prev.filter((_, i) => i !== idx));
    };

    const agregarProducto = (producto) => {
        setRevisiones((prev) => [...prev, revisionDesdeProducto(producto)]);
        setSkuError('');
        setSkuQuery('');
        setSkuResultados([]);
        reproducirBipConfirmacion();
        if (!esCampo) {
            window.setTimeout(() => skuPistolaRef.current?.focus(), 50);
        }
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
            const exacto = lote.find((p) => String(p.sku).toLowerCase() === q.toLowerCase()
                || String(p.codigo_barras || '').toLowerCase() === q.toLowerCase());
            // Coincidencia exacta de SKU/código: agregar siempre (pistola/móvil no dependen solo de Enter).
            if (exacto) {
                agregarProducto(exacto);
            } else if (autoAgregar) {
                if (lote.length === 1) {
                    agregarProducto(lote[0]);
                } else if (lote.length === 0) {
                    setSkuError('Sin coincidencias en el inventario de este almacén.');
                    reproducirBipError();
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
        // PC (pistola): listar al teclear; auto-agregar solo con Enter en onSkuKeyDown.
        skuDebounceRef.current = setTimeout(() => buscarProductos(v), 350);
    };

    const onSkuKeyDown = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(skuDebounceRef.current);
            buscarProductos(skuQuery, { autoAgregar: true });
        }
    };

    const abrirSesionEvidencia = async () => {
        desbloquearBipAudio();
        try {
            const { data } = await window.axios.post(route('control_pedidos.cedis.sesion_evidencia.store', pedido.id));
            setSesionEvidencia(data);
            setCelularConectado(false);
            setModalQr(true);
        } catch (err) {
            setAlerta({
                abierto: true,
                tipo: 'error',
                titulo: 'Evidencias',
                mensaje: err.response?.data?.message || 'No se pudo generar el QR.',
            });
        }
    };

    const cancelarSesionEvidencia = async () => {
        try {
            await window.axios.post(route('control_pedidos.cedis.sesion_evidencia.cancelar', pedido.id));
        } catch {
            /* ignore */
        }
        setSesionEvidencia(null);
        setCelularConectado(false);
        setModalQr(false);
    };

    const avisarCampo = (id, mensaje) => {
        setPaso(id === 'cedis-sku-pesaje' || id.startsWith('pesaje-comentario-') || id.startsWith('pesaje-evidencia-producto-') ? 1 : 2);
        if (id.startsWith('pesaje-comentario-') || id.startsWith('pesaje-evidencia-producto-')) setListaProductosAbierta(true);
        setErrorCampo({ id, mensaje });
        window.requestAnimationFrame(() => {
            const campo = document.getElementById(id);
            campo?.scrollIntoView({ block: 'center' });
            campo?.focus({ preventScroll: true });
        });
    };
    const atributosError = (id) => ({
        'aria-invalid': errorCampo?.id === id || undefined,
        'aria-describedby': errorCampo?.id === id ? `${id}-error` : undefined,
    });
    const mensajeError = (id) => errorCampo?.id === id
        ? <p id={`${id}-error`} role="alert" className="text-sm theme-text-peligro m-0 mt-2">{errorCampo.mensaje}</p>
        : null;

    const confirmar = () => {
        if (procesando) return;
        setErrorCampo(null);
        if (!soloRevisiones) {
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
                    avisarCampo(`pesaje-caja-${e.client_uuid}`, 'Selecciona el tipo de caja para este envío.');
                    return;
                }
                if (e.largo === '' || Number.isNaN(largo) || largo < 0
                    || e.ancho === '' || Number.isNaN(ancho) || ancho < 0
                    || e.alto === '' || Number.isNaN(alto) || alto < 0) {
                    setAlerta({ abierto: true, tipo: 'error', titulo: `Envío ${n}`, mensaje: 'Indique largo, ancho y alto válidos.' });
                    return;
                }
                if (e.peso_real_kg === '' || Number.isNaN(pesoReal) || pesoReal < 0) {
                    avisarCampo(`pesaje-peso-${e.client_uuid}`, 'Ingresa un peso válido en kilogramos.');
                    return;
                }
                if (e.peso_volumetrico_kg === '' || Number.isNaN(pesoVol) || pesoVol < 0) {
                    setAlerta({ abierto: true, tipo: 'error', titulo: `Envío ${n}`, mensaje: 'Indique el peso volumétrico.' });
                    return;
                }
            }

            const cambiosPiezas = baselineConsulta
                ? resumenActualizacionConsulta(baselineConsulta.productos, snapshotConsulta(revisiones, envios).productos)
                : null;
            const paqueteIgual = !cambiosPiezas || cambiosPiezas.sinCambioPiezas;
            for (let i = 0; i < envios.length; i++) {
                const hayLocal = evidenciasPorEnvio[i]?.archivos?.length;
                const hayRemota = evidenciasPorEnvio[i]?.previews?.some((p) => p.remoto && (!p.vigente || paqueteIgual));
                if (!hayLocal && !hayRemota) {
                    avisarCampo(`pesaje-evidencia-${envios[i].client_uuid}`, 'Adjunta una foto del contenido de esta caja.');
                    return;
                }
            }
        } else if (revisiones.length === 0) {
            avisarCampo('cedis-sku-pesaje', 'Escanea o busca al menos un producto para registrar la consulta.');
            return;
        } else {
            const bultos = Number(bultosAproximados);
            if (bultosAproximados === '' || Number.isNaN(bultos) || bultos < 1) {
                avisarCampo('pesaje-bultos', empaqueConsulta.mensajeRequerido);
                return;
            }
            if (exigeCajaSinPeso && !Number(tipoCajaRevision)) {
                avisarCampo('pesaje-tipo-caja-revision', 'Selecciona el tipo de caja. No se piden kilogramos.');
                return;
            }
            const hayLocal = evidenciasLote.archivos?.length;
            const cambiosLote = baselineConsulta
                ? resumenActualizacionConsulta(baselineConsulta.productos, snapshotConsulta(revisiones, []).productos)
                : null;
            const loteIgual = (!cambiosLote || cambiosLote.sinCambioPiezas)
                && Number(bultosAproximados) === Number(pedido.numero_cajas);
            const hayRemota = evidenciasLote.previews?.some((p) => p.remoto && (!p.vigente || loteIgual));
            if (!hayLocal && !hayRemota) {
                avisarCampo('pesaje-evidencia-lote', 'Adjunta una foto de todos los productos del pedido juntos.');
                return;
            }
        }

        const piezasPedido = Number(pedido.cantidad_piezas || 0);
        if (piezasPedido > 0 && cantidadSolicitadaRevisada < piezasPedido && !avisoPiezasRef.current) {
            avisoPiezasRef.current = true;
            setAlerta({
                abierto: true,
                tipo: 'warning',
                titulo: 'Piezas por revisar',
                mensaje: `El pedido tiene ${piezasPedido} piezas y solo revisó ${cantidadSolicitadaRevisada}. Confirme de nuevo para continuar, o agregue las piezas faltantes. Si falta stock, márquela Sin existencias; si agregó una de más, elimínela.`,
            });
            return;
        }

        for (let i = 0; i < revisiones.length; i++) {
            const r = revisiones[i];
            if (!r.descripcion_producto.trim()) {
                setAlerta({ abierto: true, tipo: 'error', titulo: `Producto ${i + 1}`, mensaje: 'Producto sin descripción.' });
                return;
            }
            if (trasladoInterno && (!Number.isInteger(Number(r.cantidad_solicitada)) || Number(r.cantidad_solicitada) < 1
                || r.cantidad_encontrada === '' || r.cantidad_encontrada == null || !Number.isInteger(Number(r.cantidad_encontrada))
                || Number(r.cantidad_encontrada) < 0 || Number(r.cantidad_encontrada) > Number(r.cantidad_solicitada)
                || (r.estado_fisico === 'sin_existencia' && Number(r.cantidad_encontrada) !== 0))) {
                actualizarRevision(i, 'expandido', true);
                setAlerta({ abierto: true, tipo: 'error', titulo: `Producto ${i + 1}`, mensaje: 'Revise las cantidades solicitadas y encontradas.' });
                return;
            }
            if (requiereComentario(r.estado_fisico) || (trasladoInterno && Number(r.cantidad_encontrada) < Number(r.cantidad_solicitada))) {
                if (!r.comentario.trim()) {
                    actualizarRevision(i, 'expandido', true);
                    avisarCampo(`pesaje-comentario-${r.client_uuid}`, r.estado_fisico === 'sin_existencia'
                        ? 'Indica qué falta y cómo debe proceder Ventas.'
                        : 'Describe el daño o problema de esta pieza.');
                    return;
                }
            }
            if (requiereEvidencia(r.estado_fisico) && !r.evidencias?.length && !evidenciaProductoSigue(r)) {
                actualizarRevision(i, 'expandido', true);
                avisarCampo(`pesaje-evidencia-producto-${r.client_uuid}`, 'Adjunta una foto que muestre el daño o problema de esta pieza.');
                return;
            }
        }

        setConfirmacion('registrar');
    };

    const enviarPesaje = () => {
        const uuidsActuales = new Set(envios.map((e) => e.client_uuid).filter(Boolean));
        const retirados = uuidsGuardados.filter((u) => !uuidsActuales.has(u));
        if (retirados.length > 0 && !String(motivoRetiro || '').trim()) {
            setPedirMotivoRetiro(true);
            setAlerta({
                abierto: true,
                tipo: 'error',
                titulo: 'Motivo de retiro',
                mensaje: 'Indique el motivo para retirar envíos que ya estaban guardados.',
            });
            return;
        }

        const cajas = soloRevisiones
            ? []
            : envios.map((e) => ({
                catalogo_tipo_caja_id: Number(e.catalogo_tipo_caja_id),
                largo: Number(e.largo),
                ancho: Number(e.ancho),
                alto: Number(e.alto),
                peso_real_kg: Number(e.peso_real_kg),
                peso_volumetrico_kg: Number(e.peso_volumetrico_kg),
            }));
        const form = new FormData();
        if (almacenBusquedaId) form.append('almacen_id', String(almacenBusquedaId));
        cajas.forEach((c, i) => {
            Object.entries(c).forEach(([k, v]) => form.append(`cajas[${i}][${k}]`, String(v)));
            if (envios[i]?.client_uuid) {
                const uuid = uuidOperativo(envios[i].client_uuid);
                form.append(`cajas[${i}][client_uuid]`, uuid);
                form.append(`cajas[${i}][uuid_operativo]`, uuid);
            }
        });
        if (retirados.length > 0 && motivoRetiro) {
            form.append('motivo_retiro', String(motivoRetiro).trim());
        }
        form.append('estado_fisico_general', estadoGeneralDerivado);
        form.append('comentario_fisico_general', '');
        if (soloRevisiones) {
            form.append('numero_cajas', String(Number(bultosAproximados)));
            if (exigeCajaSinPeso) form.append('catalogo_tipo_caja_id', String(Number(tipoCajaRevision)));
            (evidenciasLote.archivos || []).forEach((f, j) => form.append(`evidencias_generales[${j}]`, f));
        }
        if (!soloRevisiones) {
            evidenciasPorEnvio.forEach((slot, i) => {
                (slot.archivos || []).forEach((f, j) => form.append(`evidencias_envios[${i}][${j}]`, f));
            });
        }
        revisiones.forEach((r, i) => {
            form.append(`revisiones[${i}][descripcion_producto]`, r.descripcion_producto);
            if (r.producto_id) form.append(`revisiones[${i}][producto_id]`, String(r.producto_id));
            if (r.sku) form.append(`revisiones[${i}][sku]`, String(r.sku));
            if (trasladoInterno) {
                form.append(`revisiones[${i}][cantidad_solicitada]`, String(r.cantidad_solicitada));
                form.append(`revisiones[${i}][cantidad_encontrada]`, String(r.cantidad_encontrada));
            }
            form.append(`revisiones[${i}][estado_fisico]`, r.estado_fisico);
            form.append(`revisiones[${i}][comentario]`, r.comentario || '');
            form.append(`revisiones[${i}][unica_pieza]`, r.unica_pieza ? '1' : '0');
            form.append(`revisiones[${i}][mejor_ejemplar]`, r.mejor_ejemplar ? '1' : '0');
            if (r.client_uuid) form.append(`revisiones[${i}][client_uuid]`, r.client_uuid);
            const mismoProducto = String(r.producto_id || '') === String(r.producto_id_original || '')
                && String(r.sku || '') === String(r.sku_original || '');
            if (r.revision_id && mismoProducto) form.append(`revisiones[${i}][revision_id]`, String(r.revision_id));
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

    const hayDatosPesaje = revisiones.length > 0
        || envios.some((e) => e.peso_real_kg || e.catalogo_tipo_caja_id)
        || evidenciasPorEnvio.some((s) => s.archivos?.length)
        || evidenciasLote.archivos?.length;

    const pedirCerrar = () => {
        if (procesando) return;
        if (hayDatosPesaje) {
            setConfirmacion('cerrar');
            return;
        }
        onClose();
    };

    const totalCobrado = envios.reduce((acc, e) => {
        const cobrado = calcularPesoCobradoGuia(e.peso_real_kg, e.peso_volumetrico_kg);
        return acc + (cobrado === '' ? 0 : Number(cobrado));
    }, 0);

    const resumenAntesDespues = (() => {
        if (!baselineConsulta) return null;
        const despues = snapshotConsulta(revisiones, soloRevisiones ? [] : envios);
        const piezas = resumenActualizacionConsulta(baselineConsulta.productos, despues.productos);
        return {
            antes: baselineConsulta,
            despues,
            piezas,
            cambioEnvios: !soloRevisiones && baselineConsulta.envios !== despues.envios,
            cambioPeso: !soloRevisiones
                && Math.abs((baselineConsulta.pesoCobrado || 0) - (despues.pesoCobrado || 0)) > 0.0001,
        };
    })();

    const pasos = ['Pedido', 'Productos', soloRevisiones ? empaqueConsulta.paso : 'Cajas'];
    const instrucciones = [
        'Consulta el archivo del pedido.',
        'Registra las piezas y su condición.',
        soloRevisiones ? empaqueConsulta.instruccion : 'Registra el peso y fotografía el contenido de cada caja.',
    ];
    const cambiarPaso = (siguiente) => {
        if (procesando) return;
        setPaso(siguiente);
        window.requestAnimationFrame(() => {
            cuerpoRef.current?.scrollTo({ top: 0, behavior: 'instant' });
            const id = ['pesaje-referencia', 'pesaje-productos', 'pesaje-envios'][siguiente];
            document.getElementById(id)?.focus({ preventScroll: true });
        });
    };

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
                key={rev.client_uuid}
                className="rounded-xl border theme-border theme-element overflow-hidden"
                open={Boolean(rev.expandido)}
                onToggle={(e) => actualizarRevision(idx, 'expandido', e.target.open)}
            >
                <summary className="flex items-center justify-between gap-2 px-3 py-2.5 cursor-pointer list-none min-h-[44px]">
                    <div className="min-w-0 flex-1 flex flex-wrap items-center gap-2">
                        <ChevronDown className={`w-4 h-4 theme-text-muted shrink-0 transition-transform ${rev.expandido ? 'rotate-180' : ''}`} />
                        {instancia && (
                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold tabular-nums theme-element border theme-border theme-text-main shrink-0">
                                {instancia}
                            </span>
                        )}
                        <p className="text-sm font-bold theme-text-main m-0 break-words line-clamp-2">{rev.descripcion_producto}</p>
                        <span className={badge.className} style={badge.style}>{badge.label}</span>
                    </div>
                    <button
                        type="button"
                        onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            setConfirmacion({ tipo: 'quitar_pieza', idx });
                        }}
                        className="p-2 min-h-[44px] min-w-[44px] rounded-xl border theme-border theme-element outline-none inline-flex items-center justify-center theme-text-main shrink-0"
                        aria-label="Eliminar pieza de la revisión"
                        title="Eliminar pieza (agregada por error)"
                    >
                        <Trash2 className="w-4 h-4" />
                    </button>
                </summary>
                <div className="px-3 pb-3 space-y-3 border-t theme-border pt-3">
                    <p className="text-xs theme-text-muted font-normal m-0">Agregue detalle solo si el estado no es Bueno o necesita foto/comentario.</p>
                    {trasladoInterno && (
                        <div className="grid grid-cols-2 gap-3">
                            <label className={SECCION}>Cantidad solicitada
                                <input type="number" min="1" step="1" required value={rev.cantidad_solicitada ?? ''}
                                    onChange={(e) => actualizarRevision(idx, 'cantidad_solicitada', e.target.value)} className={`${THEME_INPUT} w-full`} />
                            </label>
                            <label className={SECCION}>Cantidad encontrada
                                <input type="number" min="0" step="1" max={rev.cantidad_solicitada} required value={rev.cantidad_encontrada ?? ''}
                                    onChange={(e) => actualizarRevision(idx, 'cantidad_encontrada', e.target.value)} className={`${THEME_INPUT} w-full`} />
                            </label>
                        </div>
                    )}
                    <div>
                        <label htmlFor={`pesaje-estado-${rev.client_uuid}`} className={SECCION}>Estado físico</label>
                        <select id={`pesaje-estado-${rev.client_uuid}`} name={`estado-${rev.client_uuid}`} value={rev.estado_fisico} onChange={(e) => actualizarRevision(idx, 'estado_fisico', e.target.value)} className={`${THEME_SELECT} w-full py-2.5 min-h-[44px]`}>
                            {ESTADOS.map((c) => (
                                <option key={c} value={c}>{LABELS_ESTADO_FISICO[c]}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label htmlFor={`pesaje-comentario-${rev.client_uuid}`} className={SECCION}>Comentario{requiereComentario(rev.estado_fisico) ? ' *' : ''}</label>
                        <textarea
                            id={`pesaje-comentario-${rev.client_uuid}`}
                            {...atributosError(`pesaje-comentario-${rev.client_uuid}`)}
                            name={`comentario-${rev.client_uuid}`}
                            aria-required={requiereComentario(rev.estado_fisico)}
                            value={rev.comentario}
                            onChange={(e) => actualizarRevision(idx, 'comentario', e.target.value)}
                            className={`${THEME_TEXTAREA} w-full py-2.5 min-h-[60px]`}
                            placeholder={
                                rev.estado_fisico === 'sin_existencia'
                                    ? 'Indique qué falta / cómo debe proceder Ventas…'
                                    : (requiereComentario(rev.estado_fisico) ? 'Comentario obligatorio…' : 'Opcional…')
                            }
                        />
                        {mensajeError(`pesaje-comentario-${rev.client_uuid}`)}
                    </div>
                    {rev.estado_fisico === 'sin_existencia' && (
                        <p className="text-xs font-bold theme-text-info m-0">
                            Sin existencias: Ventas verá este aviso en el detalle del pedido (no hace falta adjuntar foto).
                        </p>
                    )}
                    <div className="gelia-pedidos-atributos" aria-label="Características de la pieza">
                        <label className="gelia-pedidos-atributo gelia-pedidos-atributo-control">
                            <input type="checkbox" checked={rev.unica_pieza} onChange={(e) => actualizarRevision(idx, 'unica_pieza', e.target.checked)} className="w-4 h-4" />
                            Única pieza
                        </label>
                        <label className="gelia-pedidos-atributo gelia-pedidos-atributo-control">
                            <input type="checkbox" checked={rev.mejor_ejemplar} onChange={(e) => actualizarRevision(idx, 'mejor_ejemplar', e.target.checked)} className="w-4 h-4" />
                            Mejor ejemplar
                        </label>
                    </div>
                    <GaleriaEvidencias
                        archivos={rev.evidencias || []}
                        previews={rev.previews || []}
                        id={`pesaje-evidencia-producto-${rev.client_uuid}`}
                        error={errorCampo?.id === `pesaje-evidencia-producto-${rev.client_uuid}` ? errorCampo.mensaje : undefined}
                        disabled={procesando}
                        obligatorio={requiereEvidencia(rev.estado_fisico)}
                        label="Evidencia del producto (solo si hay detalle)"
                        onChange={(files, previews) => setEvidenciasRevision(idx, files, previews)}
                        onVer={abrirGaleria}
                    />
                </div>
            </details>
        );
    });

    const accionesRespuesta = (
        <>
            <button type="button" onClick={() => paso > 0 ? cambiarPaso(paso - 1) : pedirCerrar()} className={`${BTN_SECONDARY} outline-none min-h-[44px]`} disabled={procesando}>{paso > 0 ? 'Atrás' : 'Cerrar'}</button>
            {paso < 2 ? (
                <button type="button" onClick={() => cambiarPaso(paso + 1)} disabled={procesando} className={`${BTN_PRIMARY} min-h-[44px]`}>{paso === 0 ? 'Revisar productos' : (soloRevisiones ? empaqueConsulta.ctaPaso : 'Cajas y evidencias')}</button>
            ) : (
                <button type="button" onClick={confirmar} disabled={procesando} aria-busy={procesando} className={`${BTN_PRIMARY} flex items-center justify-center gap-2 outline-none min-h-[44px]`}>
                    <Scale className="w-4 h-4" aria-hidden="true" /> <span>{procesando ? 'Guardando…' : (soloRevisiones ? 'Registrar consulta' : 'Registrar pesaje')}</span>
                </button>
            )}
        </>
    );

    return createPortal(
        <>
            <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`}>
                <div
                    {...dialog}
                    aria-labelledby="pesaje-titulo"
                    className={`${THEME_MODAL_SHELL} gelia-pedidos-dialog-workspace gelia-pedidos-respuesta max-w-3xl w-full flex flex-col`}
                    style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="p-4 md:p-6 border-b theme-border shrink-0 space-y-3">
                        <div className="flex justify-between items-start gap-3">
                            <div className="min-w-0">
                                <h2 id="pesaje-titulo" className="text-lg font-semibold theme-text-main m-0 mb-2">
                                    {soloRevisiones ? 'Responder consulta de mercancía' : 'Responder pesaje'}
                                </h2>
                                <EncabezadoFolioPedido pedido={pedido} />
                                <p className="text-xs theme-text-muted m-0 mt-1">
                                    {pedido.cliente?.nombre || '—'} · {formatearFechaNegocio(pedido.fecha)}
                                </p>
                            </div>
                            <button type="button" disabled={procesando} onClick={pedirCerrar} className="p-2 min-h-[44px] min-w-[44px] rounded-xl theme-element border theme-border outline-none shrink-0 inline-flex items-center justify-center theme-text-main" aria-label="Cerrar">
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                        <div className="flex flex-wrap gap-2 sm:justify-end">
                            {accionesRespuesta}
                        </div>
                    </div>

                    <nav className="gelia-pedidos-pasos" aria-label="Pasos para responder">
                        {pasos.map((label, index) => (
                            <button key={label} type="button" disabled={procesando}
                                aria-current={paso === index ? 'step' : undefined}
                                onClick={() => cambiarPaso(index)}>
                                <span aria-hidden="true">{index + 1}</span>{label}
                            </button>
                        ))}
                    </nav>
                    <div ref={cuerpoRef} className="gelia-modal-body p-4 md:p-6 space-y-5">
                        <p className="text-xs theme-text-muted m-0" role="status">Paso {paso + 1} de 3 · {instrucciones[paso]}</p>
                        {paso === 0 && (pedido.motivo_repesaje || pedido.consulta_actualizacion_pendiente) && (
                            <AvisoOperativoPedido label="Actualización de consulta" tono="warning" icon={Scale}>
                                Motivo: {LABELS_MOTIVO_REPESAJE[pedido.motivo_repesaje] || pedido.motivo_repesaje || 'cambio'}
                                . Revise el resumen antes → después, ajuste piezas/cajas y guarde.
                            </AvisoOperativoPedido>
                        )}

                        {paso === 2 && resumenAntesDespues && (
                            <div className="p-4 rounded-xl border theme-border theme-element space-y-3">
                                <p className={`${SECCION} m-0`}>Antes → después</p>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                    <div className="rounded-lg border theme-border p-3 space-y-1">
                                        <p className="text-xs font-medium theme-text-muted m-0">Antes (CEDIS)</p>
                                        <p className="m-0 font-bold theme-text-main">
                                            {resumenAntesDespues.antes.productos.length} producto(s)
                                            {!soloRevisiones && (
                                                <> · {resumenAntesDespues.antes.envios} envío(s) · {Math.round((resumenAntesDespues.antes.pesoCobrado || 0) * 10000) / 10000} kg</>
                                            )}
                                        </p>
                                        <ul className="m-0 pl-4 list-disc theme-text-muted font-bold max-h-28 overflow-y-auto">
                                            {resumenAntesDespues.antes.productos.slice(0, 12).map((p, i) => (
                                                <li key={`a-${i}`}>{p.label} ({LABELS_ESTADO_FISICO[p.estado] || p.estado})</li>
                                            ))}
                                            {resumenAntesDespues.antes.productos.length > 12 && (
                                                <li>+{resumenAntesDespues.antes.productos.length - 12} más</li>
                                            )}
                                        </ul>
                                    </div>
                                    <div className="rounded-lg border theme-border p-3 space-y-1" style={{ borderColor: 'color-mix(in srgb, var(--color-primario) 45%, transparent)' }}>
                                        <p className="text-xs font-medium theme-text-muted m-0">Después (a guardar)</p>
                                        <p className="m-0 font-bold theme-text-main">
                                            {resumenAntesDespues.despues.productos.length} producto(s)
                                            {!soloRevisiones && (
                                                <> · {resumenAntesDespues.despues.envios} envío(s) · {Math.round((resumenAntesDespues.despues.pesoCobrado || 0) * 10000) / 10000} kg</>
                                            )}
                                        </p>
                                        <ul className="m-0 pl-4 list-disc theme-text-muted font-bold max-h-28 overflow-y-auto">
                                            {resumenAntesDespues.despues.productos.slice(0, 12).map((p, i) => (
                                                <li key={`d-${i}`}>{p.label} ({LABELS_ESTADO_FISICO[p.estado] || p.estado})</li>
                                            ))}
                                            {resumenAntesDespues.despues.productos.length > 12 && (
                                                <li>+{resumenAntesDespues.despues.productos.length - 12} más</li>
                                            )}
                                        </ul>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2 text-xs font-semibold">
                                    {resumenAntesDespues.piezas.sinCambioPiezas && (
                                        <span className="px-2 py-1 rounded-lg theme-element theme-text-muted border theme-border">
                                            Sin cambios de piezas. Se conserva el motivo de la actualización.
                                        </span>
                                    )}
                                    {resumenAntesDespues.piezas.agregadas.length > 0 && (
                                        <span className="px-2 py-1 rounded-lg bg-emerald-500/15 text-emerald-700">
                                            {resumenAntesDespues.piezas.agregadas.length} agregada(s): {resumenAntesDespues.piezas.agregadas.map((p) => p.etiqueta).join(', ')}
                                        </span>
                                    )}
                                    {resumenAntesDespues.piezas.retiradas.length > 0 && (
                                        <span className="px-2 py-1 rounded-lg bg-rose-500/15 text-rose-700">
                                            {resumenAntesDespues.piezas.retiradas.length} retirada(s): {resumenAntesDespues.piezas.retiradas.map((p) => p.etiqueta).join(', ')}
                                        </span>
                                    )}
                                    {resumenAntesDespues.piezas.sustituidas.length > 0 && (
                                        <span className="px-2 py-1 rounded-lg gelia-estado-vivo gelia-estado-vivo--aviso">
                                            {resumenAntesDespues.piezas.sustituidas.length} sustituida(s)
                                        </span>
                                    )}
                                    {resumenAntesDespues.piezas.cantidades.length > 0 && (
                                        <span className="px-2 py-1 rounded-lg gelia-estado-vivo gelia-estado-vivo--aviso">
                                            {resumenAntesDespues.piezas.cantidades.length} con cantidad modificada
                                        </span>
                                    )}
                                    {resumenAntesDespues.piezas.condiciones.length > 0 && (
                                        <span className="px-2 py-1 rounded-lg gelia-estado-vivo gelia-estado-vivo--aviso">
                                            {resumenAntesDespues.piezas.condiciones.length} con condición modificada
                                        </span>
                                    )}
                                    {resumenAntesDespues.cambioEnvios && (
                                        <span className="px-2 py-1 rounded-lg gelia-estado-vivo gelia-estado-vivo--aviso">
                                            Envios {resumenAntesDespues.antes.envios} → {resumenAntesDespues.despues.envios}
                                        </span>
                                    )}
                                    {resumenAntesDespues.cambioPeso && (
                                        <span className="px-2 py-1 rounded-lg gelia-estado-vivo gelia-estado-vivo--aviso">
                                            Peso cobrado cambió (se puede invalidar costo de envío)
                                        </span>
                                    )}
                                </div>
                            </div>
                        )}

                        <div className="space-y-4">
                        <div hidden={paso !== 0} className="gelia-pedidos-paso space-y-4">
                        <section id="pesaje-referencia" tabIndex={-1} className="gelia-pedidos-seccion space-y-4">
                            <div className="space-y-2">
                                <div className="flex items-center justify-between gap-2 flex-wrap">
                                    <h3 className="gelia-pedidos-seccion-titulo">PDF o foto del pedido</h3>
                                    {pdfPedido?.url && (
                                        <button type="button" onClick={() => abrirGaleria([pdfPedido], 0)} className={`${BTN_SECONDARY} inline-flex items-center justify-center gap-1.5 text-xs min-h-[44px]`}>
                                            <FileText className="w-3.5 h-3.5" /> Ver
                                        </button>
                                    )}
                                </div>
                                {renderSoporte(pdfPedido, 'Soporte del pedido')}
                            </div>
                            {anexosPiezas.length > 0 && (
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between gap-2 flex-wrap">
                                        <label className={`${SECCION} m-0`}>Piezas adicionales ({anexosPiezas.length})</label>
                                        <button type="button" onClick={() => abrirGaleria(anexosPiezas, 0)} className={`${BTN_SECONDARY} inline-flex items-center justify-center gap-1.5 text-xs min-h-[44px]`}>
                                            <FileText className="w-3.5 h-3.5" /> Ver
                                        </button>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {anexosPiezas.map((doc, idx) => (
                                            <button
                                                key={doc.id || idx}
                                                type="button"
                                                onClick={() => abrirGaleria(anexosPiezas, idx)}
                                                className="relative w-20 h-20 rounded-xl overflow-hidden border theme-border theme-element outline-none group"
                                                title={doc.nombre_original || 'Ver anexo'}
                                            >
                                                {esImagenDoc(doc) ? (
                                                    <img src={doc.url} alt={doc.nombre_original || 'Anexo'} width="80" height="80" className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-200" />
                                                ) : (
                                                    <div className="w-full h-full flex items-center justify-center text-xs font-medium theme-text-muted group-hover:scale-105 transition-transform">PDF</div>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </section>

                        {soloRevisiones && (
                        <div className="space-y-2 p-4 rounded-xl border theme-border theme-element">
                            <p className={`${SECCION} m-0`}>Sucursal destino</p>
                            <p className="text-sm font-bold theme-text-main m-0">
                                {etiquetaSucursal(pedido.sucursal_destino || pedido.sucursalDestino || {
                                    id: pedido.sucursal_destino_id,
                                    nombre: pedido.sucursal_destino_nombre,
                                    codigo: pedido.sucursal_destino_codigo,
                                })}
                            </p>
                        </div>
                        )}

                        </div>
                        <div className="flex flex-wrap items-end justify-between gap-2 mb-3">
                            <p className="text-sm font-bold theme-text-main m-0">Almacén y pedido</p>
                            {pdfPedido?.url && (
                                <button type="button" onClick={() => abrirGaleria([pdfPedido], 0)} className={`${BTN_SECONDARY} inline-flex items-center justify-center gap-1.5 text-xs min-h-[44px]`}>
                                    <FileText className="w-3.5 h-3.5" /> Ver PDF del pedido
                                </button>
                            )}
                        </div>
                        <label className="block text-sm font-bold mb-3">Almacén del catálogo de preparación
                            <select className={`${THEME_SELECT} w-full`} value={almacenBusquedaId || ''} disabled={procesando || revisiones.length > 0}
                                onChange={(e) => { clearTimeout(skuDebounceRef.current); skuAbortRef.current?.abort(); setSkuQuery(''); setSkuResultados([]); setSkuError(''); setAlmacenElegidoId(e.target.value); }}>
                                <option value="">Seleccione almacén…</option>
                                {(almacenesBusqueda || []).map((a) => <option key={a.id} value={a.id}>{etiquetaAlmacen(a)}</option>)}
                            </select>
                            {revisiones.length > 0 && <span className="block text-xs theme-text-muted">Las piezas conservan el almacén utilizado al registrarlas.</span>}
                        </label>
                        <fieldset disabled={procesando} className="gelia-pedidos-respuesta-main min-w-0 border-0 p-0 m-0">
                        <section id="pesaje-productos" tabIndex={-1} hidden={paso !== 1} className="gelia-pedidos-paso gelia-pedidos-seccion space-y-4">
                            <div className="flex items-center justify-between gap-2 flex-wrap">
                                <h3 className="gelia-pedidos-seccion-titulo">{soloRevisiones ? 'Registro de productos' : 'Revisión física de productos'}</h3>
                                <span className="text-xs theme-text-muted tabular-nums">{cantidadSolicitadaRevisada} de {pedido.cantidad_piezas || '—'} piezas</span>
                                {borradorMsg && (
                                    <p className="text-xs font-medium theme-text-muted m-0" role="status">{borradorMsg}</p>
                                )}
                            </div>
                            <p className="text-xs theme-text-muted font-normal m-0">
                                Cada pieza inicia en Bueno. Abre su detalle para cambiar la condición o adjuntar fotos. Si falta una pieza, registra Sin existencias.
                            </p>

                            <div className="space-y-2">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-xs font-medium theme-text-muted m-0">Productos revisados</p>
                                    <button type="button" onClick={() => document.getElementById('cedis-sku-pesaje')?.focus()} className={`${BTN_SECONDARY} text-xs flex items-center gap-1.5 min-h-[44px]`}>
                                        <Plus className="w-3.5 h-3.5" /> Producto
                                    </button>
                                </div>
                                <label htmlFor="cedis-sku-pesaje" className={SECCION}>SKU / código de barras</label>
                                {!esCampo && (
                                    <p className="text-xs font-semibold m-0 mb-1 theme-text-exito">
                                        Pistola lista · escaneo continuo
                                    </p>
                                )}
                                <p className="text-xs font-medium theme-text-muted m-0 mb-2">
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
                                        name: 'sku',
                                        ...atributosError('cedis-sku-pesaje'),
                                        ref: skuPistolaRef,
                                        placeholder: esCampo ? 'Escanear o escribir SKU…' : 'Pistola: escanee y pulse Enter…',
                                        className: `${THEME_INPUT} w-full py-3 min-h-[44px]`,
                                        onKeyDown: onSkuKeyDown,
                                        autoComplete: 'off',
                                        disabled: !almacenBusquedaId,
                                        onFocus: desbloquearBipAudio,
                                    }}
                                />
                                {mensajeError('cedis-sku-pesaje')}
                                <div className="flex flex-wrap gap-2">
                                    <button type="button" onClick={() => buscarProductos(skuQuery, { autoAgregar: true })} disabled={skuCargando || String(skuQuery).trim().length < 2 || !almacenBusquedaId} className={`${BTN_SECONDARY} text-xs flex items-center gap-1.5 min-h-[44px] outline-none`}>
                                        <Search className="w-3.5 h-3.5" /> {skuCargando ? 'Buscando…' : 'Buscar y agregar'}
                                    </button>
                                    {!esCampo && <button type="button" onClick={abrirSesionEvidencia} className={`${BTN_SECONDARY} text-xs flex items-center gap-1.5 min-h-[44px] outline-none`}>
                                        <Smartphone className="w-3.5 h-3.5" /> Tomar evidencias con celular
                                    </button>}
                                </div>
                                {skuError && <p className="text-xs font-bold theme-text-aviso m-0">{skuError}</p>}
                                {skuResultados.length > 0 && (
                                    <div className="theme-surface border theme-border rounded-xl shadow-xl max-h-48 overflow-y-auto p-2">
                                        {skuResultados.map((p) => (
                                            <button key={p.id} type="button" onClick={() => agregarProducto(p)} className="w-full text-left p-3 rounded-lg hover:bg-[color-mix(in_srgb,var(--theme-text-main)_6%,transparent)] text-xs font-bold theme-text-main outline-none">
                                                <span className="tabular-nums">{p.sku}</span>
                                                <span className="theme-text-muted"> — </span>
                                                {p.descripcion}
                                                <span className="block text-xs theme-text-muted font-normal mt-0.5">
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
                                <p className="text-xs theme-text-muted font-normal m-0">Sin productos agregados.</p>
                            )}

                            {productosCompactos ? (
                                <details
                                    className="rounded-xl border theme-border overflow-hidden"
                                    open={listaProductosAbierta}
                                    onToggle={(e) => setListaProductosAbierta(e.target.open)}
                                >
                                    <summary className="flex items-center justify-between gap-2 px-3 py-3 cursor-pointer list-none min-h-[48px] theme-element">
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold theme-text-main m-0">
                                                {revisiones.length} productos
                                                {productosConDetalle > 0 ? ` · ${productosConDetalle} con detalle` : ''}
                                            </p>
                                            <p className="text-xs theme-text-muted font-normal m-0 mt-1 line-clamp-2">
                                                {revisiones.map((r, i) => {
                                                    const tag = instancias[i];
                                                    const base = r.sku || r.descripcion_producto;
                                                    return tag ? `${base} (${tag})` : base;
                                                }).join(' · ')}
                                            </p>
                                        </div>
                                        <ChevronDown className={`w-4 h-4 theme-text-muted shrink-0 transition-transform ${listaProductosAbierta ? 'rotate-180' : ''}`} />
                                    </summary>
                                    <div className="p-3 space-y-2 border-t theme-border">
                                        {listaProductos}
                                    </div>
                                </details>
                            ) : (
                                <div className="space-y-2">{listaProductos}</div>
                            )}
                        </section>

                        {soloRevisiones && (
                        <section id="pesaje-envios" tabIndex={-1} hidden={paso !== 2} className="gelia-pedidos-paso gelia-pedidos-seccion">
                            <h3 className="gelia-pedidos-seccion-titulo mb-3">Evidencia final del pedido</h3>
                            <p className="text-xs theme-text-muted font-normal m-0 mb-3">
                                {empaqueConsulta.introEvidencia}
                            </p>
                            <div className="p-4 rounded-xl border theme-border theme-element space-y-3">
                                <div>
                                    <label htmlFor="pesaje-bultos" className={SECCION}>{empaqueConsulta.campo}</label>
                                    <input
                                        id="pesaje-bultos" {...atributosError('pesaje-bultos')} name="bultos" aria-required="true"
                                        type="number"
                                        min={1}
                                        max={999}
                                        inputMode="numeric"
                                        value={bultosAproximados}
                                        onChange={(e) => setBultosAproximados(e.target.value)}
                                        placeholder="Ej. 3…"
                                        className={`${THEME_INPUT} w-full py-3 min-h-[44px] max-w-[10rem]`}
                                    />
                                    {mensajeError('pesaje-bultos')}
                                    <p className="text-xs theme-text-muted font-normal m-0 mt-1">
                                        {empaqueConsulta.ayuda}
                                    </p>
                                </div>
                                {exigeCajaSinPeso && (
                                <div>
                                    <label htmlFor="pesaje-tipo-caja-revision" className={SECCION}>Tipo de caja</label>
                                    <select
                                        id="pesaje-tipo-caja-revision"
                                        {...atributosError('pesaje-tipo-caja-revision')}
                                        value={tipoCajaRevision}
                                        onChange={(e) => setTipoCajaRevision(e.target.value)}
                                        className={`${THEME_SELECT} w-full py-3 min-h-[44px]`}
                                    >
                                        <option value="">Seleccionar…</option>
                                        {tiposCaja.map((c) => (
                                            <option key={c.id} value={c.id}>{c.nombre}</option>
                                        ))}
                                    </select>
                                    {mensajeError('pesaje-tipo-caja-revision')}
                                    <p className="text-xs theme-text-muted font-normal m-0 mt-1">
                                        El transporte pide caja. No captura kilogramos ni peso volumétrico.
                                    </p>
                                </div>
                                )}
                                <GaleriaEvidencias
                                    id="pesaje-evidencia-lote" error={errorCampo?.id === 'pesaje-evidencia-lote' ? errorCampo.mensaje : undefined} disabled={procesando}
                                    archivos={evidenciasLote.archivos}
                                    previews={evidenciasLote.previews}
                                    obligatorio
                                    label="Fotos del lote (productos del pedido)"
                                    onChange={(files, previews) => setEvidenciasLote({ archivos: files, previews })}
                                    onVer={abrirGaleria}
                                />
                            </div>
                        </section>
                        )}

                        {!soloRevisiones && (
                        <section id="pesaje-envios" tabIndex={-1} hidden={paso !== 2} className="gelia-pedidos-paso gelia-pedidos-seccion">
                            <h3 className="gelia-pedidos-seccion-titulo mb-3">Envíos</h3>
                            <p className="text-xs theme-text-muted font-normal m-0 mb-3">
                                Por cada caja adjunte la foto del lote de productos que van en ese envío.
                            </p>
                            <div className="space-y-4">
                                {envios.map((envio, idx) => {
                                    const cobrado = calcularPesoCobradoGuia(envio.peso_real_kg, envio.peso_volumetrico_kg);
                                    const slot = evidenciasPorEnvio[idx] || slotEnvioVacio();
                                    const esUltimo = idx === envios.length - 1;
                                    return (
                                        <div key={envio.client_uuid} ref={esUltimo ? envioNuevoRef : undefined} className="p-4 rounded-xl border theme-border theme-element space-y-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <p className="text-sm font-semibold theme-text-main m-0">{etiquetaEnvio(idx, { tipo_caja: tiposCaja.find((t) => String(t.id) === String(envio.catalogo_tipo_caja_id)) })}</p>
                                                <button type="button" onClick={() => envios.length > 1 && setConfirmacion({ tipo: 'quitar_envio', idx })} disabled={envios.length <= 1} className="p-2 min-h-[44px] min-w-[44px] rounded-xl border theme-border theme-element outline-none disabled:opacity-40 inline-flex items-center justify-center theme-text-main" aria-label={`Quitar envío ${idx + 1}`}>
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                            <div className="space-y-3">
                                                <div>
                                                    <label htmlFor={`pesaje-caja-${envio.client_uuid}`} className={SECCION}>Tipo de caja *</label>
                                                    <select id={`pesaje-caja-${envio.client_uuid}`} {...atributosError(`pesaje-caja-${envio.client_uuid}`)} name={`caja-${envio.client_uuid}`} aria-required="true" value={envio.catalogo_tipo_caja_id} onChange={(e) => actualizarEnvio(idx, 'catalogo_tipo_caja_id', e.target.value)} className={`${THEME_SELECT} w-full py-3 min-h-[44px]`}>
                                                        <option value="">Seleccionar…</option>
                                                        {tiposCaja.map((c) => (
                                                            <option key={c.id} value={c.id}>{c.nombre}</option>
                                                        ))}
                                                    </select>
                                                    {mensajeError(`pesaje-caja-${envio.client_uuid}`)}
                                                </div>
                                                {envio.catalogo_tipo_caja_id ? (
                                                    <details className="rounded-xl border theme-border theme-surface p-3 sm:p-4">
                                                        <summary className="cursor-pointer min-h-[44px] text-xs theme-text-muted">
                                                            Datos del catálogo · {envio.largo || '—'} × {envio.ancho || '—'} × {envio.alto || '—'} cm
                                                            <span className="block mt-1 text-sm font-semibold theme-text-main">Peso cobrado: {cobrado === '' ? '—' : cobrado} kg</span>
                                                        </summary>
                                                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                                            <div>
                                                                <p className="text-xs font-medium theme-text-muted m-0">Largo</p>
                                                                <p className="text-base font-semibold theme-text-main m-0 mt-0.5 tabular-nums">{envio.largo || '—'} <span className="text-xs font-bold theme-text-muted">cm</span></p>
                                                            </div>
                                                            <div>
                                                                <p className="text-xs font-medium theme-text-muted m-0">Ancho</p>
                                                                <p className="text-base font-semibold theme-text-main m-0 mt-0.5 tabular-nums">{envio.ancho || '—'} <span className="text-xs font-bold theme-text-muted">cm</span></p>
                                                            </div>
                                                            <div>
                                                                <p className="text-xs font-medium theme-text-muted m-0">Alto</p>
                                                                <p className="text-base font-semibold theme-text-main m-0 mt-0.5 tabular-nums">{envio.alto || '—'} <span className="text-xs font-bold theme-text-muted">cm</span></p>
                                                            </div>
                                                            <div>
                                                                <p className="text-xs font-medium theme-text-muted m-0">Peso vol.</p>
                                                                <p className="text-base font-semibold theme-text-main m-0 mt-0.5 tabular-nums">{envio.peso_volumetrico_kg || '—'} <span className="text-xs font-bold theme-text-muted">kg</span></p>
                                                            </div>
                                                            <div className="col-span-2 sm:col-span-2">
                                                                <p className="text-xs font-medium theme-text-muted m-0">Peso cobrado</p>
                                                                <p className="text-lg font-semibold theme-text-main m-0 mt-0.5 tabular-nums">{cobrado !== '' ? cobrado : '—'} <span className="text-xs font-bold theme-text-muted">kg</span></p>
                                                            </div>
                                                        </div>
                                                    </details>
                                                ) : (
                                                    <p className="text-xs theme-text-muted font-normal m-0">Seleccione el tipo de caja para ver medidas del catálogo.</p>
                                                )}
                                                <div>
                                                    <label htmlFor={`pesaje-peso-${envio.client_uuid}`} className={SECCION}>Peso real (kg) *</label>
                                                    <input id={`pesaje-peso-${envio.client_uuid}`} {...atributosError(`pesaje-peso-${envio.client_uuid}`)} name={`peso-${envio.client_uuid}`} aria-required="true" type="number" step="0.0001" min="0" inputMode="decimal" value={envio.peso_real_kg} onChange={(e) => actualizarEnvio(idx, 'peso_real_kg', e.target.value)} onFocus={(e) => e.target.scrollIntoView({ block: 'nearest' })} className={`${THEME_INPUT} w-full py-3 min-h-[44px] text-base`} placeholder="0.0000" />
                                                    {mensajeError(`pesaje-peso-${envio.client_uuid}`)}
                                                </div>
                                            </div>
                                            <GaleriaEvidencias
                                                id={`pesaje-evidencia-${envio.client_uuid}`} error={errorCampo?.id === `pesaje-evidencia-${envio.client_uuid}` ? errorCampo.mensaje : undefined} disabled={procesando}
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
                            <button type="button" onClick={agregarEnvio} className={`${BTN_SECONDARY} w-full min-h-[44px] text-xs flex items-center justify-center gap-1.5 outline-none mt-3`}>
                                <Plus className="w-3.5 h-3.5" /> Otro envío
                            </button>
                            {envios.length > 1 && (
                                <p className="text-xs theme-text-muted font-normal m-0 mt-3">
                                    Total peso cobrado: {Math.round(totalCobrado * 10000) / 10000} kg · {envios.length} envíos
                                </p>
                            )}
                            {(pedirMotivoRetiro || uuidsGuardados.some((u) => !envios.some((e) => e.client_uuid === u))) && (
                                <div className="mt-3">
                                    <label htmlFor="pesaje-motivo-retiro" className={SECCION}>Motivo de retiro de envíos *</label>
                                    <textarea
                                        id="pesaje-motivo-retiro" name="motivo_retiro" aria-required="true"
                                        value={motivoRetiro}
                                        onChange={(e) => setMotivoRetiro(e.target.value)}
                                        rows={2}
                                        className={`${THEME_TEXTAREA} w-full`}
                                        placeholder="Explique por qué se retira un envío ya guardado…"
                                    />
                                </div>
                            )}
                        </section>
                        )}
                        </fieldset>
                        <div className="flex flex-col gap-3 pt-2">
                            <p className="m-0 text-xs theme-text-muted" role="status">
                                <strong className="theme-text-main tabular-nums">{revisiones.length} piezas</strong>
                                {soloRevisiones ? ` · ${bultosAproximados || '—'} ${empaqueConsulta.unidad}` : ` · ${envios.length} envíos · ${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 4 }).format(totalCobrado)} kg cobrados`}
                                <span className="block mt-0.5">{borradorMsg || 'Revisa los datos antes de registrar la respuesta.'}</span>
                            </p>
                            <div className="flex flex-wrap gap-2 sm:justify-end">
                                {accionesRespuesta}
                            </div>
                        </div>
                        </div>
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
            <ModalConfirmarAccion
                abierto={Boolean(confirmacion)}
                titulo={confirmacion === 'registrar'
                    ? (soloRevisiones ? 'Confirmar consulta' : 'Confirmar pesaje')
                    : confirmacion === 'cerrar'
                        ? (soloRevisiones ? 'Cerrar consulta' : 'Cerrar pesaje')
                        : confirmacion?.tipo === 'quitar_envio'
                            ? 'Quitar envío'
                            : confirmacion?.tipo === 'quitar_pieza'
                                ? 'Eliminar pieza'
                                : ''}
                mensaje={confirmacion === 'registrar'
                    ? (resumenAntesDespues
                        ? `Antes ${resumenAntesDespues.antes.productos.length} prod. → después ${resumenAntesDespues.despues.productos.length} prod.`
                            + (resumenAntesDespues.piezas.agregadas.length ? ` (+${resumenAntesDespues.piezas.agregadas.length})` : '')
                            + (resumenAntesDespues.piezas.retiradas.length ? ` (−${resumenAntesDespues.piezas.retiradas.length})` : '')
                            + (resumenAntesDespues.piezas.sinCambioPiezas ? ' Sin cambios de piezas.' : '')
                            + (soloRevisiones
                                ? '.'
                                : ` · ${envios.length} envío(s), ${Math.round(totalCobrado * 10000) / 10000} kg.`)
                        : (soloRevisiones
                            ? `Se registrarán ${revisiones.length} producto(s) en la consulta de mercancía.`
                            : `Se registrarán ${envios.length} envío(s), ${revisiones.length} producto(s) y ${Math.round(totalCobrado * 10000) / 10000} kg cobrados.`))
                    : confirmacion === 'cerrar'
                        ? '¿Cerrar? El borrador se conserva.'
                        : confirmacion?.tipo === 'quitar_envio'
                            ? 'Se quitará este envío y sus fotos. No se puede deshacer.'
                            : confirmacion?.tipo === 'quitar_pieza'
                                ? 'Se eliminará esta pieza de la revisión (p. ej. agregada por error). Si no hay stock, use el estado Sin existencias.'
                                : ''}
                etiquetaConfirmar={confirmacion === 'registrar'
                    ? (soloRevisiones ? 'Registrar consulta' : 'Registrar pesaje')
                    : confirmacion === 'cerrar'
                        ? 'Cerrar'
                        : 'Quitar'}
                variante={confirmacion === 'registrar' ? 'primary' : 'danger'}
                onClose={() => setConfirmacion(null)}
                onConfirm={() => {
                    const acc = confirmacion;
                    setConfirmacion(null);
                    if (acc === 'registrar') enviarPesaje();
                    else if (acc === 'cerrar') {
                        cancelarSesionEvidencia();
                        onClose();
                    }
                    else if (acc?.tipo === 'quitar_envio') quitarEnvio(acc.idx);
                    else if (acc?.tipo === 'quitar_pieza') quitarRevision(acc.idx);
                }}
            />
            <ModalSesionEvidenciaCedis
                abierto={modalQr}
                sesion={sesionEvidencia}
                conectado={celularConectado}
                onCerrar={() => setModalQr(false)}
                onCancelar={cancelarSesionEvidencia}
            />
        </>,
        document.body
    );
}
