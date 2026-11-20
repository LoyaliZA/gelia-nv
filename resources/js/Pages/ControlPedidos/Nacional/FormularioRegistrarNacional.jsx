import React, { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
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
    return catalogo.filter(esPaqueteriaNacional);
}

function paqueteriasPosteriores(catalogo = []) {
    return catalogo.filter((item) => item?.activo !== false && (
        item?.nombre === 'PAQ. PENDIENTE'
        || esPaqueteriaNacional(item)
    ));
}

function mensajeDe(error, reserva) {
    const datos = error?.response?.data;
    const lista = datos?.errors ? Object.values(datos.errors).flat() : [];

    return lista[0] || datos?.message || reserva;
}

export default function FormularioRegistrarNacional({ pedido, catalogos = {}, guardar, onPedido, children }) {
    const [actual, setActual] = useState(pedido);
    const [paqueteriaId, setPaqueteriaId] = useState(String(pedido?.catalogo_paqueteria_id || ''));
    const [tocada, setTocada] = useState(false);
    const [error, setError] = useState('');
    const [enviando, setEnviando] = useState(false);
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
    const claveComplemento = useRef(uuidSolicitud());
    const claveSucursal = useRef(uuidSolicitud());
    useEffect(() => { setActual(pedido); }, [pedido]);
    const opciones = useMemo(() => paqueteriasNacionales(catalogos.paqueterias), [catalogos.paqueterias]);
    const posteriores = useMemo(() => paqueteriasPosteriores(catalogos.paqueterias), [catalogos.paqueterias]);
    const sucursales = catalogos.sucursales || [];
    const enviada = Boolean(actual?.pesaje_solicitado_at);
    const cerrada = Boolean(actual?.consulta_cerrada_at);
    const conComplemento = Boolean(actual?.numero_remision || actual?.pago_validado_at);
    const elegida = opciones.find((item) => String(item.id) === String(actual?.catalogo_paqueteria_id || paqueteriaId));
    const cambio = actual?.cambio_a_tienda || actual?.solicitud_preparacion?.cambio_a_tienda;
    const fase = actual?.estatus?.fase_ciclo;
    const yaSalio = ['ENVIADO', 'ENTREGADO', 'EN_RUTA'].includes(fase);
    const nombreSucursal = sucursalRecoleccionNacional(actual, sucursales);

    const adoptar = (lectura) => {
        const siguiente = { ...actual, ...lectura };
        setActual(siguiente);
        onPedido?.(siguiente);

        return siguiente;
    };

    const motivoEnvio = () => {
        if (!String(actual?.folio_remision || '').trim()) return 'Indique el folio del pedido.';
        const tieneArchivo = (actual?.documentos || []).some((documento) => documento?.url || documento?.id);
        if (!tieneArchivo) return 'Adjunte el PDF del pedido.';
        if (!opciones.length) return 'No hay paquetería comercial con logística disponible.';
        if (!paqueteriaId) return 'Elija la paquetería comercial.';

        return '';
    };

    const enviar = async () => {
        if (enviando) return;
        const motivo = motivoEnvio();
        setTocada(true);
        if (motivo) {
            setError(motivo);

            return;
        }
        setEnviando(true);
        setError('');
        try {
            const guardado = await guardar?.({ catalogo_paqueteria_id: Number(paqueteriaId) });
            const lectura = guardado?.id ? guardado : actual;
            if (!lectura?.id) {
                setError('Guarde el pedido antes de enviar la consulta.');

                return;
            }
            const { data } = await axios.post(`/control-pedidos/${lectura.id}/solicitar-pesaje`, {
                revision_esperada: lectura.revision_registro,
                base_captura: lectura.base_captura,
            }, { headers: { Accept: 'application/json' } });
            adoptar({ ...lectura, ...data, documentos: lectura.documentos || actual?.documentos || [] });
        } catch (fallo) {
            setError(mensajeDe(fallo, 'No se envió la consulta. Intente de nuevo.'));
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
                solicitud_id: crypto.randomUUID(),
                revision_esperada: actual.revision_registro,
                base_captura: actual.base_captura,
            }, { headers: { Accept: 'application/json' } });
            adoptar({ ...data, catalogo_paqueteria_id: Number(paqueteriaPosterior) });
        } catch (fallo) {
            setError(mensajeDe(fallo, 'No se actualizó la paquetería. Intente de nuevo.'));
        } finally {
            setEnviando(false);
        }
    };

    const agregarCatalogo = () => {
        if (!producto?.sku || !producto?.descripcion || Number(cantidadCatalogo) < 1) {
            setErrorComplemento('Seleccione el producto y la cantidad.');

            return;
        }
        setPiezas((actuales) => [...actuales, {
            sku: producto.sku,
            descripcion: producto.descripcion,
            cantidad: Math.max(1, Number(cantidadCatalogo) || 1),
            evidencia: null,
        }]);
        setProducto(null);
        setCantidadCatalogo('1');
        setErrorComplemento('');
    };

    const agregarCaptura = () => {
        const descripcion = descripcionCaptura.trim();
        const archivo = archivoCaptura[0] || null;
        if (!descripcion || !archivo) {
            setErrorComplemento('Indique la descripción y adjunte la captura o el documento.');

            return;
        }
        setPiezas((actuales) => [...actuales, {
            sku: '',
            descripcion,
            cantidad: Math.max(1, Number(cantidadCaptura) || 1),
            evidencia: archivo,
        }]);
        setDescripcionCaptura('');
        setCantidadCaptura('1');
        setArchivoCaptura([]);
        setPreviewCaptura([]);
        setErrorComplemento('');
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

    const contenido = (
        <section className="registrar-tienda-panel space-y-3" aria-labelledby="envio-nacional-titulo">
            <h3 id="envio-nacional-titulo" className="m-0 text-base">Envío nacional</h3>
            <p className="text-sm theme-text-main m-0">Salida: CEDIS</p>
            {enviada ? (
                <p className="text-sm theme-text-main m-0">Paquetería: {elegida?.nombre || 'Comercial con peso'}</p>
            ) : (
                <div className="min-w-0">
                    <label className={THEME_LABEL} htmlFor="paqueteria-nacional">Paquetería</label>
                    <select
                        id="paqueteria-nacional"
                        className={`${THEME_SELECT} w-full min-h-[44px] text-base mt-1`}
                        value={paqueteriaId}
                        aria-invalid={tocada && !paqueteriaId ? 'true' : undefined}
                        aria-describedby={error ? 'paqueteria-nacional-error' : undefined}
                        onBlur={() => {
                            setTocada(true);
                            if (!paqueteriaId) setError(opciones.length ? 'Elija la paquetería comercial.' : 'No hay paquetería comercial con logística disponible.');
                        }}
                        onFocus={() => setError('')}
                        onChange={(evento) => {
                            setPaqueteriaId(evento.target.value);
                            setError('');
                        }}
                    >
                        <option value="">Elegir</option>
                        {opciones.map((item) => (
                            <option key={item.id} value={String(item.id)}>{item.nombre}</option>
                        ))}
                    </select>
                    {error && <p id="paqueteria-nacional-error" className="text-sm theme-text-aviso m-0 mt-1" role="alert">{error}</p>}
                </div>
            )}
            {cerrada && (
                <div className="min-w-0">
                    <label className={THEME_LABEL} htmlFor="paqueteria-posterior">Paquetería</label>
                    <p className="text-sm theme-text-main m-0 mt-1">PAQ. PENDIENTE espera la elección. No asigna guía.</p>
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
                            setPaqueteriaPosterior(evento.target.value);
                            setError('');
                        }}
                    >
                        <option value="">Elegir</option>
                        {posteriores.map((item) => (
                            <option key={item.id} value={String(item.id)}>{item.nombre}</option>
                        ))}
                    </select>
                    <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px] mt-2`} onClick={actualizarPaqueteria}>
                        {enviando ? 'Actualizando…' : 'Actualizar paquetería'}
                    </button>
                    {error && <p className="text-sm theme-text-aviso m-0 mt-1" role="alert">{error}</p>}
                </div>
            )}
            {conComplemento && (
                <div className="min-w-0 space-y-3">
                    <p className="text-sm theme-text-main m-0">Piezas nuevas. Lo ya pagado no se copia ni se vuelve a cobrar.</p>
                    <div className="flex flex-wrap gap-2" role="group" aria-label="Cómo identificar las piezas">
                        <button type="button" className={GELIA_BTN_OUTLINE} aria-pressed={entradaPieza === 'catalogo'} onClick={() => setEntradaPieza('catalogo')}>Por SKU</button>
                        <button type="button" className={GELIA_BTN_OUTLINE} aria-pressed={entradaPieza === 'captura'} onClick={() => setEntradaPieza('captura')}>Por captura o documento</button>
                    </div>
                    {entradaPieza === 'catalogo' ? (
                        <div className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_6rem_auto] sm:items-end">
                            <div className="min-w-0">
                                <label className={THEME_LABEL} htmlFor="complemento-buscar-producto">Producto</label>
                                <SelectorProducto
                                    inputId="complemento-buscar-producto"
                                    almacenId={actual?.almacen_id || actual?.almacen?.id || null}
                                    incluirSinInventario
                                    value={producto?.id || ''}
                                    onChange={(_, elegido) => setProducto(elegido)}
                                    disabled={enviando}
                                />
                            </div>
                            <div>
                                <label className={THEME_LABEL} htmlFor="complemento-cantidad-catalogo">Cantidad</label>
                                <input id="complemento-cantidad-catalogo" className={`${THEME_INPUT} w-full min-h-[44px] text-base mt-1`} inputMode="numeric" min="1" value={cantidadCatalogo} onChange={(evento) => setCantidadCatalogo(evento.target.value)} />
                            </div>
                            <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} disabled={enviando || !producto} onClick={agregarCatalogo}>Agregar</button>
                        </div>
                    ) : (
                        <div className="grid gap-2">
                            <label className={THEME_LABEL} htmlFor="complemento-descripcion-captura">Descripción</label>
                            <input id="complemento-descripcion-captura" className={`${THEME_INPUT} w-full min-h-[44px] text-base`} value={descripcionCaptura} onChange={(evento) => setDescripcionCaptura(evento.target.value)} />
                            <label className={THEME_LABEL} htmlFor="complemento-cantidad-captura">Cantidad</label>
                            <input id="complemento-cantidad-captura" className={`${THEME_INPUT} w-full min-h-[44px] text-base`} inputMode="numeric" min="1" value={cantidadCaptura} onChange={(evento) => setCantidadCaptura(evento.target.value)} />
                            <GaleriaEvidenciasPedido
                                compacto
                                accionesCamara
                                maxArchivos={1}
                                id="complemento-captura-pieza"
                                label="Captura o documento de la pieza"
                                archivos={archivoCaptura}
                                previews={previewCaptura}
                                onChange={(archivos, vistas) => { setArchivoCaptura(archivos); setPreviewCaptura(vistas); }}
                                disabled={enviando}
                            />
                            <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px] justify-self-start`} disabled={enviando} onClick={agregarCaptura}>Agregar pieza</button>
                        </div>
                    )}
                    {piezas.length > 0 && (
                        <ul className="m-0 pl-0 list-none grid gap-2">
                            {piezas.map((pieza, indice) => (
                                <li key={`${pieza.sku}-${pieza.descripcion}-${indice}`} className="flex items-center justify-between gap-3 rounded-lg border theme-border px-3 py-2 text-sm theme-text-main">
                                    <span className="min-w-0">{pieza.descripcion}<span className="block text-xs theme-text-muted">{pieza.sku || 'Captura'} · {pieza.cantidad}</span></span>
                                    <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} onClick={() => setPiezas((actuales) => actuales.filter((_, actual) => actual !== indice))}>Quitar</button>
                                </li>
                            ))}
                        </ul>
                    )}
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
                    <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px] mt-2`} onClick={registrarComplemento}>
                        {enviando ? 'Registrando…' : 'Registrar complemento'}
                    </button>
                    {errorComplemento && <p className="text-sm theme-text-aviso m-0 mt-1" role="alert">{errorComplemento}</p>}
                </div>
            )}
            {enviada && !yaSalio && (
                <div className="min-w-0 space-y-2">
                    {cambio ? (
                        <>
                            <p className="text-sm theme-text-main m-0">Recolección en sucursal: {nombreSucursal || 'Sucursal elegida'}.</p>
                            <p className="text-sm theme-text-main m-0">Costo de envío conservado: {actual?.costo_envio ?? '0'}.</p>
                            <p className="text-sm theme-text-main m-0">La guía invalidada no está cancelada ante la paquetería.</p>
                        </>
                    ) : (
                        <>
                            <label className={THEME_LABEL} htmlFor="sucursal-recoleccion-nacional">Sucursal de recolección</label>
                            <p className="text-sm theme-text-main m-0 mt-1">Costo de envío conservado: {actual?.costo_envio ?? '0'}. La guía invalidada no está cancelada ante la paquetería.</p>
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
                            <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px] mt-2`} onClick={solicitarSucursal}>
                                {enviando ? 'Solicitando…' : 'Solicitar recolección en sucursal'}
                            </button>
                        </>
                    )}
                    {error && <p className="text-sm theme-text-aviso m-0" role="alert">{error}</p>}
                </div>
            )}
            {enviada && (
                <SeguimientoSolicitudNacional
                    pedido={actual}
                    onPedidoActualizado={(lectura) => adoptar(lectura)}
                />
            )}
        </section>
    );

    const pie = enviada ? null : (
        <div className="registrar-tienda-pie-acciones">
            <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} onClick={enviar}>
                {enviando ? 'Enviando…' : 'Enviar consulta a CEDIS'}
            </button>
        </div>
    );

    return children({ contenido, pie });
}
