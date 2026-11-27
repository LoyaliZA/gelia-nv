import { useCambiarEnvio } from './CambiarEnvioContext';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
    AlertTriangle, CheckCircle2, CircleDot, Clock, Layers, Package, Receipt, RefreshCw, ShieldCheck, Truck,
} from 'lucide-react';
import AvisoOperativoPedido from '../Partials/AvisoOperativoPedido';
import { MiniaturaDocumento } from '../Partials/ModalVistaPreviaDocumento';
import { formatoMoneda } from '../../../utils/formatoMoneda';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY, THEME_INPUT, THEME_LABEL } from '../../../utils/geliaTheme';

const MONTO = /^\d+(?:\.\d{1,2})?$/;
const ESPERA_AUTOGUARDADO = 600;

/** Hechos previos que detiene el reparto -> texto para Ventas (sin códigos internos). */
export const HECHOS_REGULARIZACION = {
    pago_capturado: 'Pago capturado',
    pago_validado: 'Pago validado',
    cierre_o_saf: 'Cierre de pago o saldo a favor aplicado',
    remision: 'Remisión registrada',
    guia: 'Guía registrada',
    empacada: 'Empaque registrado',
    empaque: 'Empaque registrado',
    salida_parcial: 'Salida parcial de cajas',
    cancelacion: 'Cancelación en curso',
    complemento: 'Complemento o anexo relacionado',
};

/** Centavos enteros de un monto capturado o leído; null si no es un monto válido. */
export function centavos(valor) {
    if (valor === null || valor === undefined || valor === '') return null;
    if (typeof valor === 'number') return Number.isFinite(valor) ? Math.round(valor * 100) : null;
    const texto = String(valor).trim();
    if (!MONTO.test(texto)) return null;
    const [enteros, decimales = ''] = texto.split('.');

    return Number(enteros) * 100 + Number(decimales.padEnd(2, '0'));
}

function filaDesdeBorrador(entrada) {
    const monto = Number(entrada?.total_mercancia);

    return {
        folio: entrada?.folio_remision || '',
        // El borrador guarda 0 cuando el valor aún no se captura.
        valor: Number.isFinite(monto) && monto > 0 ? monto.toFixed(2) : '',
        contenido: entrada?.contenido || '',
    };
}

function medidas(caja) {
    const lados = [caja.largo, caja.ancho, caja.alto];

    return lados.every((lado) => lado !== null && lado !== undefined && lado !== '') ? `${lados.join(' × ')} cm` : null;
}

function foliosRepetidos(cajas, filas) {
    const cuenta = {};
    cajas.forEach((caja) => {
        const folio = (filas[caja.uuid_operativo]?.folio || '').trim().toLowerCase();
        if (folio) cuenta[folio] = (cuenta[folio] || 0) + 1;
    });

    return new Set(Object.keys(cuenta).filter((folio) => cuenta[folio] > 1));
}

function folioDelMensaje(mensaje) {
    const coincidencia = /folio\s+(.+?)\s+(?:está|ya)/i.exec(mensaje || '');

    return coincidencia ? coincidencia[1].trim().toLowerCase() : null;
}

function Boton({ primario = false, icon: Icono, children, className = '', ...props }) {
    return (
        <button type="button" className={`${primario ? THEME_BTN_PRIMARY : GELIA_BTN_OUTLINE} min-h-[44px] max-w-full ${className}`} {...props}>
            {Icono && <Icono className="w-4 h-4 shrink-0" aria-hidden="true" />}
            {children}
        </button>
    );
}

function AccionActivar({ multienvio, apoyo = true }) {
    const ocupado = Boolean(multienvio.ocupado);

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap gap-2">
                <Boton primario icon={ShieldCheck} disabled={ocupado} onClick={() => multienvio.decidir('activar')}>
                    {multienvio.ocupado === 'activar' ? 'Activando multienvío…' : 'Activar multienvío con seguro'}
                </Boton>
            </div>
            {apoyo && <p className="m-0 text-sm theme-text-muted">Si el cliente requiere seguro de envío, cada caja tendrá su propio pedido, pago y remisión.</p>}
        </div>
    );
}

