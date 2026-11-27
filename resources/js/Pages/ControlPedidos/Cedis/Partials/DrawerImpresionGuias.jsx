import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { CalendarDays, ChevronDown, Download, Eye, FileText, Printer, Search, Truck, X } from 'lucide-react';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';
import usePedidoDialog from '../../Partials/usePedidoDialog';
import { BTN_SECONDARY } from '../../Partials/pedidosBmaStyles';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';

const tabs = [
    { id: 'pendientes', nombre: 'Pendientes', Icono: Printer },
    { id: 'historial', nombre: 'Historial', Icono: CalendarDays },
];
const boton = 'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg px-3 text-sm font-bold';
const principal = `${THEME_BTN_PRIMARY} ${boton} px-3! py-2! text-sm! normal-case! tracking-normal!`;
const secundario = `${BTN_SECONDARY} ${boton}`;
const iconoBoton = 'theme-border theme-text-main grid size-11 shrink-0 place-items-center rounded-lg border hover:border-[var(--color-primario)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)]';
const acentoSuave = 'theme-text-primario bg-[color-mix(in_srgb,var(--color-primario)_12%,transparent)]';

function mostrarDialogoImpresion(url, contenedor, signal) {
    return new Promise((resolve, reject) => {
        const iframe = document.createElement('iframe');
        const limpiar = () => {
            clearTimeout(temporizador);
            signal.removeEventListener('abort', limpiar);
            iframe.remove();
            reject(new Error());
        };
        const temporizador = setTimeout(limpiar, 10000);
        iframe.title = 'PDF para imprimir';
        iframe.setAttribute('aria-hidden', 'true');
        iframe.tabIndex = -1;
        iframe.className = 'pointer-events-none absolute size-px opacity-0';
        iframe.onload = () => {
            clearTimeout(temporizador);
            try {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                resolve();
            } catch (error) {
                reject(error);
            }
        };
        signal.addEventListener('abort', limpiar, { once: true });
        iframe.src = url;
        contenedor.appendChild(iframe);
    });
}

async function mensajeError(error, mensaje) {
    const dato = error.response?.data;
    if (dato instanceof Blob) {
        try {
            return JSON.parse(await dato.text()).message ?? mensaje;
        } catch {
            return mensaje;
        }
    }
    return dato?.message ?? mensaje;
}

