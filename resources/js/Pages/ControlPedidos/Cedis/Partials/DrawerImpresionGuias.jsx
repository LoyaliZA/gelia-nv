import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { CalendarDays, ChevronDown, Download, Eye, FileText, Printer, Search, Truck, X } from 'lucide-react';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';
import usePedidoDialog from '../../Partials/usePedidoDialog';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';

const tabs = [
    { id: 'pendientes', nombre: 'Pendientes', Icono: Printer },
    { id: 'historial', nombre: 'Historial', Icono: CalendarDays },
];
const boton = 'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg px-3 text-sm font-bold';
const principal = `${THEME_BTN_PRIMARY} ${boton} px-3! py-2! text-sm! normal-case! tracking-normal!`;
const iconoBoton = 'theme-border theme-text-main grid size-11 shrink-0 place-items-center rounded-lg border hover:border-[var(--color-primario)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)]';
const acentoSuave = 'theme-text-primario bg-[color-mix(in_srgb,var(--color-primario)_12%,transparent)]';

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
    const [aviso, setAviso] = useState('');
    const operacion = useRef(null);
    const botonesTab = useRef({});

    useEffect(() => () => operacion.current?.abort(), []);

    const imprimir = async ({ guia, ids = [], lote = false }) => {
        if (operacion.current) return;
        setAviso('');
        const visor = window.open('about:blank', '_blank');
        if (!visor) {
            setAviso('Permite abrir pestañas nuevas para ver el PDF e intenta de nuevo.');
            return;
        }
        visor.opener = null;
        setProcesando(true);
        const controller = new AbortController();
        operacion.current = controller;
        let abierto = false;
        let url;
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
            const { data, headers } = lote
                ? await axios.post(route('control_pedidos.cedis.impresion_guias.lote'), { ids }, opciones)
                : await axios.get(guia.url, ids.length > 0 ? { ...opciones, params: { preparar: 1 } } : opciones);
            if (controller.signal.aborted) return;
            if (data.type !== 'application/pdf' || !data.size || visor.closed) throw new Error();
            const registro = headers?.['x-gelia-apertura'];
            if (ids.length > 0 && !registro) throw new Error();
            url = URL.createObjectURL(data);
            visor.location.replace(url);
            abierto = true;
            if (ids.length > 0) {
                await axios.post(registro, { ids }, {
                    headers: { Accept: 'application/json' },
                });
                actualizar();
            }
        } catch (fallo) {
            if (!controller.signal.aborted) {
                setAviso(abierto
                    ? 'El PDF se abrió. Vuelve a usar Imprimir para registrar la apertura.'
                    : await mensajeError(fallo, 'No se pudo abrir el PDF. Intenta de nuevo.'));
                if (fallo.response?.status === 409) actualizar();
            }
        } finally {
            if (!abierto) visor.close();
            if (url) {
                if (abierto) setTimeout(() => URL.revokeObjectURL(url), 60000);
                else URL.revokeObjectURL(url);
            }
            operacion.current = null;
            if (!controller.signal.aborted) setProcesando(false);
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
                        {busqueda ? 'No se encontraron guías para este folio.' : tab === 'pendientes' ? 'No hay guías pendientes de impresión.' : 'Aún no hay guías abiertas para imprimir.'}
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
                                        <button type="button" className={principal} disabled={procesando}
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
                {(tab === 'pendientes' || aviso || procesando) && (
                    <footer className="theme-border shrink-0 space-y-2 border-t p-4 sm:p-5">
                        {aviso && <p role="alert" className="theme-text-peligro m-0 text-sm">{aviso}</p>}
                        {procesando && <p role="status" className="theme-text-muted m-0 text-sm">Preparando PDF…</p>}
                        {tab === 'pendientes' && <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="m-0 text-sm font-semibold">{pendientes?.pedidos ?? 0} pedidos · {pendientes?.guias ?? 0} guías</p>
                            <button type="button" className={principal} disabled={procesando || error || !pendientes?.guias}
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