function Errores({ errores, omitir = [] }) {
    const mensajes = ['general', 'envios', 'multienvio'].filter((campo) => !omitir.includes(campo) && errores[campo]);
    if (!mensajes.length) return null;

    return mensajes.map((campo) => (
        <p key={campo} className="m-0 text-sm theme-text-aviso" role="alert">{errores[campo]}</p>
    ));
}

function Configuracion({ pedido, multienvio, onDirtyChange, onVer, bloqueado }) {
    const { datos, errores } = multienvio;
    const cajas = useMemo(() => datos?.cajas_origen || [], [datos]);
    const borrador = useMemo(() => {
        const lista = Array.isArray(datos?.grupo?.borrador) ? datos.grupo.borrador : [];

        return Object.fromEntries(lista.map((entrada) => [entrada.uuid_operativo, entrada]));
    }, [datos]);
    const editadas = useRef(new Set());
    const [filas, setFilas] = useState({});
    const [tocados, setTocados] = useState({});
    const [version, setVersion] = useState(0);
    const [guardada, setGuardada] = useState(0);
    const [guardando, setGuardando] = useState(false);
    const [intento, setIntento] = useState(false);
    const temporizador = useRef(null);
    const enCurso = useRef(null);
    const filasRef = useRef(filas);
    filasRef.current = filas;
    const versionRef = useRef(version);
    versionRef.current = version;
    const guardadaRef = useRef(guardada);
    guardadaRef.current = guardada;

    // Una lectura nueva nunca pisa lo que se está capturando: solo completa las cajas no editadas aquí.
    useEffect(() => {
        setFilas((previas) => {
            const siguientes = {};
            cajas.forEach((caja) => {
                const uuid = caja.uuid_operativo;
                siguientes[uuid] = editadas.current.has(uuid) && previas[uuid]
                    ? previas[uuid]
                    : filaDesdeBorrador(borrador[uuid]);
            });

            return siguientes;
        });
    }, [cajas, borrador]);

    const sucio = version !== guardada;
    useEffect(() => { onDirtyChange?.(sucio); }, [sucio, onDirtyChange]);
    useEffect(() => () => {
        clearTimeout(temporizador.current);
        onDirtyChange?.(false);
    }, [onDirtyChange]);

    const repetidos = foliosRepetidos(cajas, filas);
    const folioServidor = errores.folio_remision ? folioDelMensaje(errores.folio_remision) : null;
    const totalPedido = centavos(pedido?.total_mercancia) ?? 0;
    const montos = cajas.map((caja) => centavos(filas[caja.uuid_operativo]?.valor));
    const suma = montos.reduce((acumulado, monto) => acumulado + (monto ?? 0), 0);
    const diferencia = suma - totalPedido;
    const faltanFolios = cajas.some((caja) => !(filas[caja.uuid_operativo]?.folio || '').trim());
    const montoInvalido = montos.some((monto) => monto === null);
    const motivoBloqueo = (() => {
        if (!cajas.length) return 'No hay cajas activas para repartir.';
        if (faltanFolios) return 'Captura el folio de pedido Wizerp de cada caja.';
        if (repetidos.size) return 'Cada caja necesita un folio de pedido Wizerp distinto.';
        if (montoInvalido) return 'Captura el valor de mercancía de cada caja.';
        if (diferencia !== 0) return 'La suma de las cajas debe coincidir con el total del pedido.';

        return '';
    })();

    const payload = (lista) => cajas.map((caja) => {
        const fila = lista[caja.uuid_operativo] || {};
        const monto = centavos(fila.valor);

        return {
            uuid_operativo: caja.uuid_operativo,
            folio_remision: (fila.folio || '').trim() || null,
            total_mercancia: monto === null ? 0 : monto / 100,
            contenido: (fila.contenido || '').trim() || null,
        };
    });

    /** Guarda lo capturado (una petición a la vez). Devuelve false si no se pudo guardar. */
    const guardar = async () => {
        clearTimeout(temporizador.current);
        if (enCurso.current) await enCurso.current;
        const enviada = versionRef.current;
        if (enviada === guardadaRef.current) return true;
        if (foliosRepetidos(cajas, filasRef.current).size) return false;
        const tarea = (async () => {
            setGuardando(true);
            const resultado = await multienvio.guardarBorrador(payload(filasRef.current));
            setGuardando(false);
            if (resultado.ok) {
                guardadaRef.current = Math.max(guardadaRef.current, enviada);
                setGuardada(guardadaRef.current);
            }

            return resultado.ok;
        })();
        enCurso.current = tarea;
        const ok = await tarea;
        enCurso.current = null;

        return ok;
    };

    const programar = () => {
        clearTimeout(temporizador.current);
        temporizador.current = setTimeout(() => { guardar(); }, ESPERA_AUTOGUARDADO);
    };

    const cambiar = (uuid, campo, valor) => {
        editadas.current.add(uuid);
        setFilas((previas) => ({ ...previas, [uuid]: { ...(previas[uuid] || {}), [campo]: valor } }));
        setVersion((previa) => previa + 1);
        if (campo === 'folio_remision' || campo === 'folio') multienvio.limpiarError('folio_remision');
        if (campo === 'valor') multienvio.limpiarError('total_mercancia');
        setTocados((previos) => ({ ...previos, [`${uuid}:${campo}`]: false }));
        programar();
    };

    const salir = (uuid, campo) => {
        setTocados((previos) => ({ ...previos, [`${uuid}:${campo}`]: true }));
        if (versionRef.current !== guardadaRef.current) guardar();
    };

    const confirmar = () => {
        setIntento(true);
        if (motivoBloqueo || bloqueado) return;
        multienvio.confirmar(guardar);
    };

    const ocupado = Boolean(multienvio.ocupado);
    const confirmando = multienvio.ocupado === 'confirmar';
    const estadoBorrador = guardando
        ? 'Guardando borrador…'
        : (sucio ? (repetidos.size ? 'Corrige los folios repetidos para guardar el borrador.' : 'Cambios sin guardar.') : (version > 0 ? 'Borrador guardado.' : ''));
    const IconoDiferencia = diferencia === 0 ? CheckCircle2 : AlertTriangle;

    return (
        <div className="space-y-3">
            <p className="m-0 text-sm theme-text-main">
                Captura el folio de pedido Wizerp y el valor de mercancía de cada caja. La suma debe coincidir con el total del pedido.
            </p>
            <ol className="m-0 grid list-none gap-3 p-0" aria-label="Cajas del multienvío">
                {cajas.map((caja, indice) => {
                    const uuid = caja.uuid_operativo;
                    const fila = filas[uuid] || { folio: '', valor: '', contenido: '' };
                    const base = `multienvio-${uuid}`;
                    const folio = fila.folio.trim().toLowerCase();
                    const errorFolio = (() => {
                        if (folio && repetidos.has(folio)) return 'Este folio ya está en otra caja.';
                        if (errores.folio_remision && (folio && folio === folioServidor)) return errores.folio_remision;
                        if (errores.folio_remision && !folioServidor && indice === 0) return errores.folio_remision;
                        if (!folio && (tocados[`${uuid}:folio`] || intento)) return 'Indica el folio de pedido Wizerp.';

                        return '';
                    })();
                    const valorInvalido = fila.valor !== '' ? centavos(fila.valor) === null : (tocados[`${uuid}:valor`] || intento);
                    const errorValor = valorInvalido
                        ? (fila.valor === '' ? 'Indica el valor de mercancía.' : 'Usa un monto con hasta dos decimales, por ejemplo 1250.50.')
                        : '';
                    const datosCaja = [caja.tipo, caja.peso_cobrado_kg != null && caja.peso_cobrado_kg !== '' ? `${Number(caja.peso_cobrado_kg)} kg` : null, medidas(caja)].filter(Boolean);
                    const evidencias = caja.evidencias || [];

                    return (
                        <li key={uuid} className="rounded-xl border theme-border p-3 space-y-3">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="m-0 flex items-center gap-2 text-sm font-bold theme-text-main">
                                        <Package className="w-4 h-4 shrink-0" aria-hidden="true" />
                                        Caja {caja.orden || indice + 1}
                                    </p>
                                    {datosCaja.length > 0 && <p className="m-0 mt-0.5 text-xs theme-text-muted">{datosCaja.join(' · ')}</p>}
                                </div>
                                {evidencias.length > 0 && (
                                    <div className="flex flex-wrap gap-2" role="group" aria-label={`Evidencia de CEDIS de la caja ${caja.orden || indice + 1}`}>
                                        {evidencias.map((documento, posicion) => (
                                            <MiniaturaDocumento
                                                key={documento.id}
                                                documento={documento}
                                                className="block w-14 h-14 rounded-lg overflow-hidden border theme-border theme-element cursor-pointer"
                                                onVer={() => onVer?.(evidencias, posicion)}
                                            />
                                        ))}
                                    </div>
                                )}
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="min-w-0">
                                    <label className={THEME_LABEL} htmlFor={`${base}-folio`}>Folio de pedido Wizerp</label>
                                    <input
                                        id={`${base}-folio`}
                                        className={`${THEME_INPUT} mt-1 w-full min-h-[44px] text-base`}
                                        type="text" maxLength={80} autoComplete="off"
                                        value={fila.folio}
                                        disabled={bloqueado || confirmando}
                                        aria-invalid={errorFolio ? 'true' : undefined}
                                        aria-describedby={errorFolio ? `${base}-folio-error` : undefined}
                                        onChange={(evento) => cambiar(uuid, 'folio', evento.target.value)}
                                        onBlur={() => salir(uuid, 'folio')}
                                    />
                                    {errorFolio && <p id={`${base}-folio-error`} className="m-0 mt-1 text-sm theme-text-aviso" role="alert">{errorFolio}</p>}
                                </div>
                                <div className="min-w-0">
                                    <label className={THEME_LABEL} htmlFor={`${base}-valor`}>Valor de mercancía (MXN)</label>
                                    <input
                                        id={`${base}-valor`}
                                        className={`${THEME_INPUT} mt-1 w-full min-h-[44px] text-base`}
                                        type="text" inputMode="decimal" autoComplete="off"
                                        value={fila.valor}
                                        disabled={bloqueado || confirmando}
                                        aria-invalid={errorValor ? 'true' : undefined}
                                        aria-describedby={errorValor ? `${base}-valor-error` : undefined}
                                        onChange={(evento) => cambiar(uuid, 'valor', evento.target.value.replace(',', '.'))}
                                        onBlur={() => salir(uuid, 'valor')}
                                    />
                                    {errorValor && <p id={`${base}-valor-error`} className="m-0 mt-1 text-sm theme-text-aviso" role="alert">{errorValor}</p>}
                                </div>
                                <div className="min-w-0 sm:col-span-2">
                                    <label className={THEME_LABEL} htmlFor={`${base}-contenido`}>Contenido (opcional)</label>
                                    <input
                                        id={`${base}-contenido`}
                                        className={`${THEME_INPUT} mt-1 w-full min-h-[44px] text-base`}
                                        type="text" maxLength={500} autoComplete="off"
                                        value={fila.contenido}
                                        disabled={bloqueado || confirmando}
                                        onChange={(evento) => cambiar(uuid, 'contenido', evento.target.value)}
                                        onBlur={() => salir(uuid, 'contenido')}
                                    />
                                </div>
                            </div>
                        </li>
                    );
                })}
            </ol>
            <dl className="m-0 grid gap-2 rounded-xl border theme-border p-3 text-sm sm:grid-cols-3" aria-label="Totales del multienvío">
                <div>
                    <dt className="text-xs theme-text-muted">Total del pedido</dt>
                    <dd className="m-0 font-semibold theme-text-main">{formatoMoneda(totalPedido / 100)}</dd>
                </div>
                <div>
                    <dt className="text-xs theme-text-muted">Suma de las cajas</dt>
                    <dd className="m-0 font-semibold theme-text-main" data-suma-multienvio>{formatoMoneda(suma / 100)}</dd>
                </div>
                <div>
                    <dt className="text-xs theme-text-muted">Diferencia</dt>
                    <dd className={`m-0 flex items-center gap-1 font-semibold ${diferencia === 0 ? 'theme-text-exito' : 'theme-text-aviso'}`} role="status" data-diferencia-multienvio>
                        <IconoDiferencia className="w-4 h-4 shrink-0" aria-hidden="true" />
                        {diferencia === 0
                            ? 'Cuadra con el total'
                            : `${diferencia < 0 ? 'Faltan' : 'Sobran'} ${formatoMoneda(Math.abs(diferencia) / 100)}`}
                    </dd>
                </div>
            </dl>
            {errores.total_mercancia && <p className="m-0 text-sm theme-text-aviso" role="alert">{errores.total_mercancia}</p>}
            <Errores errores={errores} />
            {estadoBorrador && <p className="m-0 text-xs theme-text-muted" role="status">{estadoBorrador}</p>}
            <div className="flex flex-wrap gap-2">
                <Boton primario icon={CheckCircle2}
                    disabled={Boolean(motivoBloqueo) || ocupado || bloqueado}
                    aria-describedby={motivoBloqueo ? 'multienvio-motivo-confirmar' : undefined}
                    onClick={confirmar}>
                    {confirmando ? 'Confirmando multienvío…' : 'Confirmar multienvío'}
                </Boton>
                <Boton disabled={ocupado || bloqueado} onClick={() => multienvio.decidir('sin_seguro')}>
                    {multienvio.ocupado === 'sin_seguro' ? 'Continuando sin seguro…' : 'Continuar sin seguro'}
                </Boton>
            </div>
            {motivoBloqueo && <p id="multienvio-motivo-confirmar" className="m-0 text-sm theme-text-muted">{motivoBloqueo}</p>}
        </div>
    );
}