export default function DrawerImpresionGuias({ cola, onClose }) {
    const { tab, setTab, busqueda, setBusqueda, pendientes, pedidos, error, actualizar } = cola;
    const dialog = usePedidoDialog({ abierto: true, onClose });
    const [previa, setPrevia] = useState(null);
    const [procesando, setProcesando] = useState(false);
    const [intento, setIntento] = useState(null);
    const [alternativa, setAlternativa] = useState(null);
    const [aviso, setAviso] = useState('');
    const operacion = useRef(null);
    const urlActiva = useRef(null);
    const botonesTab = useRef({});

    const limpiarPdf = () => {
        operacion.current?.abort();
        if (urlActiva.current) URL.revokeObjectURL(urlActiva.current);
        urlActiva.current = null;
    };
    useEffect(() => limpiarPdf, []);

    const imprimir = async ({ guia, ids = [], lote = false }) => {
        if (procesando || intento) return;
        limpiarPdf();
        setAlternativa(null);
        setAviso('');
        setProcesando(true);
        const controller = new AbortController();
        operacion.current = controller;
        const opciones = { responseType: 'blob', signal: controller.signal, headers: { Accept: 'application/json, application/pdf' } };
        try {
            if (lote) {
                const { data: manifiesto } = await axios.get(route('control_pedidos.cedis.impresion_guias.index'), {
                    params: { manifiesto: 1 },
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                });
                ids = manifiesto.pendientes.ids;
                if (!ids.length) {
                    setAviso('No quedan guías pendientes de impresión.');
                    actualizar();
                    return;
                }
            }
            const { data } = lote
                ? await axios.post(route('control_pedidos.cedis.impresion_guias.lote'), { ids }, opciones)
                : await axios.get(guia.url, opciones);
            if (controller.signal.aborted) return;
            if (!data.type.includes('application/pdf')) throw new Error();
            const url = URL.createObjectURL(data);
            urlActiva.current = url;
            const detalle = ids.length > 0 ? { ids, url } : null;
            try {
                await mostrarDialogoImpresion(url, dialog.ref.current, controller.signal);
                if (!controller.signal.aborted) setIntento(detalle);
            } catch {
                if (!controller.signal.aborted) {
                    setAlternativa({ url, detalle });
                    setAviso('Abre el PDF en el visor del navegador para imprimirlo.');
                }
            }
        } catch (fallo) {
            if (!controller.signal.aborted) {
                setAviso(await mensajeError(fallo, 'No se pudo preparar la impresión. Intenta de nuevo.'));
                if (fallo.response?.status === 409) actualizar();
            }
        } finally {
            if (!controller.signal.aborted) setProcesando(false);
        }
    };

    const confirmar = async () => {
        if (procesando || !intento) return;
        setAviso('');
        setProcesando(true);
        try {
            await axios.post(route('control_pedidos.cedis.impresion_guias.confirmar'), { ids: intento.ids }, {
                headers: { Accept: 'application/json' },
            });
            setIntento(null);
            limpiarPdf();
            actualizar();
        } catch (fallo) {
            setAviso(await mensajeError(fallo, 'No se pudo registrar la confirmación. Intenta de nuevo.'));
            if (fallo.response?.status === 409) {
                setIntento(null);
                limpiarPdf();
                actualizar();
            }
        } finally {
            setProcesando(false);
        }
    };

    const pulsarTab = (event, id) => {
        if (!['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const destino = event.key === 'Home' ? 'pendientes' : event.key === 'End' ? 'historial' : tabs.find(opcion => opcion.id !== id).id;
        setTab(destino);
        botonesTab.current[destino]?.focus();
    };

    return createPortal(
        <div className="gelia-modal-overlay" onClick={onClose}
            style={{ background: 'transparent', backdropFilter: 'none', padding: 0, justifyContent: 'flex-end', zIndex: 'var(--gelia-z-modal)' }}>
            <section {...dialog} aria-labelledby="cedis-impresion-titulo" onClick={event => event.stopPropagation()}
                className="theme-surface-solid theme-border theme-text-main relative flex h-dvh w-full flex-col border shadow-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[var(--color-primario)] sm:w-[min(42rem,60vw)] sm:rounded-2xl">
                <header className="shrink-0 space-y-4 p-4 sm:p-5">
                    <div className="flex items-start gap-3">
                        <span className={`${acentoSuave} flex size-12 shrink-0 items-center justify-center rounded-xl`}><Printer aria-hidden="true" /></span>
                        <div className="min-w-0 flex-1">
                            <h2 id="cedis-impresion-titulo" className="m-0 text-xl font-bold sm:text-2xl">Imprimir guías</h2>
                            <p className="theme-text-muted m-0 text-sm">Guías de envío disponibles para impresión</p>
                        </div>
                        <button type="button" className={iconoBoton} onClick={onClose} aria-label="Cerrar impresión de guías" title="Cerrar">
                            <X size={18} aria-hidden="true" />
                        </button>
                    </div>
                    <div role="tablist" aria-label="Estado de impresión" className="theme-border theme-element grid grid-cols-2 gap-1 rounded-xl border p-1">
                        {tabs.map(({ id, nombre, Icono }) => (
                            <button key={id} type="button" role="tab" id={`cedis-impresion-tab-${id}`} aria-controls="cedis-impresion-panel"
                                aria-selected={tab === id} tabIndex={tab === id ? 0 : -1}
                                ref={node => { botonesTab.current[id] = node; }} onClick={() => setTab(id)}
                                onKeyDown={event => pulsarTab(event, id)}
                                className={`${boton} ${tab === id ? acentoSuave : 'theme-text-main'}`}>
                                <Icono size={17} aria-hidden="true" />{nombre}
                                {id === 'pendientes' && pendientes && <span className={`${acentoSuave} rounded-full px-2 tabular-nums`}>{pendientes.pedidos}</span>}
                            </button>
                        ))}
                    </div>
                    <label className="block space-y-1 text-sm font-semibold">
                        Buscar por folio
                        <span className="theme-border theme-element flex min-h-[44px] items-center gap-2 rounded-lg border px-3">
                            <Search size={16} aria-hidden="true" />
                            <input type="search" value={busqueda} onChange={event => setBusqueda(event.target.value)}
                                maxLength={100} className="min-w-0 flex-1 border-0 bg-transparent p-1 text-sm focus-visible:ring-2 focus-visible:ring-[var(--color-primario)]" />
                        </span>
                    </label>
                </header>
                <div id="cedis-impresion-panel" role="tabpanel" aria-labelledby={`cedis-impresion-tab-${tab}`}
                    className="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain px-4 pb-4 sm:px-5" aria-busy={!pedidos && !error}>
                    {tab === 'pendientes' && pendientes && (
                        <div role="status" className="flex flex-wrap justify-between gap-2 text-sm">
                            <strong>{pendientes.pedidos} {pendientes.pedidos === 1 ? 'pedido con guías pendientes' : 'pedidos con guías pendientes'}</strong>
                            <span>{pendientes.guias} {pendientes.guias === 1 ? 'guía' : 'guías'} en total</span>
                        </div>
                    )}
                    {error && <p role="alert" className="theme-text-peligro text-sm">No se pudieron actualizar las guías. <button type="button" className="underline" onClick={actualizar}>Reintentar</button></p>}
                    {!pedidos && !error && <p role="status" className="theme-text-muted text-sm">Cargando guías…</p>}
                    {pedidos?.length === 0 && <p className="theme-text-muted text-sm">
                        {busqueda ? 'No se encontraron guías para este folio.' : tab === 'pendientes' ? 'No hay guías pendientes de impresión.' : 'Aún no hay impresiones confirmadas.'}
                    </p>}
                    {pedidos?.map(pedido => (
                        <details key={pedido.id} className="theme-border theme-surface group rounded-xl border">
                            <summary className="flex min-h-[44px] cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2 focus-visible:outline-2 focus-visible:outline-[var(--color-primario)]">
                                <span className={`${acentoSuave} flex size-10 shrink-0 items-center justify-center rounded-lg`}><Truck size={19} aria-hidden="true" /></span>
                                <span className="min-w-0 flex-1 break-words font-bold">Folio {pedido.folio}</span>
                                <span className={`${acentoSuave} shrink-0 rounded-full px-2 py-1 text-xs font-semibold`}>{pedido.guias.length} {pedido.guias.length === 1 ? 'guía' : 'guías'}</span>
                                <ChevronDown size={18} aria-hidden="true" className="shrink-0 group-open:rotate-180" />
                            </summary>
                            {pedido.guias.map(guia => (
                                <div key={guia.id} className="theme-border flex flex-wrap items-center gap-2 border-t px-3 py-2">
                                    <FileText size={18} className="theme-text-primario shrink-0" aria-hidden="true" />
                                    <div className="min-w-0 flex-1 basis-[8rem]">
                                        <p className="m-0 break-words text-sm font-bold">{guia.caja}</p>
                                        <p className="theme-text-muted m-0 truncate text-xs" title={guia.nombre_original}>{guia.nombre_original}</p>
                                        {!guia.vigente && <p className="theme-text-aviso m-0 text-xs">Versión anterior</p>}
                                    </div>
                                    <div className="ml-auto flex flex-wrap gap-2">
                                        <button type="button" className={iconoBoton} aria-label={`Ver ${guia.nombre_original}`} title="Ver PDF" onClick={() => setPrevia(guia)}><Eye size={18} aria-hidden="true" /></button>
                                        <a className={iconoBoton} href={guia.url} download={guia.nombre_original} aria-label={`Descargar ${guia.nombre_original}`} title="Descargar PDF"><Download size={18} aria-hidden="true" /></a>
                                        <button type="button" className={principal} disabled={procesando || Boolean(intento)}
                                            aria-label={`Imprimir ${guia.nombre_original}`}
                                            onClick={() => imprimir({ guia, ids: tab === 'pendientes' ? [guia.id] : [] })}>
                                            <Printer size={17} aria-hidden="true" /> Imprimir
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </details>
                    ))}
                </div>
                {(tab === 'pendientes' || aviso || procesando || intento || alternativa) && (
                    <footer className="theme-border shrink-0 space-y-2 border-t p-4 sm:p-5">
                        {aviso && <p role="alert" className="theme-text-peligro m-0 text-sm">{aviso}</p>}
                        {alternativa && <a className={secundario} href={alternativa.url} target="_blank" rel="noreferrer"
                            onClick={() => { setIntento(alternativa.detalle); setAlternativa(null); setAviso(''); }}>Abrir PDF para imprimir</a>}
                        {procesando && <p role="status" className="theme-text-muted m-0 text-sm">Procesando impresión…</p>}
                        {intento && <div className="flex flex-wrap items-center gap-2">
                            <p className="m-0 w-full text-sm">Confirma solo si se imprimieron {intento.ids.length === 1 ? 'la guía' : `las ${intento.ids.length} guías`}.</p>
                            <button type="button" className={principal} disabled={procesando} onClick={confirmar}>Confirmar impresión</button>
                            <button type="button" className={secundario} disabled={procesando} onClick={() => { setIntento(null); setAviso(''); limpiarPdf(); }}>Mantener pendientes</button>
                        </div>}
                        {tab === 'pendientes' && <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="m-0 text-sm font-semibold">{pendientes?.pedidos ?? 0} pedidos · {pendientes?.guias ?? 0} guías</p>
                            <button type="button" className={principal} disabled={procesando || Boolean(intento) || error || !pendientes?.guias}
                                onClick={() => imprimir({ lote: true })}>
                                <Printer size={18} aria-hidden="true" /> Imprimir todas ({pendientes?.guias ?? 0})
                            </button>
                        </div>}
                    </footer>
                )}
                <ModalVistaPreviaDocumento abierto={Boolean(previa)} documento={previa} onClose={() => setPrevia(null)} />
            </section>
        </div>,
        document.body,
    );
}
