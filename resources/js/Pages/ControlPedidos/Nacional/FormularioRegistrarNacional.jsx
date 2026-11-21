import React, { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import { Truck } from 'lucide-react';
import ContinuacionEnvioNacional from './ContinuacionEnvioNacional';
import SeguimientoSolicitudNacional from './SeguimientoSolicitudNacional';
import SelectorProducto from '../../../Components/Almacenes/SelectorProducto';
import GaleriaEvidenciasPedido from '../Partials/GaleriaEvidenciasPedido';
import { uuidSolicitud } from '../../../utils/uuidSolicitud';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY, THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../utils/geliaTheme';
import { sucursalRecoleccionNacional } from './superficieNacional';

function esPaqueteriaNacional(item) {
    return item?.activo !== false
        && item?.categoria === 'comercial'
        && item?.sin_logistica !== true
        && item?.nombre !== 'PAQ. PENDIENTE';
}

function paqueteriasNacionales(catalogo = []) {
    return catalogo.filter((item) => item?.activo !== false && (
        item?.nombre === 'PAQ. PENDIENTE'
        || esPaqueteriaNacional(item)
    ));
}

function almacenesSalidaNacional(catalogo = []) {
    return catalogo.filter((almacen) => almacen.activo !== false
        && almacen.visible_en_pedidos !== false
        && almacen.permite_salida_pedidos === true
        && almacen.tipo_almacen?.nombre === 'CEDIS');
}

function salidaInicial(pedido, almacenes) {
    if (pedido?.almacen_id) return String(pedido.almacen_id);
    return !pedido?.pesaje_solicitado_at && almacenes.length === 1 ? String(almacenes[0].id) : '';
}

function mensajeDe(error, reserva) {
    const datos = error?.response?.status < 500 ? error.response.data : null;
    const lista = datos?.errors ? Object.values(datos.errors).flat() : [];

    return lista[0] || datos?.message || reserva;
}

export default function FormularioRegistrarNacional({ pedido, catalogos = {}, guardar, onPedido, onVer, onDirtyChange, onBusyChange, bloqueado, importePendiente, campoImporteMercancia, children }) {
    const [actual, setActual] = useState(pedido);
    const [paqueteriaId, setPaqueteriaId] = useState(String(pedido?.catalogo_paqueteria_id || ''));
    const almacenesSalida = useMemo(() => almacenesSalidaNacional(catalogos.almacenes), [catalogos.almacenes]);
    const [almacenSalidaId, setAlmacenSalidaId] = useState(() => salidaInicial(pedido, almacenesSalida));
    const [salidaEditada, setSalidaEditada] = useState(false);
    const [tocada, setTocada] = useState(false);
    const [error, setError] = useState('');
    const [enviando, setEnviando] = useState(false);
    useEffect(() => { onBusyChange?.(enviando); }, [enviando, onBusyChange]);
    useEffect(() => () => onBusyChange?.(false), [onBusyChange]);
    const [paqueteriaPosterior, setPaqueteriaPosterior] = useState('');
    const [importeComplemento, setImporteComplemento] = useState('');
    const [soporteComplemento, setSoporteComplemento] = useState(null);
    const [errorComplemento, setErrorComplemento] = useState('');
    const [entradaPieza, setEntradaPieza] = useState('catalogo');
    const [producto, setProducto] = useState(null);
    const [cantidadCatalogo, setCantidadCatalogo] = useState('1');
    const [descripcionCaptura, setDescripcionCaptura] = useState('');
    const [cantidadCaptura, setCantidadCaptura] = useState('1');
    const [archivoCaptura, setArchivoCaptura] = useState([]);
    const [previewCaptura, setPreviewCaptura] = useState([]);
    const [piezas, setPiezas] = useState([]);
    const [sucursalId, setSucursalId] = useState('');
    const [mercanciaEditada, setMercanciaEditada] = useState(false);
    const [paqueteriaEditada, setPaqueteriaEditada] = useState(false);
    const clavePaqueteria = useRef(uuidSolicitud());
    const claveComplemento = useRef(uuidSolicitud());
    const claveSucursal = useRef(uuidSolicitud());
    useEffect(() => { setActual(pedido); }, [pedido]);
    const opciones = useMemo(() => paqueteriasNacionales(catalogos.paqueterias), [catalogos.paqueterias]);
    const sucursales = catalogos.sucursales || [];
    const enviada = Boolean(actual?.pesaje_solicitado_at);
    const cerrada = Boolean(actual?.consulta_cerrada || actual?.consulta_cerrada_at);
    const conPago = Boolean(actual?.numero_remision || actual?.pago_validado_at || actual?.mercancia_pagada || actual?.solicitud_preparacion?.pago_solo_mercancia_nacional);
    const conComplemento = cerrada && conPago;
    const solicitud = actual?.solicitud_preparacion || {};
    const mercanciaGuardada = solicitud.piezas_nacionales_vigentes || solicitud.piezas_nacionales || [];
    const mercancia = !enviada && mercanciaEditada ? piezas : mercanciaGuardada;
    const capturaIncompleta = Boolean(producto || descripcionCaptura.trim() || archivoCaptura.length);
    useEffect(() => {
        onDirtyChange?.(!enviada && (mercanciaEditada || paqueteriaEditada || salidaEditada || capturaIncompleta));
    }, [enviada, mercanciaEditada, paqueteriaEditada, salidaEditada, capturaIncompleta, onDirtyChange]);
    useEffect(() => () => onDirtyChange?.(false), [onDirtyChange]);
    useEffect(() => {
        if (!paqueteriaEditada) setPaqueteriaId(String(pedido?.catalogo_paqueteria_id || ''));
    }, [paqueteriaEditada, pedido?.catalogo_paqueteria_id]);
    useEffect(() => {
        if (!salidaEditada || actual?.pesaje_solicitado_at) setAlmacenSalidaId(salidaInicial(actual, almacenesSalida));
    }, [salidaEditada, actual?.almacen_id, actual?.pesaje_solicitado_at, almacenesSalida]);
    const elegida = opciones.find((item) => String(item.id) === String(enviada ? actual?.catalogo_paqueteria_id : paqueteriaId));
    const cambio = actual?.cambio_a_tienda || actual?.solicitud_preparacion?.cambio_a_tienda;
    const fase = actual?.estatus?.fase_ciclo;
    const yaSalio = ['ENVIADO', 'ENTREGADO', 'EN_RUTA', 'CANCELADO'].includes(fase)
        || (actual?.cajas || []).some((caja) => caja.estatus_recoleccion === 'recolectada' || caja.recolectada_at);
    const puedeCambiarPaqueteria = cerrada && !yaSalio && !cambio;
    const puedePedirSucursal = cerrada && conPago && !yaSalio && !cambio && sucursales.length > 0;
    const guiaInvalidada = actual?.invalidacion_guia || solicitud.invalidacion_guia;
    const nombreSucursal = sucursalRecoleccionNacional(actual, sucursales);

    const adoptar = (lectura) => {
        const siguiente = { ...actual, ...lectura };
        setActual(siguiente);
        onPedido?.(siguiente);

        return siguiente;
    };

    const motivoEnvio = () => {
        if (!String(actual?.folio_remision || '').trim()) return 'Indique el folio del pedido.';
        const tieneArchivo = actual?.soporte_pedido || (actual?.documentos || []).some(
            (documento) => ['pdf_pedido', 'cotizacion'].includes(documento.tipo) && documento.activo !== false,
        );
        if (!tieneArchivo && !mercancia.some((pieza) => pieza.evidencia || pieza.documento_ids?.length)) {
            return 'Adjunte el documento del pedido o identifique las piezas con capturas.';
        }
        if (!opciones.length) return 'No hay paqueterías nacionales disponibles.';
        if (!paqueteriaId) return 'Elija la paquetería o seleccione PAQ. PENDIENTE.';

        return '';
    };

    const guardarMercancia = async (solicitar = false) => {
        if (enviando || bloqueado) return;
        const motivo = capturaIncompleta
            ? 'Agregue la pieza en captura o limpie sus datos antes de continuar.'
            : (solicitar ? motivoEnvio() : '');
        if (motivo) {
            setTocada(solicitar);
            setError(motivo);
            return;
        }

        setEnviando(true);
        setError('');
        try {
            const salida = !enviada && almacenSalidaId ? { almacen_id: Number(almacenSalidaId) } : {};
            const lectura = await guardar?.({
                ...(paqueteriaId ? { catalogo_paqueteria_id: Number(paqueteriaId) } : {}),
                ...salida,
                ...(mercanciaEditada ? { guardar_mercancia_nacional: true, piezas_nacionales: piezas } : {}),
            });
            if (!lectura?.id) {
                setError('Revise el borrador y vuelva a guardar.');
                return;
            }

            // El autoguardado confirma la captura pero no devuelve almacen_id.
            // Conservar el id aceptado hasta la siguiente lectura completa del pedido.
            adoptar({ ...lectura, ...salida });
            setPiezas([]);
            setMercanciaEditada(false);
            setPaqueteriaEditada(false);
            setSalidaEditada(false);
            if (!solicitar) return;

            const { data } = await axios.post(`/control-pedidos/${lectura.id}/solicitar-pesaje`, {
                revision_esperada: lectura.revision_registro,
                base_captura: lectura.base_captura,
            }, { headers: { Accept: 'application/json' } });
            adoptar({ ...lectura, ...salida, ...data });
        } catch (fallo) {
            setError(mensajeDe(fallo, solicitar ? 'No se envió la consulta. Intente de nuevo.' : 'No se guardó el borrador. Intente de nuevo.'));
        } finally {
            setEnviando(false);
        }
    };

    const actualizarPaqueteria = async () => {
        if (enviando) return;
        if (!paqueteriaPosterior) {
            setTocada(true);
            setError('Elija la paquetería.');

            return;
        }
        setEnviando(true);
        setError('');
        try {
            const { data } = await axios.post(`/control-pedidos/${actual.id}/paqueteria-nacional`, {
                catalogo_paqueteria_id: Number(paqueteriaPosterior),
                solicitud_id: clavePaqueteria.current,
                revision_esperada: actual.revision_registro,
                base_captura: actual.base_captura,
            }, { headers: { Accept: 'application/json' } });
            clavePaqueteria.current = uuidSolicitud();
            setPaqueteriaPosterior('');
            adoptar({ ...data, catalogo_paqueteria_id: Number(paqueteriaPosterior) });
        } catch (fallo) {
            setError(mensajeDe(fallo, 'No se actualizó la paquetería. Intente de nuevo.'));
        } finally {
            setEnviando(false);
        }
    };

    const cambiarPiezas = (actualizar) => {
        const nuevas = actualizar(enviada ? piezas : mercancia);
        if (nuevas.length > (enviada ? 20 : 100)) {
            setErrorComplemento(enviada ? 'Agregue hasta 20 líneas al complemento.' : 'Agregue hasta 100 líneas al pedido.');
            return false;
        }
        setPiezas(nuevas);
        if (!enviada) setMercanciaEditada(true);
        return true;
    };

    const limpiarPieza = () => {
        setProducto(null);
        setCantidadCatalogo('1');
        setCantidadCaptura('1');
        setDescripcionCaptura('');
        setArchivoCaptura([]);
        setPreviewCaptura([]);
        setErrorComplemento('');
    };

    const agregarCatalogo = () => {
        if (!producto?.sku || !producto?.descripcion || !Number.isInteger(Number(cantidadCatalogo)) || Number(cantidadCatalogo) < 1 || Number(cantidadCatalogo) > 99999) {
            setErrorComplemento('Seleccione el producto y la cantidad.');

            return;
        }
        const agregada = cambiarPiezas((actuales) => [...actuales, {
            id: uuidSolicitud(),
            sku: producto.sku,
            descripcion: producto.descripcion,
            cantidad: Math.max(1, Number(cantidadCatalogo) || 1),
            evidencia: null,
        }]);
        if (agregada) limpiarPieza();
    };

    const agregarCaptura = () => {
        const descripcion = descripcionCaptura.trim();
        const archivo = archivoCaptura[0] || null;
        if (!descripcion || !archivo || !Number.isInteger(Number(cantidadCaptura)) || Number(cantidadCaptura) < 1 || Number(cantidadCaptura) > 99999) {
            setErrorComplemento('Indique la descripción y adjunte la captura o el documento.');

            return;
        }
        const agregada = cambiarPiezas((actuales) => [...actuales, {
            id: uuidSolicitud(),
            sku: '',
            descripcion,
            cantidad: Math.max(1, Number(cantidadCaptura) || 1),
            evidencia: archivo,
        }]);
        if (agregada) limpiarPieza();
    };

    const registrarComplemento = async () => {
        if (enviando) return;
        const importe = Number(String(importeComplemento).replace(',', '.'));
        if (!Number.isFinite(importe) || importe <= 0) {
            setErrorComplemento('Indique el importe del complemento.');

            return;
        }
        if (!soporteComplemento) {
            setErrorComplemento('Adjunte el soporte del complemento.');

            return;
        }
        if (!piezas.length) {
            setErrorComplemento('Indique las piezas nuevas del complemento.');

            return;
        }
        setEnviando(true);
        setErrorComplemento('');
        try {
            const cuerpo = new FormData();
            cuerpo.append('tipo', 'anexo');
            cuerpo.append('solicitud_id', claveComplemento.current);
            cuerpo.append('total_mercancia_nueva', String(importe));
            cuerpo.append('revision_esperada', String(actual?.revision_registro ?? ''));
            cuerpo.append('base_captura', actual?.base_captura || '');
            cuerpo.append('evidencia', soporteComplemento);
            piezas.forEach((pieza, indice) => {
                cuerpo.append(`piezas[${indice}][sku]`, pieza.sku || '');
                cuerpo.append(`piezas[${indice}][descripcion]`, pieza.descripcion);
                cuerpo.append(`piezas[${indice}][cantidad]`, String(pieza.cantidad));
                if (pieza.evidencia) cuerpo.append(`piezas[${indice}][evidencia]`, pieza.evidencia);
            });
            const { data } = await axios.post(`/control-pedidos/${actual.id}/ajuste-nacional`, cuerpo, {
                headers: { Accept: 'application/json' },
            });
            claveComplemento.current = uuidSolicitud();
            setPiezas([]);
            setImporteComplemento('');
            setSoporteComplemento(null);
            adoptar(data);
        } catch (fallo) {
            setErrorComplemento(mensajeDe(fallo, 'No se registró el complemento. Intente de nuevo.'));
        } finally {
            setEnviando(false);
        }
    };

    const solicitarSucursal = async () => {
        if (enviando) return;
        if (!sucursalId) {
            setError('Elija la sucursal de recolección.');

            return;
        }
        setEnviando(true);
        setError('');
        try {
            const { data } = await axios.post(`/control-pedidos/${actual.id}/cambio-tienda-nacional`, {
                sucursal_destino_id: Number(sucursalId),
                solicitud_id: claveSucursal.current,
                revision_esperada: actual.revision_registro,
                base_captura: actual.base_captura,
            }, { headers: { Accept: 'application/json' } });
            claveSucursal.current = uuidSolicitud();
            adoptar(data);
        } catch (fallo) {
            setError(mensajeDe(fallo, 'No se solicitó la recolección en sucursal. Intente de nuevo.'));
        } finally {
            setEnviando(false);
        }
    };

    const listaPiezas = (filas, editable) => filas.length > 0 && (
        <ul className="m-0 list-none p-0 grid gap-2">
            {filas.map((pieza, indice) => {
                const documentos = (actual?.documentos || []).filter((doc) => pieza.documento_ids?.includes(doc.id));
                return (
                    <li key={pieza.id} className="flex flex-wrap items-center gap-2 rounded-lg border theme-border px-3 py-2 text-sm">
                        <span className="min-w-0 flex-1 break-words">
                            {pieza.descripcion}
                            <span className="block text-xs theme-text-muted">
                                {pieza.sku || 'Captura'} · {pieza.cantidad}{pieza.evidencia ? ` · ${pieza.evidencia.name}` : ''}
                            </span>
                        </span>
                        {documentos.length > 0 && onVer && (
                            <button type="button" className={GELIA_BTN_OUTLINE + ' min-h-[44px]'} onClick={() => onVer(documentos, 0)}>
                                Ver evidencia
                            </button>
                        )}
                        {editable && (
                            <button type="button" className={GELIA_BTN_OUTLINE + ' min-h-[44px]'}
                                aria-label={`Quitar ${pieza.descripcion}`} disabled={enviando || bloqueado}
                                onClick={() => cambiarPiezas((filas) => filas.filter((_, posicion) => posicion !== indice))}>
                                Quitar
                            </button>
                        )}
                    </li>
                );
            })}
        </ul>
    );

    const editorPiezas = (
        <>
            <div className="flex flex-wrap gap-2" role="group" aria-label="Cómo identificar las piezas">
                <button type="button" className={GELIA_BTN_OUTLINE} aria-pressed={entradaPieza === 'catalogo'} onClick={() => setEntradaPieza('catalogo')}>Por SKU</button>
                <button type="button" className={GELIA_BTN_OUTLINE} aria-pressed={entradaPieza === 'captura'} onClick={() => setEntradaPieza('captura')}>Por captura o documento</button>
            </div>
            {entradaPieza === 'catalogo' ? (
                <div className="registrar-nacional-producto grid gap-2 grid-cols-[minmax(0,1fr)_auto] items-end">
                    <div className="min-w-0 col-span-full">
                        <label className={THEME_LABEL} htmlFor="nacional-buscar-producto">Producto</label>
                        <SelectorProducto
                            inputId="nacional-buscar-producto"
                            almacenId={actual?.almacen_id || actual?.almacen?.id || null}
                            incluirSinInventario
                            value={producto?.id || ''}
                            onChange={(_, elegido) => setProducto(elegido)}
                            disabled={enviando || bloqueado}
                        />
                    </div>
                    <div>
                        <label className={THEME_LABEL} htmlFor="nacional-cantidad-catalogo">Cantidad</label>
                        <input id="nacional-cantidad-catalogo" className={`${THEME_INPUT} w-full min-h-[44px] text-base mt-1`} inputMode="numeric" min="1" value={cantidadCatalogo} onChange={(evento) => setCantidadCatalogo(evento.target.value)} />
                    </div>
                    <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} disabled={enviando || bloqueado || !producto} onClick={agregarCatalogo}>Agregar</button>
                </div>
            ) : (
                <div className="grid gap-2">
                    <label className={THEME_LABEL} htmlFor="nacional-descripcion-captura">Descripción</label>
                    <input id="nacional-descripcion-captura" className={`${THEME_INPUT} w-full min-h-[44px] text-base`} maxLength={180} value={descripcionCaptura} onChange={(evento) => setDescripcionCaptura(evento.target.value)} />
                    <label className={THEME_LABEL} htmlFor="nacional-cantidad-captura">Cantidad</label>
                    <input id="nacional-cantidad-captura" className={`${THEME_INPUT} w-full min-h-[44px] text-base`} inputMode="numeric" min="1" value={cantidadCaptura} onChange={(evento) => setCantidadCaptura(evento.target.value)} />
                    <GaleriaEvidenciasPedido
                        compacto
                        accionesCamara
                        maxArchivos={1}
                        id="nacional-captura-pieza"
                        label="Captura o documento de la pieza"
                        archivos={archivoCaptura}
                        previews={previewCaptura}
                        onChange={(archivos, vistas) => { setArchivoCaptura(archivos); setPreviewCaptura(vistas); }}
                        disabled={enviando || bloqueado}
                    />
                    <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px] justify-self-start`} disabled={enviando || bloqueado} onClick={agregarCaptura}>Agregar pieza</button>
                </div>
            )}
            {capturaIncompleta && <button type="button" className={GELIA_BTN_OUTLINE + ' min-h-[44px]'} onClick={limpiarPieza}>Limpiar captura</button>}
        </>
    );

    const salidaGuardadaFueraDeCatalogo = Boolean(actual?.almacen_id
        && String(actual.almacen_id) === almacenSalidaId
        && !almacenesSalida.some(almacen => String(almacen.id) === almacenSalidaId));
    const nombreSalidaGuardada = (catalogos.almacenes || []).find(almacen => String(almacen.id) === almacenSalidaId)?.nombre
        || actual?.almacen?.nombre || 'Almacén guardado';
    const selectorSalida = (
        <div className="min-w-0">
            <label className={THEME_LABEL} htmlFor="almacen-salida-nacional">Almacén de salida</label>
            <select id="almacen-salida-nacional" className={`${THEME_SELECT} w-full min-h-[44px] text-base mt-1`}
                value={almacenSalidaId} disabled={enviada || enviando || bloqueado}
                onChange={(evento) => { setAlmacenSalidaId(evento.target.value); setSalidaEditada(true); }}>
                <option value="" disabled>{almacenesSalida.length ? 'Elegir almacén' : 'Sin CEDIS habilitados para salida'}</option>
                {salidaGuardadaFueraDeCatalogo && <option value={almacenSalidaId} disabled>{nombreSalidaGuardada} · no disponible</option>}
                {almacenesSalida.map(almacen => <option key={almacen.id} value={String(almacen.id)}>{almacen.nombre}</option>)}
            </select>
        </div>
    );

    const panelEnvio = (
        <section className="registrar-tienda-panel space-y-3" aria-labelledby="envio-nacional-titulo">
            {enviada ? (
                <div className="registrar-nacional-envio-guardado">
                    <div className="min-w-0 space-y-3">
                        <div className="registrar-tienda-seccion-titulo">
                            <Truck size={18} aria-hidden="true" />
                            <h3 id="envio-nacional-titulo">Envío nacional</h3>
                        </div>
                        {selectorSalida}
                        <p className="text-sm theme-text-main m-0">Paquetería: {elegida?.nombre || actual?.paqueteria?.nombre || 'Consultar selección'}</p>
                    </div>
                    <div className="registrar-nacional-envio-importe min-w-0">
                        {campoImporteMercancia}
                    </div>
                </div>
            ) : (
                <>
                    <div className="registrar-tienda-seccion-titulo">
                        <Truck size={18} aria-hidden="true" />
                        <h3 id="envio-nacional-titulo">Envío nacional</h3>
                    </div>
                    {selectorSalida}
                    <div className="registrar-tienda-datos">
                        <div className="min-w-0">
                            <label className={THEME_LABEL} htmlFor="paqueteria-nacional">Paquetería</label>
                            <select
                                id="paqueteria-nacional"
                                className={`${THEME_SELECT} w-full min-h-[44px] text-base mt-1`}
                                value={paqueteriaId}
                                disabled={enviando || bloqueado}
                                aria-invalid={tocada && !paqueteriaId ? 'true' : undefined}
                                aria-describedby={error ? 'paqueteria-nacional-error' : undefined}
                                onBlur={() => {
                                    setTocada(true);
                                    if (!paqueteriaId) setError(opciones.length ? 'Elija la paquetería o seleccione PAQ. PENDIENTE.' : 'No hay paqueterías nacionales disponibles.');
                                }}
                                onFocus={() => setError('')}
                                onChange={(evento) => {
                                    setPaqueteriaId(evento.target.value);
                                    setPaqueteriaEditada(true);
                                    setError('');
                                }}
                            >
                                <option value="">Elegir</option>
                                {opciones.map((item) => (
                                    <option key={item.id} value={String(item.id)}>{item.nombre}</option>
                                ))}
                            </select>
                        </div>
                        {campoImporteMercancia}
                    </div>
                </>
            )}
            {elegida?.nombre === 'PAQ. PENDIENTE' && (
                <p className="text-sm theme-text-muted m-0">El flete queda pendiente. Puede continuar con el pago de mercancía después de cerrar la consulta.</p>
            )}
        </section>
    );

    const contenido = (
        <div className="grid min-w-0 gap-4">
            {enviada && (
                <SeguimientoSolicitudNacional pedido={actual} onPedidoActualizado={adoptar} onVer={onVer}
                    referenciaMercancia={listaPiezas(mercancia, false)} />
            )}
            {puedeCambiarPaqueteria && (
                <section className="registrar-tienda-panel space-y-2" aria-label="Cambiar paquetería">
                    <label className={THEME_LABEL} htmlFor="paqueteria-posterior">Cambiar paquetería</label>
                    <select
                        id="paqueteria-posterior"
                        className={`${THEME_SELECT} w-full min-h-[44px] text-base mt-1`}
                        value={paqueteriaPosterior}
                        aria-invalid={tocada && !paqueteriaPosterior ? 'true' : undefined}
                        onBlur={() => {
                            setTocada(true);
                            if (!paqueteriaPosterior) setError('Elija la paquetería.');
                        }}
                        onFocus={() => setError('')}
                        onChange={(evento) => {
                            clavePaqueteria.current = uuidSolicitud();
                            setPaqueteriaPosterior(evento.target.value);
                            setError('');
                        }}
                    >
                        <option value="">Elegir</option>
                        {opciones.filter((item) => String(item.id) !== String(actual?.catalogo_paqueteria_id)).map((item) => (
                            <option key={item.id} value={String(item.id)}>{item.nombre}</option>
                        ))}
                    </select>
                    <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px] mt-2`} disabled={enviando} onClick={actualizarPaqueteria}>
                        {enviando ? 'Actualizando…' : 'Actualizar paquetería'}
                    </button>
                </section>
            )}
            {cerrada && !yaSalio && !cambio && actual?.pesaje_respondido_at && elegida && (
                <ContinuacionEnvioNacional
                    key={`${actual.id}-${elegida.id}`}
                    pedido={actual} catalogos={catalogos}
                    pendiente={elegida.nombre === 'PAQ. PENDIENTE'} importePendiente={importePendiente} onPedido={adoptar}
                />
            )}
            {conComplemento && !cambio && !yaSalio && (
                <details className="registrar-tienda-panel space-y-3">
                    <summary className="cursor-pointer font-semibold">Agregar mercancía al pedido pagado</summary>
                    {editorPiezas}
                    {listaPiezas(piezas, true)}
                    <label className={THEME_LABEL} htmlFor="importe-complemento">Importe del complemento</label>
                    <p className="text-sm theme-text-main m-0">Importe de la mercancía nueva. Lo ya pagado no se vuelve a cobrar.</p>
                    <input
                        id="importe-complemento"
                        className={`${THEME_INPUT} w-full min-h-[44px] text-base mt-1`}
                        inputMode="decimal"
                        value={importeComplemento}
                        aria-invalid={errorComplemento ? 'true' : undefined}
                        onBlur={() => {
                            const importe = Number(String(importeComplemento).replace(',', '.'));
                            if (!Number.isFinite(importe) || importe <= 0) setErrorComplemento('Indique el importe del complemento.');
                        }}
                        onFocus={() => setErrorComplemento('')}
                        onChange={(evento) => {
                            setImporteComplemento(evento.target.value);
                            setErrorComplemento('');
                        }}
                    />
                    <label className={`${THEME_LABEL} mt-2 block`} htmlFor="soporte-complemento">Soporte del complemento</label>
                    <input
                        id="soporte-complemento"
                        className={`${THEME_INPUT} w-full min-h-[44px] text-base mt-1`}
                        type="file"
                        accept="application/pdf,image/jpeg,image/png,image/webp"
                        onFocus={() => setErrorComplemento('')}
                        onChange={(evento) => {
                            setSoporteComplemento(evento.target.files?.[0] || null);
                            setErrorComplemento('');
                        }}
                    />
                    <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px] mt-2`} disabled={enviando} onClick={registrarComplemento}>
                        {enviando ? 'Registrando…' : 'Registrar complemento'}
                    </button>
                    {errorComplemento && <p className="text-sm theme-text-aviso m-0 mt-1" role="alert">{errorComplemento}</p>}
                </details>
            )}
            {(cambio || puedePedirSucursal) && (
                <section className="registrar-tienda-panel space-y-2" aria-label="Recolección en sucursal">
                    {cambio ? (
                        <>
                            <p className="text-sm theme-text-main m-0">Recolección en sucursal: {nombreSucursal || 'Sucursal elegida'}.</p>
                            {Number(cambio.saldo_envio) > 0 && <p className="text-sm m-0">Costo de envío conservado: {cambio.saldo_envio}.</p>}
                        </>
                    ) : (
                        <>
                            <label className={THEME_LABEL} htmlFor="sucursal-recoleccion-nacional">Sucursal de recolección</label>
                            <select
                                id="sucursal-recoleccion-nacional"
                                className={`${THEME_SELECT} w-full min-h-[44px] text-base mt-1`}
                                value={sucursalId}
                                onChange={(evento) => {
                                    setSucursalId(evento.target.value);
                                    setError('');
                                }}
                            >
                                <option value="">Elegir sucursal</option>
                                {sucursales.map((item) => (
                                    <option key={item.id} value={String(item.id)}>{item.nombre}</option>
                                ))}
                            </select>
                            <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px] mt-2`} disabled={enviando} onClick={solicitarSucursal}>
                                {enviando ? 'Solicitando…' : 'Solicitar recolección en sucursal'}
                            </button>
                        </>
                    )}
                </section>
            )}
            {guiaInvalidada && !guiaInvalidada.cancelacion_paqueteria_confirmada && (
                <p className="text-sm theme-text-aviso m-0" role="status">Falta confirmar la cancelación de la guía con la paquetería.</p>
            )}
            {error && <p id="paqueteria-nacional-error" className="text-sm theme-text-aviso m-0" role="alert">{error}</p>}
        </div>
    );

    const accion = enviada ? null : (
        <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px] max-w-full`}
            disabled={enviando || bloqueado} onClick={() => guardarMercancia(true)}>
            {enviando ? 'Guardando…' : 'Enviar consulta a CEDIS'}
        </button>
    );

    return children({
        contenido, panelEnvio, accion, pie: accion, guardarBorrador: () => guardarMercancia(), enviando,
    });
}