function iconoEnvio(envio) {
    if (envio.enviado) return Truck;
    if (envio.tiene_guia || envio.tiene_remision) return Receipt;
    if (envio.estado_pago === 'validado') return CheckCircle2;

    return Clock;
}

const TEXTO_PAGO = { validado: 'Pago validado', capturado: 'Pago capturado', sin_pago: 'Sin pago' };

function Activo({ pedido, datos }) {
    const envios = datos?.envios || [];
    const progreso = datos?.progreso || {};
    const cambiarEnvio = useCambiarEnvio();
    const total = envios.length;

    return (
        <div className="space-y-3">
            <p className="m-0 text-sm theme-text-main">
                {datos?.parcial
                    ? 'Este pedido forma parte de un multienvío. Solo ves los envíos a los que tienes acceso.'
                    : `Esta compra se repartió en ${total} envíos. Elige un envío para ver su cotización, pago y remisión.`}
            </p>
            <ol className="m-0 grid list-none gap-2 p-0 sm:grid-cols-3" aria-label="Envíos del multienvío">
                {envios.map((envio) => {
                    const Icono = iconoEnvio(envio);
                    const propio = envio.id === pedido?.id;
                    const pago = TEXTO_PAGO[envio.estado_pago] || 'Sin pago';
                    const interior = (
                        <>
                            <span className="flex w-full items-center justify-between gap-2">
                                <span className="inline-flex items-center gap-1 font-bold theme-text-main">
                                    <Layers className="w-4 h-4 shrink-0" aria-hidden="true" />
                                    Envío {envio.orden} de {total}
                                </span>
                                {propio && <span className="rounded-md px-1.5 py-0.5 text-[11px] font-bold" style={{ background: 'var(--color-primario)', color: 'var(--color-on-primario, #fff)' }}>Aquí estás</span>}
                            </span>
                            <span className="text-xs theme-text-muted">Folio Wizerp {envio.folio_remision || '—'}</span>
                            <span className="inline-flex items-center gap-1 text-xs theme-text-main">
                                <Icono className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                                {pago}{envio.estatus ? ` · ${envio.estatus}` : ''}
                            </span>
                        </>
                    );
                    const clases = `flex min-h-[44px] w-full flex-col items-start gap-1 rounded-xl border px-3 py-2 text-left text-sm ${propio ? 'theme-border-strong' : 'theme-border'}`;
                    const estilo = propio ? { borderColor: 'var(--color-primario)', boxShadow: '0 0 0 1px var(--color-primario)' } : undefined;

                    return (
                        <li key={envio.id}>
                            {propio || !cambiarEnvio ? (
                                <div className={clases} style={estilo} aria-current={propio ? 'true' : undefined}>{interior}</div>
                            ) : (
                                <button type="button" className={`${clases} theme-element`} onClick={() => cambiarEnvio(envio.id, pedido.id)}
                                    title={`Ir al Envío ${envio.orden}`}>
                                    {interior}
                                </button>
                            )}
                        </li>
                    );
                })}
            </ol>
            {envios.length > 0 && (
                <ul className="m-0 flex flex-wrap gap-2 list-none p-0" aria-label="Avance del multienvío">
                    {[
                        ['pagos_validados', 'Pagos validados', CheckCircle2],
                        ['remisiones', 'Remisiones', Receipt],
                        ['guias', 'Guías', Receipt],
                        ['enviados', 'Enviados', Truck],
                    ].filter(([clave]) => progreso[clave]).map(([clave, texto, Icono]) => (
                        <li key={clave} className="inline-flex items-center gap-1 text-xs theme-text-muted">
                            <Icono className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                            {texto} {progreso[clave].replace('/', ' de ')}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function Regularizacion({ datos }) {
    const hechos = datos?.grupo?.regularizacion?.hechos || [];

    return (
        <AvisoOperativoPedido tono="warning" icon={AlertTriangle} label="Multienvío pendiente de regularización">
            <p className="m-0">
                El pedido ya tiene movimientos que impiden repartirlo en envíos. Auxiliar y Ventas deben regularizarlos antes de separar las cajas; mientras tanto, el pedido continúa como un solo envío.
            </p>
            {hechos.length > 0 && (
                <ul className="m-0 mt-2 grid gap-1 pl-5 list-disc" aria-label="Movimientos detectados">
                    {hechos.map((hecho) => <li key={hecho}>{HECHOS_REGULARIZACION[hecho] || 'Otro movimiento registrado'}</li>)}
                </ul>
            )}
        </AvisoOperativoPedido>
    );
}

/**
 * Panel «Multienvío» del modal nacional (antes de la cotización). Presentacional sobre `useMultienvioNacional`.
 * Estados: aviso (decisión pendiente), configuración, activo, regularización y pedido estándar sin seguro.
 */
export default function PanelMultienvioNacional({ pedido, multienvio, onDirtyChange, onVer, bloqueado = false }) {
    const { datos, cargando, cargado, errores, avisoRevision } = multienvio;
    if (!cargado) {
        if (errores.carga) {
            return (
                <section className="registrar-tienda-panel space-y-2" aria-label="Multienvío">
                    <p className="m-0 text-sm theme-text-aviso" role="alert">{errores.carga}</p>
                    <Boton icon={RefreshCw} onClick={() => multienvio.recargar()}>Revisar de nuevo</Boton>
                </section>
            );
        }

        return cargando ? <p className="m-0 text-sm theme-text-muted" role="status">Revisando las cajas del envío…</p> : null;
    }

    const evaluacion = datos?.evaluacion || {};
    const estado = datos?.grupo?.estado ?? null;
    const pendiente = Boolean(evaluacion.elegible) && (estado === null || estado === 'pendiente_decision');
    const sinSeguro = estado === 'estandar_sin_seguro' && Boolean(evaluacion.elegible);
    if (!pendiente && !sinSeguro && !['configurando', 'activo', 'regularizacion_pendiente'].includes(estado)) return null;

    const nCajas = datos?.cajas_resumen?.n || evaluacion.n_cajas;
    const ocupado = Boolean(multienvio.ocupado);
    const resumen = datos?.cajas_resumen;

    return (
        <section className="registrar-tienda-panel space-y-3" aria-labelledby="multienvio-nacional-titulo" data-panel-multienvio={estado || 'pendiente'}>
            <div className="registrar-tienda-seccion-titulo">
                <Layers size={18} aria-hidden="true" />
                <h3 id="multienvio-nacional-titulo">{estado === 'activo' ? 'Envíos de esta compra' : 'Multienvío'}</h3>
            </div>
            {avisoRevision && (
                <AvisoOperativoPedido tono="info" icon={RefreshCw}>
                    <p className="m-0" role="status">{avisoRevision}</p>
                    {estado === 'configurando' && (
                        <Boton className="mt-2" icon={RefreshCw} disabled={ocupado} onClick={() => multienvio.decidir('activar')}>
                            {multienvio.ocupado === 'activar' ? 'Actualizando cajas…' : 'Actualizar con las cajas vigentes'}
                        </Boton>
                    )}
                </AvisoOperativoPedido>
            )}
            {pendiente && (
                <>
                    <AvisoOperativoPedido tono="info" icon={Layers} label="Multienvío detectado">
                        <p className="m-0">
                            CEDIS determinó que este pedido requiere {nCajas} cajas. Si el cliente requiere seguro de envío, cada caja deberá contar con su propio pedido, pago y remisión.
                        </p>
                        {resumen && (resumen.tipos?.length > 0 || resumen.peso_total_kg) ? (
                            <p className="m-0 mt-1 text-xs theme-text-muted">
                                {[resumen.tipos?.join(', '), resumen.peso_total_kg ? `${resumen.peso_total_kg} kg en total` : null].filter(Boolean).join(' · ')}
                            </p>
                        ) : null}
                    </AvisoOperativoPedido>
                    <div className="flex flex-wrap gap-2">
                        <Boton primario icon={ShieldCheck} disabled={ocupado || bloqueado} onClick={() => multienvio.decidir('activar')}>
                            {multienvio.ocupado === 'activar' ? 'Activando multienvío…' : 'Activar multienvío con seguro'}
                        </Boton>
                        <Boton disabled={ocupado || bloqueado} onClick={() => multienvio.decidir('sin_seguro')}>
                            {multienvio.ocupado === 'sin_seguro' ? 'Continuando sin seguro…' : 'Continuar sin seguro'}
                        </Boton>
                    </div>
                    <p className="m-0 text-sm theme-text-muted">Podrás activar el multienvío posteriormente si el cliente cambia de opinión.</p>
                    <Errores errores={errores} />
                </>
            )}
            {estado === 'configurando' && (
                <Configuracion pedido={pedido} multienvio={multienvio} onDirtyChange={onDirtyChange} onVer={onVer} bloqueado={bloqueado} />
            )}
            {sinSeguro && (
                <>
                    <p className="m-0 flex items-center gap-2 text-sm theme-text-main">
                        <CircleDot className="w-4 h-4 shrink-0" aria-hidden="true" />
                        El pedido continúa como un solo envío sin seguro por caja.
                    </p>
                    <AccionActivar multienvio={multienvio} />
                    <Errores errores={errores} />
                </>
            )}
            {estado === 'activo' && <Activo pedido={pedido} datos={datos} />}
            {estado === 'regularizacion_pendiente' && <Regularizacion datos={datos} />}
        </section>
    );
}

/** El panel retiene la cotización mientras falta la decisión o se configura el reparto. */
export function multienvioBloqueaCotizacion({ habilitado, multienvio, resumen, cajasActivas }) {
    if (!habilitado) return false;
    if (!multienvio.cargado) {
        if (multienvio.errores.carga) return false;

        return cajasActivas >= 2 && (!resumen || ['pendiente_decision', 'configurando'].includes(resumen.estado));
    }
    const evaluacion = multienvio.datos?.evaluacion || {};
    const estado = multienvio.datos?.grupo?.estado ?? null;

    return estado === 'configurando' || (Boolean(evaluacion.elegible) && (estado === null || estado === 'pendiente_decision'));
}
